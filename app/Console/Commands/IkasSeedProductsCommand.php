<?php

namespace App\Console\Commands;

use App\Lib\IkasSync\AuthTokenService;
use App\Lib\IkasSync\GraphqlClient;
use App\Lib\IkasSync\IkasProductGraphQL;
use Illuminate\Console\Command;

class IkasSeedProductsCommand extends Command
{
    protected $signature = 'ikas:seed-products
                            {--simple=10 : Varyasyonsuz (basit) ürün sayısı}
                            {--variable=10 : Varyasyonlu ürün sayısı}
                            {--variants=2 : Her varyasyonlu üründeki varyant sayısı (en az 2)}
                            {--tag= : İkas ürün etiketi (varsayılan: IKAS_TEST_TAG)}';

    protected $description = 'İkas mağazasına test amaçlı basit ve varyasyonlu ürünler ekler';

    private IkasProductGraphQL $graphql;

    /** @var list<string> */
    private array $createdProductIds = [];

    public function handle(): int
    {
        if (empty(config('ikas.client_id')) || empty(config('ikas.client_secret'))) {
            $this->error('IKAS_CLIENT_ID ve IKAS_CLIENT_SECRET .env dosyasında tanımlı olmalı.');

            return Command::FAILURE;
        }

        $simpleCount = max(0, (int) $this->option('simple'));
        $variableCount = max(0, (int) $this->option('variable'));
        $variantsPerProduct = max(2, (int) $this->option('variants'));
        $tag = (string) ($this->option('tag') ?: config('ikas.test_tag', 'SYNC_TEST'));

        if ($simpleCount === 0 && $variableCount === 0) {
            $this->warn('En az bir ürün oluşturmak için --simple veya --variable değerini 1 veya üzeri verin.');

            return Command::FAILURE;
        }

        $this->graphql = new IkasProductGraphQL(new GraphqlClient(new AuthTokenService()));
        $batchId = (string) time();
        $throttle = max(0, (int) config('ikas.throttle_seconds', 1));

        $this->info("İkas seed başlıyor (batch: {$batchId}, etiket: {$tag})");
        $this->line("Basit: {$simpleCount}, Varyasyonlu: {$variableCount}, Varyant/ürün: {$variantsPerProduct}");

        $failed = 0;

        for ($i = 1; $i <= $simpleCount; $i++) {
            if (! $this->createSimpleProduct($batchId, $i, $tag)) {
                $failed++;
            }
            $this->throttle($throttle);
        }

        for ($i = 1; $i <= $variableCount; $i++) {
            if (! $this->createVariableProduct($batchId, $i, $variantsPerProduct, $tag)) {
                $failed++;
            }
            $this->throttle($throttle);
        }

        $created = count($this->createdProductIds);
        $this->newLine();
        $this->info("Tamamlandı: {$created} ürün oluşturuldu, {$failed} hata.");

        if ($created > 0) {
            $this->line('Oluşturulan ürün ID’leri:');
            foreach ($this->createdProductIds as $productId) {
                $this->line("  - {$productId}");
            }
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function createSimpleProduct(string $batchId, int $index, string $tag): bool
    {
        $sku = $this->uniqueSku("SIMPLE-{$batchId}-{$index}");
        $title = "Seed Simple {$index} ({$sku})";

        try {
            $productData = $this->simpleProductPayload($sku, $title);
            $variation = $productData['inventory_data']['variations'][0];

            $create = $this->graphql->create_simple_product($productData);
            $productId = $create['productId'] ?? null;
            $variantId = $create['variantId'] ?? null;

            if (empty($productId) || empty($variantId)) {
                $this->error("[basit {$index}] Ürün oluşturulamadı (productId/variantId boş).");

                return false;
            }

            $inv = $this->graphql->update_simple_product_inventory_data($productId, [
                'variantId' => $variantId,
                'barcode' => $variation['barcode'],
                'price' => $variation['price'],
                'compareAtPrice' => $variation['compareAtPrice'],
                'cost' => $variation['cost'],
                'sku' => $sku,
            ]);

            if ($this->hasGraphqlErrors($inv)) {
                $this->error("[basit {$index}] Envanter güncellenemedi.");

                return false;
            }

            $this->graphql->update_variation_stock($variantId, (int) $variation['inventoryQuantity'], $productId);
            $this->graphql->update_product_tags($productId, [$tag, 'SEED_SIMPLE']);
            $this->graphql->publish_product_all($productId);

            $this->createdProductIds[] = $productId;
            $this->line("[basit {$index}] OK — {$title}");

            return true;
        } catch (\Throwable $e) {
            $this->error("[basit {$index}] {$e->getMessage()}");

            return false;
        }
    }

    private function createVariableProduct(string $batchId, int $index, int $variantCount, string $tag): bool
    {
        $title = "Seed Variable {$index} ({$batchId})";

        try {
            $productData = $this->variableProductPayload($batchId, $index, $variantCount, $title);
            $create = $this->graphql->create_variable_product($productData);
            $productId = $create['productId'] ?? null;

            if (empty($productId)) {
                $this->error("[varyasyonlu {$index}] Ürün oluşturulamadı (productId boş).");

                return false;
            }

            $this->graphql->update_product_tags($productId, [$tag, 'SEED_VARIABLE']);
            $this->graphql->publish_product_all($productId);

            $this->createdProductIds[] = $productId;
            $this->line("[varyasyonlu {$index}] OK — {$title} ({$variantCount} varyant)");

            return true;
        } catch (\Throwable $e) {
            $this->error("[varyasyonlu {$index}] {$e->getMessage()}");

            return false;
        }
    }

    /** @return array<string, mixed> */
    private function simpleProductPayload(string $sku, string $title): array
    {
        return [
            'title' => $title,
            'descriptionHtml' => '<p>ikas:seed-products ile oluşturuldu</p>',
            'inventory_data' => [
                'variations' => [[
                    'barcode' => $sku,
                    'cost' => '50.00',
                    'price' => '99.90',
                    'sku' => $sku,
                    'compareAtPrice' => '129.90',
                    'options' => [],
                    'inventoryQuantity' => 5,
                ]],
                'options' => [],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function variableProductPayload(string $batchId, int $index, int $variantCount, string $title): array
    {
        $colorNames = ['Kirmizi', 'Mavi', 'Yesil', 'Sari', 'Siyah', 'Beyaz', 'Gri', 'Turuncu', 'Mor', 'Pembe'];
        $optionValues = [];
        $variations = [];

        for ($v = 1; $v <= $variantCount; $v++) {
            $color = $colorNames[($v - 1) % count($colorNames)].'-'.$v;
            $optionValues[] = ['name' => $color];
            $sku = $this->uniqueSku("VAR-{$batchId}-{$index}-{$v}");

            $variations[] = [
                'barcode' => $sku,
                'cost' => number_format(40 + $v, 2, '.', ''),
                'price' => number_format(89.90 + $v, 2, '.', ''),
                'sku' => $sku,
                'compareAtPrice' => number_format(109.90 + $v, 2, '.', ''),
                'inventoryQuantity' => 3 + $v,
                'options' => [
                    ['name' => 'Renk', 'optionName' => $color],
                ],
            ];
        }

        return [
            'title' => $title,
            'descriptionHtml' => '<p>ikas:seed-products ile oluşturuldu</p>',
            'inventory_data' => [
                'options' => [
                    ['name' => 'Renk', 'values' => $optionValues],
                ],
                'variations' => $variations,
            ],
        ];
    }

    private function uniqueSku(string $suffix): string
    {
        return 'SEED-'.$suffix;
    }

    private function hasGraphqlErrors(?array $response): bool
    {
        return ! empty($response['errors'] ?? [])
            || empty($response['data']['updateVariantPrices']['isSuccess'] ?? true);
    }

    private function throttle(int $seconds): void
    {
        if ($seconds > 0) {
            sleep($seconds);
        }
    }
}
