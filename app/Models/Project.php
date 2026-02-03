<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProjectDocument;
use App\Models\ProjectSource;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'connection',
        'env_key',
        'qdrant_collection',
        'include_tables',
        'exclude_tables',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'connection' => 'encrypted:array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'include_tables' => 'array',
        'exclude_tables' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if ($project->slug === '' || $project->slug === null) {
                $project->slug = Str::slug($project->name);
            }
        });
    }

    public function qdrantCollectionName(): string
    {
        return $this->qdrant_collection ?: 'project_'.$this->id;
    }

    public function resolvedConnection(): array
    {
        // Use attribute access to avoid colliding with Eloquent's $connection property
        $stored = $this->getAttribute('connection');
        Log::debug("Resolving database connection for project {$this->id}", ['env_key' => $this->env_key, 'stored_type' => gettype($stored)]);

        // Prefer explicit stored connection (array or JSON) when it contains a database value
        if (is_array($stored) && ! empty($stored['database'])) {
            Log::info('Using stored DB connection for project', ['project' => $this->id]);

            return $stored;
        }

        if (is_string($stored) && $stored !== '') {
            $decoded = json_decode($stored, true);
            if (is_array($decoded) && ! empty($decoded['database'])) {
                Log::info('Using stored DB connection (json) for project', ['project' => $this->id]);

                return $decoded;
            }
        }

        // Fall back to env-based connection if env_key is set
        if ($this->env_key) {
            $prefix = strtoupper($this->env_key);

            Log::info('Using env_key DB connection for project', ['project' => $this->id, 'env_key' => $this->env_key]);

            return [
                'driver' => env("{$prefix}_DB_DRIVER"),
                'host' => env("{$prefix}_DB_HOST"),
                'port' => env("{$prefix}_DB_PORT"),
                'database' => env("{$prefix}_DB_DATABASE"),
                'username' => env("{$prefix}_DB_USERNAME"),
                'password' => env("{$prefix}_DB_PASSWORD"),
            ];
        }

        return [];
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ProjectSource::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }
}
