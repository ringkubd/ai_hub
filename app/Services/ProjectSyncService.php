<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ProjectSyncService
{
    public function sync(Project $project): void
    {
        Log::info('Starting Qdrant sync', ['project' => $project->id, 'name' => $project->name]);
        $connection = $project->resolvedConnection();
        if (empty($connection['database'])) {
            \Log::warning("Project has no resolved database connection; skipping Qdrant sync", ['project' => $project->id]);
            return;
        }

        $connectionName = $this->registerConnection($project, $connection);
        $chunkSize = (int) config('aihub.chunk.size');
        $overlap = (int) config('aihub.chunk.overlap');
        $embeddingModel = (string) config('aihub.embedding_model');

        $activeSources = $project->sources()->where('is_active', true)->get();
        if ($activeSources->isEmpty()) {
            Log::warning('Project has no active sources; nothing to sync', ['project' => $project->id]);
            return;
        }

        foreach ($activeSources as $source) {
            $this->syncSource($project, $source, $connectionName, $chunkSize, $overlap, $embeddingModel);
        }

        $project->forceFill(['last_synced_at' => now()])->save();
        Log::info('Completed Qdrant sync', ['project' => $project->id]);
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

        $rows = DB::connection($connectionName)
            ->table($source->table)
            ->select(array_merge([$primaryKey], $fields))
            ->get();

        Log::info('Syncing source rows', [
            'project' => $project->id,
            'source' => $source->id,
            'table' => $source->table,
            'rows' => $rows->count(),
        ]);

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

        foreach ($rows as $row) {
            $payloadText = $this->buildPayloadText($fields, $row);
            $chunks = $chunker->chunk($payloadText, $chunkSize, $overlap);

            foreach ($chunks as $index => $chunk) {
                $chunk = $this->normalizeText($chunk);
                if ($chunk === '') {
                    continue;
                }
                $hash = hash('sha256', $chunk);
                $pointId = $this->pointId($project->id, (string) $row->{$primaryKey}, $index, $hash);

                $existing = ProjectDocument::where('point_id', $pointId)->first();
                if ($existing) {
                    continue;
                }

                $embedding = $embedder->embed($chunk);
                if ($embedding === []) {
                    continue;
                }

                $qdrant->upsert($collection, [[
                    'id' => $pointId,
                    'vector' => $embedding,
                    'payload' => [
                        'project_id' => $project->id,
                        'source' => $source->name,
                        'source_table' => $source->table,
                        'source_id' => (string) $row->{$primaryKey},
                        'text' => $chunk,
                        'model' => $embeddingModel,
                    ],
                ]]);

                ProjectDocument::create([
                    'project_id' => $project->id,
                    'project_source_id' => $source->id,
                    'source_type' => $source->table,
                    'source_id' => (string) $row->{$primaryKey},
                    'chunk_hash' => $hash,
                    'content' => $chunk,
                    'point_id' => $pointId,
                ]);
            }
        }

        $source->forceFill(['last_synced_at' => now()])->save();
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
        return hash('sha256', $projectId . '|' . $sourceId . '|' . $index . '|' . $hash);
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
}
