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
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('engineer_id')->constrained('engineers')->onDelete('cascade');
            $table->integer('quantity')->default(0);
            $table->enum('stock_movement', ['IN', 'TRANSIT', 'OUT']);
            $table->enum('type', ['DIRECT', 'MR', 'PR', 'SS-REUTRN', 'ENG-RETURN', 'DISPATCH'])->default(value: 'DIRECT');
            $table->string('lpo')->nullable();
            $table->string('dn_number')->nullable();
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
        Schema::dropIfExists('stock_transactions');
    }
};
