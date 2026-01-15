<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Traits\HasMoney;
use App\Traits\HasPhone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory, HasMoney, HasPhone;

    protected $fillable = [
        'shop_id',
        'external_id',
        'customer_name',
        'customer_phone',
        'customer_comment',
        'total_amount',
        'status',
        'raw_data',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'raw_data' => 'array',
            'total_amount' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }

    // Relationships

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    // Scopes

    public function scopeNew($query)
    {
        return $query->where('status', OrderStatus::NEW);
    }

    public function scopeWithoutInvoice($query)
    {
        return $query->doesntHave('invoice');
    }

    public function scopeFromShop($query, int|Shop $shop)
    {
        $shopId = $shop instanceof Shop ? $shop->id : $shop;

        return $query->where('shop_id', $shopId);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('external_id', 'like', "%{$term}%")
              ->orWhere('customer_name', 'like', "%{$term}%")
              ->orWhere('customer_phone', 'like', "%{$term}%");
        });
    }

    // Helpers

    public function canCreateInvoice(): bool
    {
        return $this->status->canCreateInvoice() && !$this->invoice;
    }

    public function markAsInvoiced(): void
    {
        $this->update(['status' => OrderStatus::INVOICED]);
    }

    public function markAsPaid(): void
    {
        $this->update(['status' => OrderStatus::PAID]);
    }

    public function getItemsFromRawData(): array
    {
        // Get items array from raw_data - handle both Horoshop and Prom formats
        $items = data_get($this->raw_data, 'items',
            data_get($this->raw_data, 'products', [])
        );

        return collect($items)->map(function ($item) {
            return [
                'name' => data_get($item, 'name', data_get($item, 'title', 'Товар')),
                'quantity' => (float) data_get($item, 'quantity', data_get($item, 'amount', 1)),
                'unit_price' => (float) data_get($item, 'price', 0),
            ];
        })->toArray();
    }

    public static function findByExternalId(int $shopId, string $externalId): ?self
    {
        return static::where('shop_id', $shopId)
            ->where('external_id', $externalId)
            ->first();
    }
}
