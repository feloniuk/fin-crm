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
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ShopType::class,
            'api_credentials' => 'array',
            'is_active' => 'boolean',
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

    // Helpers

    public function getApiCredential(string $key, $default = null): mixed
    {
        return data_get($this->api_credentials, $key, $default);
    }

    public function markSynced(): void
    {
        $this->update(['last_synced_at' => now()]);
    }
}
