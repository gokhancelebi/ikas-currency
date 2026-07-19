<?php

namespace App\Lib\ShopifySync;

use App\Models\Product as ProductModel;
use App\Models\Variation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProductSyncService
{
    public function __construct(
        private ShopifyProductGraphQL $graphql,
        private PricingService $pricing
    ) {
    }

    public function updateExisting(ProductModel $productModel, array $shopifyProduct, Collection $dbCollections): void
    {
        // Aktif sync sırasında mağazada olduğu doğrulandı
        if ($productModel->shopify_deleted_at !== null) {
            $productModel->shopify_deleted_at = null;
        }

        $profit = floatval($productModel->profit);
        $discount = floatval($productModel->discount);

        echo "Using product profit from database: ".$profit."\n";

        $productImage = '';
        if (! empty($shopifyProduct['images']['edges'])) {
            $productImage = $shopifyProduct['images']['edges'][0]['node']['src'];
        }
        if ($productModel->shopify_image != $productImage) {
            $productModel->shopify_image = $productImage;
        }

        // Mağaza fiyatını her sync'te güncelle (maliyet alanına yazma)
        $this->refreshShopifyPrices($productModel, $shopifyProduct);

        $isSimpleProduct = count($shopifyProduct['variants']['edges']) === 1
            && $shopifyProduct['variants']['edges'][0]['node']['title'] === 'Default Title';

        if ($isSimpleProduct) {
            $this->updateSimpleProduct($productModel, $shopifyProduct, $profit, $discount);

            return;
        }

        $this->updateVariableProduct($productModel, $shopifyProduct, $profit, $discount, $productImage);
    }

    private function refreshShopifyPrices(ProductModel $productModel, array $shopifyProduct): void
    {
        $variants = $shopifyProduct['variants']['edges'];
        if ($variants === []) {
            return;
        }

        $productModel->shopify_price = floatval($variants[0]['node']['price'] ?? 0);

        foreach ($variants as $variantEdge) {
            $variant = $variantEdge['node'];
            Variation::where('shopify_variant_id', $variant['id'])->update([
                'shopify_price' => floatval($variant['price']),
            ]);
        }
    }

    private function updateSimpleProduct(
        ProductModel $productModel,
        array $shopifyProduct,
        float $profit,
        float $discount
    ): void {
        echo "simple product\n";

        if (! $productModel->isSyncEnabled()) {
            echo "sync disabled for ".$productModel->shopify_product_id."\n";
            $this->saveProductIfDirty($productModel);

            return;
        }

        if (! $productModel->hasCostConfigured()) {
            echo "cost not configured for ".$productModel->shopify_product_id." — skipping price update\n";
            $this->saveProductIfDirty($productModel);

            return;
        }

        $basePrice = floatval($productModel->price);
        echo "Calculating with base_price: ".$basePrice." and profit: ".$profit."%\n";

        $calculated = $this->pricing->calculate($basePrice, $profit, $discount, $productModel->price_type);

        if ($this->pricing->pricesChanged(
            floatval($productModel->total_price),
            floatval($productModel->comparison_price),
            floatval($productModel->commission),
            $calculated
        )) {
            echo "prices changed for ".$productModel->shopify_product_id."\n";

            $productModel->total_price = $calculated['total'];
            $productModel->comparison_price = $calculated['comparison'];
            $productModel->commission = $calculated['commission'];

            $firstVariant = $shopifyProduct['variants']['edges'][0]['node'];
            $currentShopifyPrice = floatval($firstVariant['price']);
            $currentShopifyComparePrice = floatval($firstVariant['compareAtPrice'] ?? $firstVariant['price']);

            if ($calculated['total'] != $currentShopifyPrice
                || $calculated['comparison'] != $currentShopifyComparePrice) {
                $this->graphql->update_multiple_variation_prices(
                    $shopifyProduct['id'],
                    [[
                        'id' => $firstVariant['id'],
                        'price' => $calculated['total'],
                        'compareAtPrice' => $calculated['comparison'],
                    ]]
                );
            }
        } else {
            echo "prices not changed for ".$productModel->shopify_product_id."\n";
        }

        $removed = Variation::where('shopify_product_id', $productModel->shopify_product_id)->delete();
        if ($removed > 0) {
            echo "Removed {$removed} stale variation record(s) — product is simple in Shopify\n";
        }

        $this->saveProductIfDirty($productModel);
    }

    private function updateVariableProduct(
        ProductModel $productModel,
        array $shopifyProduct,
        float $profit,
        float $discount,
        string $productImage
    ): void {
        $variants = $shopifyProduct['variants']['edges'];
        $variantUpdates = [];
        $variantIds = [];

        echo "Processing variants with profit: ".$profit."%\n";
        echo "Multiple price setting: ".$productModel->multiple_price."\n";

        $syncPrices = $productModel->isSyncEnabled();
        if (! $syncPrices) {
            echo "sync disabled for ".$productModel->shopify_product_id." — skipping price updates\n";
        }

        $basePrice = floatval($productModel->price);
        $useProductLevelCost = $productModel->multiple_price === 'no';

        if ($syncPrices && $useProductLevelCost && $productModel->hasCostConfigured()) {
            $calculated = $this->pricing->calculate($basePrice, $profit, $discount, $productModel->price_type);
            $productModel->total_price = $calculated['total'];
            $productModel->comparison_price = $calculated['comparison'];
            $productModel->commission = $calculated['commission'];
        } elseif ($syncPrices && $useProductLevelCost) {
            echo "cost not configured for ".$productModel->shopify_product_id." — skipping price update\n";
        }

        $firstVariantTotalPrice = null;
        $firstVariantComparisonPrice = null;
        $firstVariantCommission = null;

        foreach ($variants as $variantEdge) {
            $variant = $variantEdge['node'];
            $variantIds[] = $variant['id'];

            $variation = Variation::where('shopify_variant_id', $variant['id'])->first();

            if (! $variation && $variant['title'] !== 'Default Title') {
                $this->createVariationFromShopify($variant, $productModel, $productImage, $profit, $discount);

                continue;
            }

            if (! $variation) {
                continue;
            }

            $variation->shopify_price = floatval($variant['price']);

            if (! $syncPrices) {
                $variation->sku = $variant['sku'] ?: $variation->sku;
                $variation->save();

                continue;
            }

            if ($productModel->multiple_price === 'yes') {
                if (! $variation->isSyncEnabled() || ! $variation->hasCostConfigured()) {
                    $variation->save();
                    echo "skipping variant {$variation->sku} — sync off or cost missing\n";

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
                'shopify_price' => floatval($variant['price']),
            ]);

            $variantUpdates[] = [
                'id' => $variant['id'],
                'price' => $calculated['total'],
                'compareAtPrice' => $calculated['comparison'],
            ];

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
            echo "Updating prices in Shopify\n";
            $this->graphql->update_multiple_variation_prices(
                $productModel->shopify_product_id,
                $variantUpdates
            );
        }

        Variation::where('shopify_product_id', $productModel->shopify_product_id)
            ->whereNotIn('shopify_variant_id', $variantIds)
            ->delete();

        $productModel->save();
    }

    private function createVariationFromShopify(
        array $variant,
        ProductModel $productModel,
        string $productImage,
        float $profit,
        float $discount
    ): void {
        echo "Creating new variation record\n";

        Variation::create([
            'name' => $variant['title'],
            'price' => null,
            'shopify_price' => floatval($variant['price']),
            'sync_enabled' => true,
            'sku' => $variant['sku'] ?: time(),
            'price_type' => $productModel->price_type,
            'discount' => $discount,
            'profit' => $profit,
            'commission' => '0',
            'total_price' => '0',
            'comparison_price' => '0',
            'shopify_product_id' => $productModel->shopify_product_id,
            'shopify_variant_id' => $variant['id'],
            'shopify_image' => $variant['image']['src'] ?? $productImage,
        ]);
    }

    public function createLocalRecords(array $shopifyProduct): void
    {
        try {
            $firstVariant = $shopifyProduct['variants']['edges'][0]['node'];
            $sku = $firstVariant['sku'] ?: time();
            $shopifyPrice = floatval($firstVariant['price']);

            $productImage = '';
            if (! empty($shopifyProduct['images']['edges'])) {
                $productImage = $shopifyProduct['images']['edges'][0]['node']['src'];
            }

            ProductModel::create([
                'sku' => $sku,
                'name' => $shopifyProduct['title'],
                'price' => null,
                'shopify_price' => $shopifyPrice,
                'sync_enabled' => true,
                'price_type' => (string) config('shopify.default_price_type', 'TL'),
                'discount' => '1',
                'profit' => '1',
                'commission' => '0',
                'total_price' => '0',
                'comparison_price' => '0',
                'shopify_product_id' => $shopifyProduct['id'],
                'shopify_image' => $productImage,
                'multiple_price' => 'no',
            ]);

            if ($shopifyProduct['totalVariants'] > 1) {
                foreach ($shopifyProduct['variants']['edges'] as $variantEdge) {
                    $variant = $variantEdge['node'];
                    if ($variant['title'] === 'Default Title') {
                        continue;
                    }

                    Variation::create([
                        'sku' => $variant['sku'] ?: time(),
                        'name' => $variant['title'],
                        'price' => null,
                        'shopify_price' => floatval($variant['price']),
                        'sync_enabled' => true,
                        'price_type' => (string) config('shopify.default_price_type', 'TL'),
                        'discount' => '1',
                        'profit' => '1',
                        'commission' => '0',
                        'total_price' => '0',
                        'comparison_price' => '0',
                        'shopify_product_id' => $shopifyProduct['id'],
                        'shopify_variant_id' => $variant['id'],
                        'shopify_image' => $variant['image']['src'] ?? $productImage,
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
