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
        DB::statement("ALTER TABLE `stock_transactions` DROP `stock_movement`");
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->enum('stock_movement', ['IN', 'TRANSIT', 'OUT'])->after("quantity");
            $table->enum('type', ['STOCK', 'TRANSFER', 'CONSUMPTION', "RETURN"])
                ->default('TRANSFER')->after("stock_movement");
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stock_transactions', function ($table) {
            $table->dropColumn('type');
        });
    }
};
