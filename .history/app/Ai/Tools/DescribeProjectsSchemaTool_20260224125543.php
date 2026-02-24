<?php

namespace App\Ai\Tools;

use App\Ai\Support\ProjectsSchemaCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DescribeProjectsSchemaTool implements Tool
{
    public function __construct(protected ProjectsSchemaCatalog $catalog) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Returns table columns and Eloquent relationship mappings for Projects models on the project database connection.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $table = isset($request['table']) ? trim((string) $request['table']) : null;

        if ($table) {
            $details = $this->catalog->table($table);

            if (! $details) {
                return 'No matching Projects table/model found for: '.$table;
            }

            return json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return $this->catalog->compactText();
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'table' => $schema->string(),
        ];
    }
}
