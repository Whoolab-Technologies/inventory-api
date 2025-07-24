<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('material_request_stocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('material_request_id')
                ->constrained('material_requests')
                ->onDelete('cascade');

            $table->foreignId('material_request_item_id')
                ->constrained('material_request_items')
                ->onDelete('cascade');

            $table->foreignId('purchase_request_id')
                ->constrained('purchase_requests')
                ->onDelete('cascade');

            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade');

            $table->integer('quantity');

            $table->bigInteger('created_by')->nullable();
            $table->string('created_type')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->string('updated_type')->nullable();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('material_request_stocks');
    }
};
