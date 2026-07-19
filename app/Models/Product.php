<?php

namespace App\Models;

use App\Models\Concerns\HasPricingState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    use HasPricingState;

    # _token is not in the database
    protected $guarded = ['_token'];

    protected $fillable = [
        'sku',
        'name',
        'price',
        'shopify_price',
        'sync_enabled',
        'price_type',
        'discount',
        'profit',
        'commission',
        'total_price',
        'comparison_price',
        'shopify_product_id',
        'shopify_image',
        'multiple_price',
        'shopify_deleted_at',
    ];

    protected $casts = [
        'sync_enabled' => 'boolean',
        'shopify_price' => 'decimal:2',
        'price' => 'decimal:2',
        'shopify_deleted_at' => 'datetime',
    ];

    /** Shopify mağazasında silinmiş / API listesinde yok */
    public function isDeletedFromShopify(): bool
    {
        return $this->shopify_deleted_at !== null;
    }

    public function variations()
    {
        return $this->hasMany(Variation::class, 'shopify_product_id', 'shopify_product_id');
    }

    /**
     * Varyant bazlı düzenleme ekranı: çoklu fiyat açık ve en az bir varyant kaydı.
     */
    public function hasEditableVariations(): bool
    {
        $count = $this->variations_count ?? $this->variations()->count();

        return $this->multiple_price === 'yes' && $count > 0;
    }

    public function isVariable(): bool
    {
        return ($this->variations_count ?? $this->variations()->count()) > 0;
    }

    /** Liste satırı için maliyet durumu */
    public function listCostStatus(): string
    {
        if ($this->multiple_price === 'yes' && $this->isVariable()) {
            $missing = (int) ($this->variations_missing_cost_count ?? 0);

            return $missing > 0 ? 'missing' : 'set';
        }

        return $this->hasCostConfigured() ? 'set' : 'missing';
    }

    /** Liste satırında gösterilecek maliyet metni */
    public function listCostLabel(): string
    {
        if ($this->multiple_price === 'yes' && $this->isVariable()) {
            $missing = (int) ($this->variations_missing_cost_count ?? 0);
            if ($missing > 0) {
                return __('products.variants_missing_cost', ['count' => $missing]);
            }

            return $this->appendPriceType(__('products.cost_in_variants'));
        }

        if ($this->hasCostConfigured()) {
            return $this->appendPriceType(number_format((float) $this->price, 2));
        }

        return __('common.dash');
    }

    /** Maliyet metnine birim ekler (ör. 25.00 USD) */
    private function appendPriceType(string $label): string
    {
        $type = trim((string) ($this->price_type ?? ''));
        if ($type === '') {
            return $label;
        }

        return $label.' '.$type;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<self>  $query */
    public function scopeFilterSearch($query, ?string $search): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $skuList = array_values(array_filter(array_map('trim', explode(',', $search))));

        $query->where(function ($q) use ($search, $skuList) {
            $q->where('name', 'like', '%'.$search.'%')
                ->orWhere('shopify_product_id', 'like', '%'.$search.'%');

            if (count($skuList) > 1) {
                $q->orWhereIn('sku', $skuList);
            } else {
                $q->orWhere('sku', 'like', '%'.$search.'%');
            }
        });
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<self>  $query */
    public function scopeWhereCostMissing($query): void
    {
        $query->where(function ($q) {
            $q->where(function ($q2) {
                $q2->where('multiple_price', 'no')
                    ->where(function ($c) {
                        $c->whereNull('price')->orWhere('price', '');
                    });
            })->orWhere(function ($q2) {
                $q2->where('multiple_price', 'yes')
                    ->where(function ($q3) {
                        $q3->whereHas('variations', function ($v) {
                            $v->whereNull('price')->orWhere('price', '');
                        })->orWhere(function ($q4) {
                            $q4->whereDoesntHave('variations')
                                ->where(function ($c) {
                                    $c->whereNull('price')->orWhere('price', '');
                                });
                        });
                    });
            });
        });
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<self>  $query */
    public function scopeWhereCostConfigured($query): void
    {
        $query->where(function ($q) {
            $q->where(function ($q2) {
                $q2->where('multiple_price', 'no')
                    ->whereNotNull('price')
                    ->where('price', '!=', '');
            })->orWhere(function ($q2) {
                $q2->where('multiple_price', 'yes')
                    ->whereHas('variations')
                    ->whereDoesntHave('variations', function ($v) {
                        $v->whereNull('price')->orWhere('price', '');
                    });
            });
        });
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<self>  $query */
    public function scopeFilterCost($query, ?string $cost): void
    {
        match ($cost) {
            'missing' => $query->whereCostMissing(),
            'set' => $query->whereCostConfigured(),
            default => null,
        };
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<self>  $query */
    public function scopeFilterSync($query, ?string $sync): void
    {
        match ($sync) {
            'inactive' => $query->where(function ($q) {
                $q->where('sync_enabled', false)
                    ->orWhereHas('variations', fn ($v) => $v->where('sync_enabled', false));
            }),
            'active' => $query->where('sync_enabled', true)
                ->whereDoesntHave('variations', fn ($v) => $v->where('sync_enabled', false)),
            default => null,
        };
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<self>  $query */
    public function scopeFilterType($query, ?string $type): void
    {
        match ($type) {
            'simple' => $query->whereDoesntHave('variations'),
            'variable' => $query->whereHas('variations'),
            default => null,
        };
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<self>  $query */
    public function scopeFilterMultiplePrice($query, ?string $multiplePrice): void
    {
        if (in_array($multiplePrice, ['yes', 'no'], true)) {
            $query->where('multiple_price', $multiplePrice);
        }
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<self>  $query */
    public function scopeFilterPriceType($query, ?string $priceType): void
    {
        $priceType = trim((string) $priceType);
        if ($priceType !== '' && $priceType !== 'all') {
            $query->where('price_type', $priceType);
        }
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<self>  $query */
    public function scopeFilterShopifyStatus($query, ?string $status): void
    {
        match ($status) {
            'deleted' => $query->whereNotNull('shopify_deleted_at'),
            'active' => $query->whereNull('shopify_deleted_at'),
            default => null,
        };
    }

    /** Liste / filtre: maliyet eksik, sync kapalı veya Shopify'da silinmiş */
    public function attentionReasons(): array
    {
        $reasons = [];

        if ($this->isDeletedFromShopify()) {
            $reasons[] = __('products.attention.shopify_deleted');
        }

        if ($this->listCostStatus() === 'missing') {
            $reasons[] = __('products.attention.cost_missing');
        }

        if (! $this->sync_enabled) {
            $reasons[] = __('products.attention.product_sync_off');
        }

        $syncOffCount = (int) ($this->variations_sync_off_count ?? 0);
        if ($syncOffCount > 0) {
            $reasons[] = $syncOffCount === 1
                ? __('products.attention.one_variant_sync_off')
                : __('products.attention.variants_sync_off', ['count' => $syncOffCount]);
        }

        return $reasons;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<self>  $query */
    public function scopeNeedsAttention($query): void
    {
        $query->where(function ($q) {
            $q->whereCostMissing()
                ->orWhere('sync_enabled', false)
                ->orWhereHas('variations', fn ($v) => $v->where('sync_enabled', false))
                ->orWhereNotNull('shopify_deleted_at');
        });
    }

    public function collections()
    {
        return $this->belongsToMany(Collection::class, null, null, null)
            ->using(function ($product) {
                return Collection::whereJsonContains('product_list', $product->shopify_product_id)->get();
            });
    }
}
