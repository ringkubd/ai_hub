<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiPackage extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'rate_limit_per_minute',
        'rate_limit_per_day',
        'rate_limit_per_month',
        'price',
        'features',
        'allowed_endpoints',
        'is_active',
        'max_api_keys',
    ];

    protected $casts = [
        'features' => 'array',
        'allowed_endpoints' => 'array',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function canAccessEndpoint(string $endpoint): bool
    {
        if (empty($this->allowed_endpoints)) {
            return true; // No restrictions
        }

        foreach ($this->allowed_endpoints as $pattern) {
            if (fnmatch($pattern, $endpoint)) {
                return true;
            }
        }

        return false;
    }
}
