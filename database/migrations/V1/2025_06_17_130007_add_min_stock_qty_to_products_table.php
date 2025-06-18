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
        Schema::table('products', function (Blueprint $table) {
            $table->integer('min_stock_qty')->after('brand_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {

                if (Schema::hasColumn('products', 'min_stock_qty')) {
                    $table->dropColumn('min_stock_qty');
                }
            });
        }

    }
};
