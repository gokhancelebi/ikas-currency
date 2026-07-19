<?php

namespace App\Lib\IkasSync;

use App\Models\Product as ProductModel;
use App\Models\Variation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProductSyncService
{
    public function __construct(
        private IkasProductGraphQL $graphql,
        private PricingService $pricing
    ) {
    }

    public function updateExisting(ProductModel $productModel, array $ikasProduct, Collection $dbCollections): void
    {
        if ($productModel->ikas_deleted_at !== null) {
            $productModel->ikas_deleted_at = null;
        }

        $profit = floatval($productModel->profit);
        $discount = floatval($productModel->discount);

        $productImage = IkasProductGraphQL::productMainImage($ikasProduct);
        if ($productModel->ikas_image != $productImage) {
            $productModel->ikas_image = $productImage;
        }

        $this->refreshIkasPrices($productModel, $ikasProduct);

        if (IkasProductGraphQL::isSimpleProduct($ikasProduct)) {
            $this->updateSimpleProduct($productModel, $ikasProduct, $profit, $discount);

            return;
        }

        $this->updateVariableProduct($productModel, $ikasProduct, $profit, $discount, $productImage);
    }

    private function refreshIkasPrices(ProductModel $productModel, array $ikasProduct): void
    {
        $variants = $ikasProduct['variants'] ?? [];
        if ($variants === []) {
            return;
        }

        $productModel->ikas_price = IkasProductGraphQL::variantSellPrice($variants[0]);

        foreach ($variants as $variant) {
            Variation::where('ikas_variant_id', $variant['id'])->update([
                'ikas_price' => IkasProductGraphQL::variantSellPrice($variant),
            ]);
        }
    }

    private function updateSimpleProduct(
        ProductModel $productModel,
        array $ikasProduct,
        float $profit,
        float $discount
    ): void {
        if (! $productModel->isSyncEnabled()) {
            $this->saveProductIfDirty($productModel);

            return;
        }

        if (! $productModel->hasCostConfigured()) {
            $this->saveProductIfDirty($productModel);

            return;
        }

        $basePrice = floatval($productModel->price);
        $calculated = $this->pricing->calculate($basePrice, $profit, $discount, $productModel->price_type);

        if ($this->pricing->pricesChanged(
            floatval($productModel->total_price),
            floatval($productModel->comparison_price),
            floatval($productModel->commission),
            $calculated
        )) {
            $productModel->total_price = $calculated['total'];
            $productModel->comparison_price = $calculated['comparison'];
            $productModel->commission = $calculated['commission'];

            $firstVariant = $ikasProduct['variants'][0];
            $currentSell = IkasProductGraphQL::variantSellPrice($firstVariant);
            $currentDiscount = IkasProductGraphQL::variantDiscountPrice($firstVariant);

            if (abs($calculated['total'] - $currentSell) > 0.01
                || abs($calculated['comparison'] - $currentDiscount) > 0.01) {
                $this->graphql->update_multiple_variation_prices(
                    $ikasProduct['id'],
                    [[
                        'variantId' => $firstVariant['id'],
                        'sellPrice' => $calculated['total'],
                        'discountPrice' => $calculated['comparison'],
                    ]]
                );
            }
        }

        $this->saveProductIfDirty($productModel);
    }

    private function updateVariableProduct(
        ProductModel $productModel,
        array $ikasProduct,
        float $profit,
        float $discount,
        string $productImage
    ): void {
        $syncPrices = $productModel->isSyncEnabled();
        $basePrice = floatval($productModel->price);
        $variantUpdates = [];
        $variantIds = [];
        $firstVariantTotalPrice = null;
        $firstVariantComparisonPrice = null;
        $firstVariantCommission = null;

        foreach ($ikasProduct['variants'] ?? [] as $variant) {
            $variantIds[] = $variant['id'];
            $variation = Variation::where('ikas_variant_id', $variant['id'])->first();

            if ($variation === null) {
                $this->createVariationFromIkas($variant, $productModel, $productImage, $profit, $discount);

                continue;
            }

            if (! $syncPrices || ! $variation->isSyncEnabled()) {
                $variation->sku = $variant['sku'] ?: $variation->sku;
                $variation->save();

                continue;
            }

            if ($productModel->multiple_price === 'yes') {
                if (! $variation->hasCostConfigured()) {
                    $variation->sku = $variant['sku'] ?: $variation->sku;
                    $variation->save();

                    continue;
                }

                $variationProfit = floatval($variation->profit);
                $variationDiscount = floatval($variation->discount);
                $variationBasePrice = floatval($variation->price);
                $variationPriceType = $variation->price_type;
            } else {
                if (! $productModel->hasCostConfigured()) {
                    $variation->sku = $variant['sku'] ?: $variation->sku;
                    $variation->save();

                    continue;
                }

                $variationBasePrice = $basePrice;
                $variationProfit = $profit;
                $variationDiscount = $discount;
                $variationPriceType = $productModel->price_type;
            }

            $calculated = $this->pricing->calculate(
                $variationBasePrice,
                $variationProfit,
                $variationDiscount,
                $variationPriceType
            );

            $variation->update([
                'price_type' => $variationPriceType,
                'total_price' => number_format($calculated['total'], 2, '.', ''),
                'comparison_price' => number_format($calculated['comparison'], 2, '.', ''),
                'commission' => number_format($calculated['commission'], 2, '.', ''),
                'discount' => $variationDiscount,
                'profit' => $variationProfit,
                'sku' => $variant['sku'] ?: $variation->sku,
                'ikas_price' => IkasProductGraphQL::variantSellPrice($variant),
            ]);

            $currentSell = IkasProductGraphQL::variantSellPrice($variant);
            $currentDiscount = IkasProductGraphQL::variantDiscountPrice($variant);

            if (abs($calculated['total'] - $currentSell) > 0.01
                || abs($calculated['comparison'] - $currentDiscount) > 0.01) {
                $variantUpdates[] = [
                    'variantId' => $variant['id'],
                    'sellPrice' => $calculated['total'],
                    'discountPrice' => $calculated['comparison'],
                ];
            }

            if ($productModel->multiple_price === 'yes' && $firstVariantTotalPrice === null) {
                $firstVariantTotalPrice = $calculated['total'];
                $firstVariantComparisonPrice = $calculated['comparison'];
                $firstVariantCommission = $calculated['commission'];
            }
        }

        if ($syncPrices && $productModel->multiple_price === 'yes') {
            $productModel->total_price = $firstVariantTotalPrice;
            $productModel->comparison_price = $firstVariantComparisonPrice;
            $productModel->commission = $firstVariantCommission;
        }

        if (! empty($variantUpdates)) {
            $this->graphql->update_multiple_variation_prices(
                $productModel->ikas_product_id,
                $variantUpdates
            );
        }

        Variation::where('ikas_product_id', $productModel->ikas_product_id)
            ->whereNotIn('ikas_variant_id', $variantIds)
            ->delete();

        $productModel->save();
    }

    private function createVariationFromIkas(
        array $variant,
        ProductModel $productModel,
        string $productImage,
        float $profit,
        float $discount
    ): void {
        Variation::create([
            'name' => IkasProductGraphQL::variantDisplayName($variant),
            'price' => null,
            'ikas_price' => IkasProductGraphQL::variantSellPrice($variant),
            'sync_enabled' => true,
            'sku' => $variant['sku'] ?: time(),
            'price_type' => $productModel->price_type,
            'discount' => $discount,
            'profit' => $profit,
            'commission' => '0',
            'total_price' => '0',
            'comparison_price' => '0',
            'ikas_product_id' => $productModel->ikas_product_id,
            'ikas_variant_id' => $variant['id'],
            'ikas_image' => IkasProductGraphQL::variantImage($variant, $productImage),
        ]);
    }

    public function createLocalRecords(array $ikasProduct): void
    {
        try {
            $firstVariant = $ikasProduct['variants'][0] ?? null;
            if ($firstVariant === null) {
                return;
            }

            $sku = $firstVariant['sku'] ?: time();
            $ikasPrice = IkasProductGraphQL::variantSellPrice($firstVariant);
            $productImage = IkasProductGraphQL::productMainImage($ikasProduct);

            ProductModel::create([
                'sku' => $sku,
                'name' => $ikasProduct['name'],
                'price' => null,
                'ikas_price' => $ikasPrice,
                'sync_enabled' => true,
                'price_type' => (string) config('ikas.default_price_type', 'TL'),
                'discount' => '1',
                'profit' => '1',
                'commission' => '0',
                'total_price' => '0',
                'comparison_price' => '0',
                'ikas_product_id' => $ikasProduct['id'],
                'ikas_image' => $productImage,
                'multiple_price' => 'no',
            ]);

            if (! IkasProductGraphQL::isSimpleProduct($ikasProduct)) {
                foreach ($ikasProduct['variants'] as $variant) {
                    Variation::create([
                        'sku' => $variant['sku'] ?: time(),
                        'name' => IkasProductGraphQL::variantDisplayName($variant),
                        'price' => null,
                        'ikas_price' => IkasProductGraphQL::variantSellPrice($variant),
                        'sync_enabled' => true,
                        'price_type' => (string) config('ikas.default_price_type', 'TL'),
                        'discount' => '1',
                        'profit' => '1',
                        'commission' => '0',
                        'total_price' => '0',
                        'comparison_price' => '0',
                        'ikas_product_id' => $ikasProduct['id'],
                        'ikas_variant_id' => $variant['id'],
                        'ikas_image' => IkasProductGraphQL::variantImage($variant, $productImage),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to create local database records: '.$e->getMessage());
            throw $e;
        }
    }

    private function saveProductIfDirty(ProductModel $productModel): void
    {
        if ($productModel->isDirty()) {
            $productModel->save();
        }
    }
}
