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
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->string('lpo')->after(column: 'type')->nullable();
            $table->string('dn_number')->after(column: 'lpo')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('stock_transactions')) {
            Schema::table('stock_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('stock_transactions', 'lpo')) {
                    $table->dropColumn('lpo');
                }
                if (Schema::hasColumn('stock_transactions', 'dn_number')) {
                    $table->dropColumn('dn_number');
                }
            });
        }
    }
};
