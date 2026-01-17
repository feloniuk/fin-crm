<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductExternalId extends Model
{
    use HasFactory;

    protected $table = 'product_external_ids';

    protected $fillable = ['product_id', 'source', 'external_id'];

    // Relationships

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
