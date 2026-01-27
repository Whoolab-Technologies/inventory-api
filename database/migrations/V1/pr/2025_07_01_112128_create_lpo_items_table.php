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
        Schema::create('lpo_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lpo_id');
            $table->unsignedBigInteger('pr_item_id');
            $table->unsignedBigInteger('product_id');

            $table->integer('requested_quantity')->default(0);
            $table->integer('received_quantity')->default(0);
            $table->bigInteger('created_by')->nullable();
            $table->string('created_type')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->string('updated_type')->nullable();
            $table->timestamps();

            $table->foreign('lpo_id')->references('id')->on('lpos')->onDelete('cascade');
            $table->foreign('pr_item_id')->references('id')->on('purchase_request_items')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lpo_items');
    }
};
