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
            $table->bigInteger('from_store_id')->index()->nullable(); // Store sending stock
            $table->bigInteger('to_store_id')->index();   // Store receiving stock
            $table->foreignId('status_id')->nullable()->default(1)->constrained('statuses')->nullOnDelete();
            $table->string('dn_number')->nullable();
            $table->text('remarks')->nullable();
            $table->integer('request_id')->nullable();
            $table->enum('type', ['MR', 'PR', 'SS-RETURN', "ENGG-RETURN", 'DIRECT'])->default("DIRECT")->nullable();
            $table->integer('send_by')->nullable();
            $table->integer('received_by')->nullable();
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
