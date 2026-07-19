<?php

namespace Tests\Unit;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductShopifyDeletedFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('tr');
    }

    public function test_filter_shopify_status_deleted_and_active(): void
    {
        $active = Product::create([
            'sku' => 'ACTIVE-1',
            'name' => 'Active product',
            'price_type' => 'TL',
            'shopify_product_id' => 'gid://shopify/Product/1',
        ]);

        $deleted = Product::create([
            'sku' => 'DELETED-1',
            'name' => 'Deleted product',
            'price_type' => 'TL',
            'shopify_product_id' => 'gid://shopify/Product/2',
            'shopify_deleted_at' => now(),
            'sync_enabled' => false,
        ]);

        $deletedIds = Product::query()->filterShopifyStatus('deleted')->pluck('id')->all();
        $this->assertSame([$deleted->id], $deletedIds);

        $activeIds = Product::query()->filterShopifyStatus('active')->pluck('id')->all();
        $this->assertSame([$active->id], $activeIds);

        $this->assertTrue($deleted->isDeletedFromShopify());
        $this->assertFalse($active->isDeletedFromShopify());
    }

    public function test_attention_reasons_and_needs_attention_scope(): void
    {
        $ok = Product::create([
            'sku' => 'OK-1',
            'name' => 'Complete product',
            'price' => '10.00',
            'price_type' => 'TL',
            'shopify_product_id' => 'gid://shopify/Product/10',
            'sync_enabled' => true,
        ]);

        $missingCost = Product::create([
            'sku' => 'COST-1',
            'name' => 'No cost',
            'price_type' => 'TL',
            'shopify_product_id' => 'gid://shopify/Product/11',
            'sync_enabled' => true,
        ]);

        $syncOff = Product::create([
            'sku' => 'SYNC-1',
            'name' => 'Sync off',
            'price' => '5.00',
            'price_type' => 'TL',
            'shopify_product_id' => 'gid://shopify/Product/12',
            'sync_enabled' => false,
        ]);

        $this->assertSame([], $ok->attentionReasons());
        $this->assertContains(__('products.attention.cost_missing'), $missingCost->attentionReasons());
        $this->assertContains(__('products.attention.product_sync_off'), $syncOff->attentionReasons());

        $attentionIds = Product::query()->needsAttention()->pluck('id')->sort()->values()->all();
        $this->assertSame(
            collect([$missingCost->id, $syncOff->id])->sort()->values()->all(),
            $attentionIds
        );
    }

    public function test_list_cost_label_includes_price_type(): void
    {
        $product = Product::create([
            'sku' => 'USD-1',
            'name' => 'USD product',
            'price_type' => 'USD',
            'price' => 25,
            'multiple_price' => 'no',
            'shopify_product_id' => 'gid://shopify/Product/99',
        ]);

        $this->assertSame('25.00 USD', $product->listCostLabel());
    }
}
