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
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('transaction_number')->after('id')->nullable();
            $table->enum('transaction_type', ['CS-SS', 'SS-SS', 'ENGG-SS'])->after('type')->default('CS-SS');
            $table->enum('sender_role', ['CENTRAL STORE', 'SITE STORE', 'ENGINEER'])->after('send_by')->default('CENTRAL STORE');
            $table->enum('receiver_role', ['CENTRAL STORE', 'SITE STORE', 'ENGINEER'])->after('received_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            //
        });
    }
};
