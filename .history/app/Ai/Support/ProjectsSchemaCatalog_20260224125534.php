<?php

namespace App\Ai\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class ProjectsSchemaCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return collect($this->projectModelClasses())
            ->map(fn (string $modelClass) => $this->describeModel($modelClass))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function table(string $tableOrModel): ?array
    {
        $needle = strtolower(trim($tableOrModel));

        foreach ($this->all() as $definition) {
            $modelName = strtolower(class_basename($definition['model']));
            $tableName = strtolower($definition['table']);

            if ($needle === $modelName || $needle === $tableName) {
                return $definition;
            }
        }

        return null;
    }

    public function compactText(?string $tableOrModel = null): string
    {
        $definitions = $tableOrModel
            ? array_filter([$this->table($tableOrModel)])
            : $this->all();

        if (empty($definitions)) {
            return 'No matching Projects table/model found.';
        }

        $lines = [
            'Projects database schema (connection: project):',
        ];

        foreach ($definitions as $definition) {
            $lines[] = '';
            $lines[] = sprintf('- %s (%s)', $definition['table'], class_basename($definition['model']));
            $lines[] = '  columns: '.implode(', ', $definition['columns']);

            if (empty($definition['relations'])) {
                $lines[] = '  relations: none';

                continue;
            }

            $lines[] = '  relations:';

            foreach ($definition['relations'] as $relation) {
                $meta = [];

                if (! empty($relation['foreign_key'])) {
                    $meta[] = 'fk='.$relation['foreign_key'];
                }

                if (! empty($relation['local_key'])) {
                    $meta[] = 'local='.$relation['local_key'];
                }

                if (! empty($relation['pivot_table'])) {
                    $meta[] = 'pivot='.$relation['pivot_table'];
                }

                $lines[] = sprintf(
                    '    - %s: %s -> %s%s',
                    $relation['name'],
                    $relation['type'],
                    $relation['related_table'],
                    empty($meta) ? '' : ' ('.implode(', ', $meta).')',
                );
            }
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @return array<int, string>
     */
    protected function projectModelClasses(): array
    {
        $files = glob(app_path('Models/Projects/*.php')) ?: [];

        return collect($files)
            ->map(fn (string $path) => 'App\\Models\\Projects\\'.pathinfo($path, PATHINFO_FILENAME))
            ->filter(fn (string $class) => class_exists($class))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function describeModel(string $modelClass): ?array
    {
        $model = new $modelClass;

        if (! $model instanceof Model) {
            return null;
        }

        $table = $model->getTable();

        try {
            $columns = Schema::connection('project')->getColumnListing($table);
        } catch (Throwable) {
            $columns = [];
        }

        return [
            'model' => $modelClass,
            'table' => $table,
            'columns' => array_values($columns),
            'relations' => $this->extractRelations($model),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractRelations(Model $model): array
    {
        $reflection = new ReflectionClass($model);

        return collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(function (ReflectionMethod $method) use ($reflection) {
                return $method->class === $reflection->getName()
                    && ! $method->isStatic()
                    && $method->getNumberOfRequiredParameters() === 0;
            })
            ->map(function (ReflectionMethod $method) use ($model) {
                try {
                    $result = $model->{$method->getName()}();
                } catch (Throwable) {
                    return null;
                }

                if (! $result instanceof Relation) {
                    return null;
                }

                $relation = [
                    'name' => $method->getName(),
                    'type' => class_basename($result),
                    'related_model' => $result->getRelated()::class,
                    'related_table' => $result->getRelated()->getTable(),
                    'foreign_key' => null,
                    'local_key' => null,
                    'pivot_table' => null,
                ];

                if ($result instanceof BelongsTo) {
                    $relation['foreign_key'] = $result->getForeignKeyName();
                    $relation['local_key'] = $result->getOwnerKeyName();
                }

                if ($result instanceof HasOne || $result instanceof HasMany) {
                    $relation['foreign_key'] = $result->getForeignKeyName();
                    $relation['local_key'] = $result->getLocalKeyName();
                }

                if ($result instanceof BelongsToMany) {
                    $relation['pivot_table'] = $result->getTable();
                    $relation['foreign_key'] = $result->getForeignPivotKeyName();
                    $relation['local_key'] = $result->getRelatedPivotKeyName();
                }

                return $relation;
            })
            ->filter()
            ->values()
            ->all();
    }
}
