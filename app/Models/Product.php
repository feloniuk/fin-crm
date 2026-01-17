<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    // Relationships

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function externalIds(): HasMany
    {
        return $this->hasMany(ProductExternalId::class);
    }

    // Helpers

    /**
     * Знайти або створити товар за назвою
     */
    public static function findOrCreateByName(string $name): self
    {
        return static::firstOrCreate(['name' => $name]);
    }

    /**
     * Знайти товар за external_id + source
     */
    public static function findByExternalId(string $externalId, string $source): ?self
    {
        $productExternalId = ProductExternalId::where('external_id', $externalId)
            ->where('source', $source)
            ->first();

        return $productExternalId?->product;
    }
}
