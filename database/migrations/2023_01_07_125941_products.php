<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up()
    {
        # products table sku, price type, price and product name
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->default('');
            $table->string('name');
            // Maliyet — panelden girilir; sync ile otomatik doldurulmaz
            $table->decimal('price', 8, 2)->nullable();
            // Shopify mağaza satış fiyatı (sync ile güncellenir)
            $table->decimal('shopify_price', 8, 2)->nullable();
            $table->string('price_type');
            # discount percentage
            $table->string('discount')->default(0);
            # profit
            $table->string('profit')->default(0);
            # shopify commission
            $table->string('commission')->default(0);
            # total price tl
            $table->string('total_price')->default(0);
            # compare price
            $table->string('comparison_price')->default(0);

            # shopify product id
            $table->string('shopify_product_id')->nullable();

            # shopify image
            $table->string('shopify_image')->nullable();

            # multiple price flag
            $table->enum('multiple_price', allowed: ['yes', 'no'])->default('no');

            // Fiyat sync açık/kapalı
            $table->boolean('sync_enabled')->default(true);

            // Shopify mağazasında artık yok (sync ile işaretlenir, kayıt silinmez)
            $table->timestamp('shopify_deleted_at')->nullable();

            $table->timestamps();

            $table->unique('shopify_product_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
