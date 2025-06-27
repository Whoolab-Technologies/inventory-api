<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number');
            $table->bigInteger('from_store_id')->index()->nullable(); // Store sending stock
            $table->bigInteger('to_store_id')->index();   // Store receiving stock
            $table->foreignId('status_id')->nullable()->default(10)->constrained('statuses')->nullOnDelete();
            $table->string('dn_number')->nullable();
            $table->text('remarks')->nullable();
            $table->integer('request_id')->nullable();
            $table->enum('request_type', ['MR', 'PR', 'SS-RETURN', "ENGG-RETURN", 'DIRECT', 'DISPATCH'])->default("DIRECT")->nullable();
            $table->enum('transaction_type', ['CS-SS', 'SS-CS', 'ENGG-SS', 'SS-ENGG', 'DIRECT'])->default('DIRECT');

            $table->integer('send_by')->nullable();
            $table->enum('sender_role', ['CENTRAL STORE', 'SITE STORE', 'ENGINEER'])->default('CENTRAL STORE');

            $table->integer('received_by')->nullable();
            $table->enum('receiver_role', ['CENTRAL STORE', 'SITE STORE', 'ENGINEER'])->nullable();

            $table->text('note')->nullable();
            $table->bigInteger('created_by')->nullable();
            $table->string('created_type')->nullable();
            $table->bigInteger('updated_by')->nullable();
            $table->string('updated_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
