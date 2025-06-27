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
        // \DB::statement('ALTER TABLE `stock_transactions` DROP `type`');
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->enum('type', ['DIRECT', 'MR', 'PR', 'SS-RETURN', 'ENGG-RETURN'])->default(value: 'DIRECT')->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->dropColumn(['type']);

        });
    }
};
