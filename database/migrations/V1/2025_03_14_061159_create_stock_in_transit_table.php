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
        Schema::create('stock_in_transit', function (Blueprint $table) {
            $table->id();
            $table->integer('stock_transfer_id');
            $table->integer('material_request_id');
            $table->integer('stock_transfer_item_id');
            $table->integer('product_id');
            $table->integer('issued_quantity')->default(0);
            $table->integer('received_quantity')->default(0);
            $table->enum('status', ['in_transit', 'received', 'partial_received'])->default('in_transit');
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
        Schema::dropIfExists('stock_in_transit');
    }
};
