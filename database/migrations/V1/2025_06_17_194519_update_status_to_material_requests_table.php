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
        DB::statement("ALTER TABLE `material_requests` DROP `status`");
        Schema::table('material_requests', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'awaiting_procurement', 'rejected', 'completed'])->default('pending')->after("store_id");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('material_requests', function (Blueprint $table) {
            //
        });
    }
};
