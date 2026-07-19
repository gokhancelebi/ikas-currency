<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('collection_id')->default('');
            $table->string('name')->default('');
            $table->string('discount')->default('0');
            $table->string('profit')->default('0');
            $table->string('commission')->default('0');

            // Index but not foreign key to match SQL structure
            $table->index('collection_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('campaigns');
    }
};
