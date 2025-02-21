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
            ['name' => 'Meters', 'short_code' => 'm', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Centimeters', 'short_code' => 'cm', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Piece', 'short_code' => 'pc', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Kilograms', 'short_code' => 'kg', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Grams', 'short_code' => 'g', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Liters', 'short_code' => 'L', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Milliliters', 'short_code' => 'mL', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Inches', 'short_code' => 'in', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Feet', 'short_code' => 'ft', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Yards', 'short_code' => 'yd', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ]);
    }
}
