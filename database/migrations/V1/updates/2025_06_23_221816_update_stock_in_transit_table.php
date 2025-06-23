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
        \DB::statement('DROP TABLE `stock_in_transit`');

        Schema::create('stock_in_transit', function (Blueprint $table) {
            $table->id();
            $table->integer('stock_transfer_id');
            $table->integer('stock_transfer_item_id');
            $table->integer('material_request_id')->nullable();
            $table->integer('material_request_item_id')->nullable();
            $table->integer('material_return_id')->nullable();
            $table->integer('material_return_item_id')->nullable();
            $table->integer('product_id');
            $table->integer('issued_quantity')->default(0);
            $table->integer('received_quantity')->default(0);
            $table->foreignId('status_id')->nullable()->default(10)->constrained('statuses')->nullOnDelete();
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
        Schema::table('stock-in-transit', function (Blueprint $table) {
            //
        });
    }
};
