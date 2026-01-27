<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {

    public function up(): void
    {
        Schema::dropIfExists('lpo_shipments');
        Schema::create('lpo_shipments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lpo_id');
            $table->string('dn_number')->unique();
            $table->date('date');
            $table->foreignId('status_id')->nullable()->default(10)->constrained('statuses')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->string('created_type')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->string('updated_type')->nullable();
            $table->timestamps();
            $table->foreign('lpo_id')->references('id')->on('lpos')->onDelete('cascade');
        });

        // lpo_shipment_items table
        Schema::create('lpo_shipment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lpo_shipment_id');
            $table->unsignedBigInteger('lpo_item_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity_delivered');
            $table->bigInteger('created_by')->nullable();
            $table->string('created_type')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->string('updated_type')->nullable();
            $table->timestamps();

            $table->foreign('lpo_shipment_id')->references('id')->on('lpo_shipments')->onDelete('cascade');
            $table->foreign('lpo_item_id')->references('id')->on('lpo_items')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lpo_shipment_items');
        Schema::dropIfExists('lpo_shipments');
    }
};