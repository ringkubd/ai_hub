<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectSyncService
{
    public function sync(Project $project): void
    {
        $lock = Cache::lock('aihub:sync:project:'.$project->id, 3600);
        if (! $lock->get()) {
            Log::warning('Sync already running for project', ['project' => $project->id]);
            return;
        }

        Log::info('Starting Qdrant sync', ['project' => $project->id, 'name' => $project->name]);
        try {
            $connection = $project->resolvedConnection();
            if (empty($connection['database'])) {
                \Log::warning("Project has no resolved database connection; skipping Qdrant sync", ['project' => $project->id]);
                return;
            }

            $connectionName = $this->registerConnection($project, $connection);
            $chunkSize = (int) config('aihub.chunk.size');
            $overlap = (int) config('aihub.chunk.overlap');
            $embeddingModel = (string) config('aihub.embedding_model');

            $includeTables = $this->normalizeTableList($project->include_tables ?? []);
            $excludeTables = $this->normalizeTableList($project->exclude_tables ?? []);
            $useAutoTables = $includeTables !== [] || $excludeTables !== [];

            $activeSources = $project->sources()->where('is_active', true)->get();
            if (! $useAutoTables && $activeSources->isEmpty()) {
                Log::warning('Project has no active sources; nothing to sync', ['project' => $project->id]);
                return;
            }

            if ($useAutoTables) {
                $tables = $this->resolveTables($connectionName, (string) $connection['database'], $includeTables, $excludeTables);
                if ($tables === []) {
                    Log::warning('No tables matched include/exclude rules; nothing to sync', ['project' => $project->id]);
                    return;
                }

                foreach ($tables as $table) {
                    $fields = $this->resolveTableColumns($connectionName, (string) $connection['database'], $table);
                    if ($fields === []) {
                        continue;
                    }
                    $primaryKey = $this->resolvePrimaryKey($connectionName, (string) $connection['database'], $table);
                    $this->syncTable(
                        $project,
                        $connectionName,
                        $table,
                        $table,
                        null,
                        $fields,
                        $primaryKey,
                        $chunkSize,
                        $overlap,
                        $embeddingModel
                    );
                }
            } else {
                foreach ($activeSources as $source) {
                    $this->syncSource($project, $source, $connectionName, $chunkSize, $overlap, $embeddingModel);
                }
            }

            $project->forceFill(['last_synced_at' => now()])->save();
            Log::info('Completed Qdrant sync', ['project' => $project->id]);
        } finally {
            optional($lock)->release();
        }
    }

    private function syncSource(
        Project $project,
        ProjectSource $source,
        string $connectionName,
        int $chunkSize,
        int $overlap,
        string $embeddingModel
    ): void {
        $primaryKey = $source->primary_key ?: 'id';
        $fields = $source->fields ?? [];

        if (! is_array($fields) || $fields === []) {
            \Log::warning('Project source has no fields defined; skipping', ['project' => $project->id, 'source' => $source->id]);
            return;
        }

        $this->syncTable(
            $project,
            $connectionName,
            $source->table,
            $source->name,
            $source->id,
            $fields,
            $primaryKey,
            $chunkSize,
            $overlap,
            $embeddingModel
        );

        $source->forceFill(['last_synced_at' => now()])->save();
    }

    private function syncTable(
        Project $project,
        string $connectionName,
        string $table,
        string $sourceName,
        ?int $projectSourceId,
        array $fields,
        ?string $primaryKey,
        int $chunkSize,
        int $overlap,
        string $embeddingModel
    ): void {
        $chunker = app(Chunker::class);
        $embedder = app(EmbeddingService::class);
        $qdrant = app(QdrantClient::class);
        $collection = $this->collectionName($project);

        $vectorSize = $this->embeddingSize($embedder, 'healthcheck');
        if ($vectorSize <= 0) {
            \Log::warning("Embedding service returned zero-length vector, aborting sync for project {$project->id}", ['project' => $project->id]);
            return;
        }

        $createResponse = $qdrant->createCollection($collection, $vectorSize);
        if (! $createResponse->successful() && $createResponse->status() !== 409) {
            \Log::error('Failed to create Qdrant collection', [
                'collection' => $collection,
                'status' => $createResponse->status(),
                'body' => $createResponse->body(),
            ]);

            return;
        }

        if ($primaryKey === null || $primaryKey === '') {
            Log::warning('Table has no primary key; using content hash', [
                'project' => $project->id,
                'table' => $table,
            ]);
        }

        $selectFields = $primaryKey ? array_merge([$primaryKey], $fields) : $fields;
        $query = DB::connection($connectionName)
            ->table($table)
            ->select($selectFields);

        $orderBy = $primaryKey ?: ($fields[0] ?? null);
        if ($orderBy) {
            $query->orderBy($orderBy);
        }

        $query->chunk(500, function ($rows) use (
            $project,
            $sourceName,
            $table,
            $projectSourceId,
            $fields,
            $primaryKey,
            $chunker,
            $embedder,
            $qdrant,
            $collection,
            $chunkSize,
            $overlap,
            $embeddingModel
        ) {
            Log::info('Syncing source rows', [
                'project' => $project->id,
                'table' => $table,
                'rows' => $rows->count(),
            ]);

            foreach ($rows as $row) {
                if ($primaryKey && (! isset($row->{$primaryKey}) || $row->{$primaryKey} === null || $row->{$primaryKey} === '')) {
                    Log::warning('Skipping row with empty primary key', [
                        'project' => $project->id,
                        'table' => $table,
                        'primary_key' => $primaryKey,
                    ]);
                    continue;
                }

                $payloadText = $this->buildPayloadText($fields, $row);
                $chunks = $chunker->chunk($payloadText, $chunkSize, $overlap);

                foreach ($chunks as $index => $chunk) {
                    $chunk = $this->normalizeText($chunk);
                    if ($chunk === '') {
                        continue;
                    }
                    $hash = hash('sha256', $chunk);
                    $sourceId = $primaryKey ? (string) $row->{$primaryKey} : hash('sha256', $payloadText);
                    $pointId = $this->pointId($project->id, $sourceId, $index, $hash);

                    $existing = ProjectDocument::where('point_id', $pointId)->first();
                    if ($existing) {
                        continue;
                    }

                    $embedding = $embedder->embed($chunk);
                    if ($embedding === []) {
                        continue;
                    }

                    $upsertResponse = $qdrant->upsert($collection, [[
                        'id' => $pointId,
                        'vector' => $embedding,
                        'payload' => [
                            'project_id' => $project->id,
                            'source' => $sourceName,
                            'source_table' => $table,
                            'source_id' => $sourceId,
                            'text' => $chunk,
                            'model' => $embeddingModel,
                        ],
                    ]]);

                    if (! $upsertResponse->successful()) {
                        Log::error('Failed to upsert Qdrant point', [
                            'project' => $project->id,
                            'table' => $table,
                            'status' => $upsertResponse->status(),
                            'body' => $upsertResponse->body(),
                        ]);
                        continue;
                    }

                    DB::table('project_documents')->insertOrIgnore([
                        'project_id' => $project->id,
                        'project_source_id' => $projectSourceId,
                        'source_type' => $table,
                        'source_id' => $sourceId,
                        'chunk_hash' => $hash,
                        'content' => $chunk,
                        'point_id' => $pointId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    private function buildPayloadText(array $fields, object $row): string
    {
        $parts = [];
        foreach ($fields as $field) {
            if (isset($row->{$field}) && $row->{$field} !== null) {
                $value = $row->{$field};
                if (is_scalar($value)) {
                    $parts[] = "{$field}: {$value}";
                } elseif (is_object($value) && method_exists($value, '__toString')) {
                    $parts[] = "{$field}: {$value}";
                }
            }
        }

        return $this->normalizeText(implode("\n", $parts));
    }

    private function pointId(int $projectId, string $sourceId, int $index, string $hash): string
    {
        $raw = hash('sha256', $projectId . '|' . $sourceId . '|' . $index . '|' . $hash);
        return $this->hashToUnsignedIntString($raw);
    }

    private function collectionName(Project $project): string
    {
        return $project->qdrantCollectionName();
    }

    private function embeddingSize(EmbeddingService $embedder, string $sample): int
    {
        $embedding = $embedder->embed($sample);
        return is_array($embedding) ? count($embedding) : 0;
    }

    private function registerConnection(Project $project, array $connection): string
    {
        $name = 'project_' . $project->id;

        config([
            "database.connections.{$name}" => [
                'driver' => $connection['driver'] ?? 'mysql',
                'host' => $connection['host'] ?? '127.0.0.1',
                'port' => $connection['port'] ?? '3306',
                'database' => $connection['database'] ?? '',
                'username' => $connection['username'] ?? '',
                'password' => $connection['password'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ],
        ]);

        return $name;
    }

    private function normalizeTableList(array $tables): array
    {
        return collect($tables)
            ->filter(fn ($table) => is_string($table) && trim($table) !== '')
            ->map(fn ($table) => strtolower(trim($table)))
            ->values()
            ->all();
    }

    private function resolveTables(string $connectionName, string $database, array $include, array $exclude): array
    {
        $defaultExcludes = [
            'migrations',
            'jobs',
            'job_batches',
            'failed_jobs',
            'cache',
            'cache_locks',
            'sessions',
            'password_reset_tokens',
            'personal_access_tokens',
            'telescope_entries',
            'telescope_entries_tags',
            'telescope_monitoring',
        ];

        $exclude = array_values(array_unique(array_merge($defaultExcludes, $exclude)));
        $tables = DB::connection($connectionName)->select(
            'select table_name as name from information_schema.tables where table_schema = ? and table_type = ?',
            [$database, 'BASE TABLE']
        );

        $names = collect($tables)
            ->map(fn ($row) => strtolower((string) ($row->name ?? '')))
            ->filter(fn ($name) => $name !== '')
            ->values()
            ->all();

        if ($include !== []) {
            return array_values(array_filter($names, fn ($name) => in_array($name, $include, true)));
        }

        return array_values(array_filter($names, fn ($name) => ! in_array($name, $exclude, true)));
    }

    private function resolveTableColumns(string $connectionName, string $database, string $table): array
    {
        $columns = DB::connection($connectionName)->select(
            'select column_name as name from information_schema.columns where table_schema = ? and table_name = ? order by ordinal_position',
            [$database, $table]
        );

        return collect($columns)
            ->map(fn ($row) => (string) ($row->name ?? ''))
            ->filter(fn ($name) => $name !== '')
            ->values()
            ->all();
    }

    private function resolvePrimaryKey(string $connectionName, string $database, string $table): ?string
    {
        $row = DB::connection($connectionName)->selectOne(
            'select column_name as name from information_schema.key_column_usage where table_schema = ? and table_name = ? and constraint_name = ? order by ordinal_position limit 1',
            [$database, $table, 'PRIMARY']
        );

        if (! $row || empty($row->name)) {
            return null;
        }

        return (string) $row->name;
    }

    private function normalizeText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        if (function_exists('mb_check_encoding') && ! mb_check_encoding($text, 'UTF-8')) {
            if (function_exists('mb_convert_encoding')) {
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            }
        }

        if (function_exists('iconv')) {
            $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if (is_string($clean)) {
                $text = $clean;
            }
        }

        return trim($text);
    }

    private function hashToUnsignedIntString(string $hex): string
    {
        $hex = preg_replace('/[^0-9a-f]/i', '', $hex) ?? '';
        if ($hex === '') {
            return '0';
        }

        $slice = substr($hex, 0, 16);
        if ($slice === '' || $slice === false) {
            return '0';
        }

        return base_convert($slice, 16, 10);
    }
}
