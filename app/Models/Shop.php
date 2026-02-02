<?php

namespace App\Models;

use App\Enums\ShopType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'api_credentials',
        'is_active',
        'sync_interval_minutes',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ShopType::class,
            'api_credentials' => 'array',
            'is_active' => 'boolean',
            'sync_interval_minutes' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    // Relationships

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, ShopType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeDueForSync($query)
    {
        return $query->where('is_active', true)
            ->whereNotNull('sync_interval_minutes')
            ->where(function ($q) {
                $q->whereNull('last_synced_at')
                    ->orWhereRaw('last_synced_at <= DATE_SUB(NOW(), INTERVAL sync_interval_minutes MINUTE)');
            });
    }

    // Helpers

    public function getApiCredential(string $key, $default = null): mixed
    {
        return data_get($this->api_credentials, $key, $default);
    }

    public function markSynced(): void
    {
        $this->update(['last_synced_at' => now()]);
    }

    public function shouldSync(): bool
    {
        if (!$this->is_active || !$this->sync_interval_minutes) {
            return false;
        }

        if (!$this->last_synced_at) {
            return true;
        }

        return $this->last_synced_at->addMinutes($this->sync_interval_minutes)->isPast();
    }

    public function getNextSyncAt(): ?\Carbon\Carbon
    {
        if (!$this->is_active || !$this->sync_interval_minutes) {
            return null;
        }

        if (!$this->last_synced_at) {
            return now();
        }

        return $this->last_synced_at->addMinutes($this->sync_interval_minutes);
    }
}
