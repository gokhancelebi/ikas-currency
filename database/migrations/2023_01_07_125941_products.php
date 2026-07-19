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
            // İkas mağaza satış fiyatı (sync ile güncellenir)
            $table->decimal('ikas_price', 8, 2)->nullable();
            $table->string('price_type');
            # discount percentage
            $table->string('discount')->default(0);
            # profit
            $table->string('profit')->default(0);
            # commission
            $table->string('commission')->default(0);
            # total price tl
            $table->string('total_price')->default(0);
            # compare price
            $table->string('comparison_price')->default(0);

            # ikas product id
            $table->string('ikas_product_id')->nullable();

            # ikas image
            $table->string('ikas_image')->nullable();

            # multiple price flag
            $table->enum('multiple_price', allowed: ['yes', 'no'])->default('no');

            // Fiyat sync açık/kapalı
            $table->boolean('sync_enabled')->default(true);

            // İkas mağazasında artık yok (sync ile işaretlenir, kayıt silinmez)
            $table->timestamp('ikas_deleted_at')->nullable();

            $table->timestamps();

            $table->unique('ikas_product_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
