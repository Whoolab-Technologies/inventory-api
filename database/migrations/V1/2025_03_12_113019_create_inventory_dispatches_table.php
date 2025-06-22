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
        Schema::create('inventory_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_number');
            $table->text('dn_number');
            $table->integer('engineer_id');
            $table->integer('store_id');
            $table->boolean('self')->default(false);
            $table->string('representative')->nullable();
            $table->timestamp('picked_at');
            $table->bigInteger('created_by')->nullable();
            $table->string('created_type')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->string('updated_type')->nullable();
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
        Schema::dropIfExists('inventory_dispatches');
    }
};
