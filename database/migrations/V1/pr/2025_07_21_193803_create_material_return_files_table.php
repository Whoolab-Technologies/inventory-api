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
        Schema::create('material_return_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_return_id');
            $table->string('file');
            $table->string('file_mime_type');
            $table->string('transaction_type');
            $table->bigInteger('created_by')->nullable();
            $table->string('created_type')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->string('updated_type')->nullable();
            $table->timestamps();
            $table->foreign('material_return_id')
                ->references('id')
                ->on('material_returns')
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
        Schema::dropIfExists('material_return_files');
    }
};
