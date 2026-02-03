<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSource extends Model
{
    protected $fillable = [
        'project_id',
        'name',
        'table',
        'primary_key',
        'fields',
        'last_synced_at',
        'is_active',
    ];

    protected $casts = [
        'fields' => 'array',
        'last_synced_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
