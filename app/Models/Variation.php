<?php

namespace App\Models;

use App\Models\Concerns\HasPricingState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variation extends Model
{
    use HasFactory;
    use HasPricingState;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'price',
        'shopify_price',
        'sync_enabled',
        'sku',
        'price_type',
        'discount',
        'profit',
        'commission',
        'total_price',
        'comparison_price',
        'shopify_product_id',
        'shopify_variant_id',
        'shopify_image',
    ];

    protected $casts = [
        'sync_enabled' => 'boolean',
        'shopify_price' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'shopify_product_id', 'shopify_product_id');
    }
}
