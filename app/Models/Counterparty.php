<?php

namespace App\Models;

use App\Traits\HasPhone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Counterparty extends Model
{
    use HasFactory, HasPhone;

    protected $fillable = [
        'name',
        'edrpou_ipn',
        'address',
        'phone',
        'email',
        'is_auto_created',
    ];

    protected function casts(): array
    {
        return [
            'is_auto_created' => 'boolean',
        ];
    }

    // Relationships

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // Scopes

    public function scopeAutoCreated($query)
    {
        return $query->where('is_auto_created', true);
    }

    public function scopeManuallyCreated($query)
    {
        return $query->where('is_auto_created', false);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('edrpou_ipn', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }

    // Helpers

    public static function findByEdrpou(string $edrpou): ?self
    {
        return static::where('edrpou_ipn', $edrpou)->first();
    }

    public static function createFromOrder(Order $order): self
    {
        return static::create([
            'name' => $order->customer_name,
            'phone' => $order->customer_phone,
            'is_auto_created' => true,
        ]);
    }
}
