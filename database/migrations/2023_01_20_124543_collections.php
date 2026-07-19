<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        # collections table with id, name, ikas_category_id
        Schema::create('collections',function (Blueprint $table){
            $table->id();
            $table->string('name');
            $table->string('active')->default('passive');
            $table->string('ikas_category_id');
            $table->string('ikas_parent_category_id')->nullable();
            $table->string('discount')->default(0);
            $table->string('profit')->default(0);
            $table->longText('product_list')->nullable();

            $table->unique('ikas_category_id');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('collections');
    }
};
