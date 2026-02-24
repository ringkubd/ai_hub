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
        return 'Returns table schemas for Projects database. Call WITHOUT parameters to list all available tables. Call WITH a table name to get detailed columns/relations for that table.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        // the request may be empty or contain "table" as string
        if ($request->has('table')) {
            $value = $request['table'];

            if (! is_string($value)) {
                return 'Invalid parameter: "table" must be a string.';
            }

            $table = trim($value);

            if ($table === '') {
                // treat empty string as no table specified
                return $this->catalog->compactText();
            }

            $details = $this->catalog->table($table);

            if (! $details) {
                return 'No matching Projects table/model found for: ' . $table;
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
        // "table" is optional; if provided it must be a string.
        return [
            'table' => $schema->string()->nullable(),
        ];
    }
}
