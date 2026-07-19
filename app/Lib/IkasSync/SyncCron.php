<?php

namespace App\Lib\IkasSync;

use App\Models\Collection as CollectionModel;
use App\Models\Product as ProductModel;
use Illuminate\Support\Facades\Log;

class SyncCron
{
    public function __construct(
        private SyncStatus $status,
        private RateService $rates,
        private RatesFailureNotifier $ratesNotifier,
        private IkasProductGraphQL $graphql,
        private CategorySyncService $categories,
        private ProductSyncService $products
    ) {
    }

    public function run(): bool
    {
        set_time_limit(0);

        $this->status->assertNotRunning();

        if (! $this->ensureRatesReady()) {
            $this->status->update('done');

            return false;
        }

        $start = time();

        echo 'Fetching products...'.PHP_EOL;
        $allProducts = $this->graphql->allProducts();

        if (empty($allProducts)) {
            echo 'Syncing categories...'.PHP_EOL;
            $this->categories->sync([]);
            $this->status->update('done');

            return true;
        }

        echo 'Syncing categories...'.PHP_EOL;
        $this->categories->sync($allProducts);

        $productsInDatabase = ProductModel::pluck('ikas_product_id')->toArray();
        $productsInIkas = array_column($allProducts, 'id');

        $index = 1;
        $count = count($allProducts);
        $dbCollections = CollectionModel::all();

        echo "Processing {$count} products...".PHP_EOL;

        foreach ($allProducts as $product) {
            echo "Product {$index}/{$count}: ".($product['name'] ?? $product['id']).PHP_EOL;
            $this->status->setProgress($index, $count);
            $this->status->update('running');

            if (! IkasProductGraphQL::isProductActive($product)) {
                $index++;

                continue;
            }

            $productModel = ProductModel::where('ikas_product_id', $product['id'])->first();

            if ($productModel) {
                $this->products->updateExisting($productModel, $product, $dbCollections);
                $index++;

                continue;
            }

            $this->products->createLocalRecords($product);
            $index++;
        }

        $productsToDelete = array_values(array_diff($productsInDatabase, $productsInIkas));

        if ($productsToDelete !== []) {
            Log::channel('delete-products')->info('Products removed from İkas: '.json_encode($productsToDelete));

            ProductModel::query()
                ->whereIn('ikas_product_id', $productsToDelete)
                ->whereNull('ikas_deleted_at')
                ->update([
                    'ikas_deleted_at' => now(),
                    'sync_enabled' => false,
                ]);
        }

        ProductModel::query()
            ->whereIn('ikas_product_id', $productsInIkas)
            ->whereNotNull('ikas_deleted_at')
            ->update(['ikas_deleted_at' => null]);

        $this->status->update('done');
        $this->status->writeLastUpdate($start, time());

        echo 'Sync completed.'.PHP_EOL;

        return true;
    }

    private function ensureRatesReady(): bool
    {
        try {
            $this->rates->updateRates();
        } catch (\Throwable $e) {
            $this->ratesNotifier->notify(__('rates.update_failed', ['error' => $e->getMessage()]));

            return false;
        }

        $this->rates->getRates();

        $ratesStatus = $this->rates->inspectRatesForUi();
        if ($ratesStatus['ready']) {
            return true;
        }

        $this->ratesNotifier->notify($ratesStatus['message']);

        return false;
    }
}
