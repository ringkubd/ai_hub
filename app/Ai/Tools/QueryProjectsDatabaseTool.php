<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QueryProjectsDatabaseTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Execute SQL SELECT queries on the construction projects database. ' .
            'REQUIRED parameter: {"sql": "SELECT column_name FROM table_name WHERE condition", "limit": 50}. ' .
            'Returns JSON with rows array. Only SELECT/WITH queries allowed, no INSERT/UPDATE/DELETE. ' .
            'Example: {"sql": "SELECT id, name, total FROM invoices ORDER BY created_at DESC", "limit": 10}. ' .
            'Use the schema tool FIRST to discover table and column names before querying.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $sql = trim((string) ($request['sql'] ?? ''));
        $limit = (int) ($request['limit'] ?? 50);
        $limit = max(1, min($limit, 100));

        if ($sql === '') {
            return 'SQL is required.';
        }

        if (! preg_match('/^\s*(select|with)\s+/i', $sql)) {
            return 'Only SELECT / WITH read-only queries are allowed.';
        }

        if (str_contains($sql, ';') || preg_match('/\b(insert|update|delete|drop|alter|truncate|create|replace|grant|revoke|commit|rollback|lock|unlock|set)\b/i', $sql)) {
            return 'Unsafe SQL detected. Only single read-only SELECT statements are allowed.';
        }

        try {
            $wrappedSql = "select * from ({$sql}) as project_ai_result limit {$limit}";
            $rows = DB::connection('project')->select($wrappedSql);

            return json_encode([
                'rows_returned' => count($rows),
                'limit' => $limit,
                'rows' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return 'Query failed: ' . $e->getMessage();
        }
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sql' => $schema->string()->required(),
            'limit' => $schema->integer(),
        ];
    }
}
