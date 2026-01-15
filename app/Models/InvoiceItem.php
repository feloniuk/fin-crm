<?php

namespace App\Models;

use App\Traits\HasMoney;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory, HasMoney;

    protected $fillable = [
        'invoice_id',
        'name',
        'quantity',
        'unit',
        'unit_price',
        'discount',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    // Relationships

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // Boot

    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $item) {
            $item->total = $item->calculateTotal();
        });

        static::saved(function (self $item) {
            $item->invoice->recalculateTotals();
        });

        static::deleted(function (self $item) {
            $item->invoice->recalculateTotals();
        });
    }

    // Helpers

    public function calculateTotal(): float
    {
        return round(($this->quantity * $this->unit_price) - $this->discount, 2);
    }

    public function getFormattedQuantity(): string
    {
        $quantity = rtrim(rtrim(number_format($this->quantity, 3, ',', ' '), '0'), ',');

        return $quantity . ' ' . $this->unit;
    }
}
