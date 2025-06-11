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
        Schema::table('material_returns', function (Blueprint $table) {
            $table->string('dn_number')->after(column: 'status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('material_returns')) {
            Schema::table('material_returns', function (Blueprint $table) {

                if (Schema::hasColumn('material_returns', 'dn_number')) {
                    $table->dropColumn('dn_number');
                }
            });
        }

    }
};
