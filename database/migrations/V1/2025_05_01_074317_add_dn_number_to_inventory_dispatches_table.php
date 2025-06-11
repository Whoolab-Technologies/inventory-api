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
        Schema::table('inventory_dispatches', function (Blueprint $table) {
            $table->text('dn_number')->after(column: 'dispatch_number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('inventory_dispatches', function (Blueprint $table) {
            $table->dropColumn('dn_number');
        });
    }
};
