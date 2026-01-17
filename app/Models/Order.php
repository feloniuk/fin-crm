<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Traits\HasMoney;
use App\Traits\HasPhone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'our_company_id',
        'with_vat',
        'subtotal',
        'discount_total',
        'order_number',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'raw_data' => 'array',
            'total_amount' => 'decimal:2',
            'with_vat' => 'boolean',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }

    // Boot events

    protected static function booted()
    {
        static::creating(function (self $model) {
            if (!$model->order_number) {
                $model->order_number = self::generateOrderNumber();
            }
        });
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

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function ourCompany(): BelongsTo
    {
        return $this->belongsTo(OurCompany::class);
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
        return $this->status->canCreateInvoice()
            && !$this->invoice
            && $this->our_company_id !== null
            && $this->with_vat !== null
            && $this->items()->exists();
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
                'external_id' => data_get($item, 'product_id', data_get($item, 'id')),
            ];
        })->toArray();
    }

    public static function findByExternalId(int $shopId, string $externalId): ?self
    {
        return static::where('shop_id', $shopId)
            ->where('external_id', $externalId)
            ->first();
    }

    /**
     * Перерахувати суми замовлення з order_items
     */
    public function recalculateTotals(): void
    {
        $subtotal = $this->items()->sum('subtotal');
        $discountTotal = $this->items()->sum('discount_amount');

        $this->update([
            'subtotal' => (float) $subtotal,
            'discount_total' => (float) $discountTotal,
        ]);
    }

    /**
     * Синхронізувати товари з raw_data (викликається Action)
     */
    public function syncItemsFromRawData(): void
    {
        // Це буде викликано з SyncOrderItemsAction
        // Це заповнювач для можливості виклику з Filament форм
    }

    /**
     * Генерувати номер замовлення у форматі DDMMYYYYNN
     * DD - день, MM - місяць, YYYY - рік, NN - порядковий номер
     * Приклад: 17012026001 (17 січня 2026, перше замовлення цього дня)
     */
    public static function generateOrderNumber(): string
    {
        $today = now();
        $datePrefix = $today->format('dmY'); // DDMMYYYY

        // Знайти кількість замовлень створених сьогодні
        $todayOrdersCount = static::whereDate('created_at', $today)
            ->lockForUpdate()
            ->count();

        // Порядковий номер (з нулями)
        $sequenceNumber = str_pad($todayOrdersCount + 1, 3, '0', STR_PAD_LEFT);

        return $datePrefix . $sequenceNumber;
    }
}
