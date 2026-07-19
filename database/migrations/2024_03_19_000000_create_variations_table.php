<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('variations', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->default('');
            $table->string('name');
            $table->decimal('price', 8, 2)->nullable();
            $table->decimal('ikas_price', 8, 2)->nullable();
            $table->string('price_type');
            $table->string('discount')->default('0');
            $table->string('profit')->default('0');
            $table->string('commission')->default('0');
            $table->string('total_price')->default('0');
            $table->string('comparison_price')->default('0');
            $table->string('ikas_product_id')->nullable();
            $table->string('ikas_variant_id')->nullable();
            $table->string('ikas_image')->nullable();
            $table->boolean('sync_enabled')->default(true);

            // Index but not foreign key to match SQL structure
            $table->index('ikas_product_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('variations');
    }
};
