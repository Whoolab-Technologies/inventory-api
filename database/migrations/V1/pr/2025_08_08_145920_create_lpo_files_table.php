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


        Schema::create('lpo_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lpo_id');
            $table->unsignedBigInteger('lpo_shipment_id')->nullable();
            $table->string('file');
            $table->string('file_mime_type');
            $table->bigInteger('created_by')->nullable();
            $table->string('created_type')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->string('updated_type')->nullable();
            $table->timestamps();
            $table->foreign('lpo_id')
                ->references('id')
                ->on('lpos')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreign('lpo_shipment_id')
                ->references('id')
                ->on('lpo_shipments')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lpo_files');
    }
};
