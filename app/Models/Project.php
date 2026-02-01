<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'connection' => 'encrypted:array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            if ($project->slug === '' || $project->slug === null) {
                $project->slug = Str::slug($project->name);
            }
        });
    }

    public function resolvedConnection(): array
    {
        if ($this->env_key) {
            $prefix = strtoupper($this->env_key);

            return [
                'driver' => env("{$prefix}_DB_DRIVER"),
                'host' => env("{$prefix}_DB_HOST"),
                'port' => env("{$prefix}_DB_PORT"),
                'database' => env("{$prefix}_DB_DATABASE"),
                'username' => env("{$prefix}_DB_USERNAME"),
                'password' => env("{$prefix}_DB_PASSWORD"),
            ];
        }

        return $this->connection ?? [];
    }
}
