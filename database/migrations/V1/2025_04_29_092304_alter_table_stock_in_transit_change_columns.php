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

        DB::statement('ALTER TABLE stock_in_transit MODIFY stock_transfer_id INT NULL');
        DB::statement('ALTER TABLE stock_in_transit MODIFY material_request_id INT NULL');
        DB::statement('ALTER TABLE stock_in_transit MODIFY stock_transfer_item_id INT NULL');

        // Add new columns
        Schema::table('stock_in_transit', function (Blueprint $table) {
            $table->integer('material_return_id')->after('stock_transfer_item_id')->nullable();
            $table->integer('material_return_item_id')->after('material_return_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // DB::statement('ALTER TABLE stock_in_transit MODIFY stock_transfer_id INT NOT NULL');
        // DB::statement('ALTER TABLE stock_in_transit MODIFY material_request_id INT NOT NULL');
        // DB::statement('ALTER TABLE stock_in_transit MODIFY stock_transfer_item_id INT NOT NULL');

        // Drop newly added columns
        Schema::table('stock_in_transit', function (Blueprint $table) {
            $table->dropColumn(['material_return_id', 'material_return_item_id']);
        });
    }
};
