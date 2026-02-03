<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'name',
        'quantity',
        'unit_price',
        'discount_type',
        'discount_value',
        'discount_amount',
        'subtotal',
        'total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Relationships

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Boot events

    protected static function booted()
    {
        static::saving(function (self $model) {
            $model->calculateAmounts();
        });

        static::saved(function (self $model) {
            $model->order?->recalculateTotals();
        });

        static::deleted(function (self $model) {
            $model->order?->recalculateTotals();
        });
    }

    // Helpers

    /**
     * Розрахувати суми для цього товару
     */
    public function calculateAmounts(): void
    {
        // Розраховуємо subtotal
        $this->subtotal = (float) $this->quantity * (float) $this->unit_price;

        // Розраховуємо discount_amount залежно від типу знижки
        $discountAmount = 0;
        if ($this->discount_type === 'percent' && $this->discount_value) {
            $discountAmount = $this->subtotal * ((float) $this->discount_value / 100);
        } elseif ($this->discount_type === 'fixed' && $this->discount_value) {
            $discountAmount = (float) $this->discount_value;
        }

        // Перевіряємо, щоб знижка не була більше за subtotal
        $this->discount_amount = min($discountAmount, $this->subtotal);

        // Розраховуємо total
        $this->total = max(0, $this->subtotal - $this->discount_amount);
    }
}
