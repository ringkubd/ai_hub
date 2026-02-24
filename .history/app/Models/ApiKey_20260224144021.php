<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'user_id',
        'api_package_id',
        'name',
        'key',
        'prefix',
        'description',
        'capabilities',
        'metadata',
        'rate_limit_override',
        'last_used_at',
        'expires_at',
        'is_active',
        'allowed_ips',
        'usage_count',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'metadata' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'key', // Never expose the actual key
    ];

    protected static function booted(): void
    {
        static::creating(function (ApiKey $apiKey) {
            if (empty($apiKey->key)) {
                $apiKey->key = hash('sha256', Str::random(64));
            }
            if (empty($apiKey->prefix)) {
                $apiKey->prefix = 'sk_' . Str::random(4);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ApiPackage::class, 'api_package_id');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(ApiUsageLog::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }

    public function getRateLimit(): int
    {
        if ($this->rate_limit_override) {
            return $this->rate_limit_override;
        }

        return $this->package?->rate_limit_per_minute ?? 60;
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    public function getMaskedKey(): string
    {
        return $this->prefix . '...' . substr($this->key, -8);
    }
}
