<?php

namespace Database\Seeders;


use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class UnitsTableSeeder extends Seeder
{
    public function run()
    {
        // Disable foreign key checks to avoid constraints issues
        Schema::disableForeignKeyConstraints();
        DB::table('units')->truncate(); // Clear the table before seeding
        Schema::enableForeignKeyConstraints();

        // Insert unit data
        DB::table('units')->insert([
            ['name' => 'Meters', 'symbol' => 'm', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Centimeters', 'symbol' => 'cm', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Piece', 'symbol' => 'pc', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Kilograms', 'symbol' => 'kg', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Grams', 'symbol' => 'g', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Liters', 'symbol' => 'L', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Milliliters', 'symbol' => 'mL', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Inches', 'symbol' => 'in', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Feet', 'symbol' => 'ft', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Yards', 'symbol' => 'yd', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]);
    }
}
