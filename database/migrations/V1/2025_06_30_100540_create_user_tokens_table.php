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
        Schema::create('user_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('user_role');
            $table->string('fcm_token');
            $table->string('device_model')->nullable();
            $table->string('device_brand')->nullable();
            $table->string('os_version')->nullable();
            $table->string('platform')->nullable();
            $table->string('device_id')->nullable();
            $table->string('sdk')->nullable();

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
        Schema::table('user_tokens', callback: function (Blueprint $table) {
            Schema::dropIfExists('user_tokens');
        });
    }
};
