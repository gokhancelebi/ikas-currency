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
        'ikas_price',
        'sync_enabled',
        'sku',
        'price_type',
        'discount',
        'profit',
        'commission',
        'total_price',
        'comparison_price',
        'ikas_product_id',
        'ikas_variant_id',
        'ikas_image',
    ];

    protected $casts = [
        'sync_enabled' => 'boolean',
        'ikas_price' => 'decimal:2',
        'price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'ikas_product_id', 'ikas_product_id');
    }

    public function hasSku(): bool
    {
        return trim((string) ($this->sku ?? '')) !== '';
    }

    public function skuLine(): ?string
    {
        if (! $this->hasSku()) {
            return null;
        }

        return __('common.sku').': '.$this->sku;
    }
}
