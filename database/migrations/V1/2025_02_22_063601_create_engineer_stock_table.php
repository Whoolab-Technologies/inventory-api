<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('engineer_stock', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('engineer_id')->index();
            $table->bigInteger('store_id')->index();
            $table->bigInteger('product_id')->index();
            $table->integer('quantity')->default(0); // Stock assigned to engineer
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('engineer_stock');
    }
};
