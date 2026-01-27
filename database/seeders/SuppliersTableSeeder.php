<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SuppliersTableSeeder extends Seeder
{
    public function run()
    {
        $suppliers = [
            [
                'name' => 'Acme Supplies',
                'email' => 'contact@acmesupplies.com',
                'contact' => 'John Doe',
                'address' => '123 Main St, Springfield',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 1,
                'updated_by' => 1,
                'created_type' => 'admin',
                'updated_type' => 'admin',
            ],
            [
                'name' => 'Global Industrial',
                'email' => 'info@globalindustrial.com',
                'contact' => 'Jane Smith',
                'address' => '456 Industrial Ave, Metropolis',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 1,
                'updated_by' => 1,
                'created_type' => 'admin',
                'updated_type' => 'admin',
            ],
            [
                'name' => 'Supply Depot',
                'email' => 'support@supplydepot.com',
                'contact' => 'Mike Johnson',
                'address' => '789 Depot Rd, Gotham',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 1,
                'updated_by' => 1,
                'created_type' => 'admin',
                'updated_type' => 'admin',
            ],
            [
                'name' => 'Office Essentials',
                'email' => 'sales@officeessentials.com',
                'contact' => 'Sara Lee',
                'address' => '321 Office Park, Star City',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 1,
                'updated_by' => 1,
                'created_type' => 'admin',
                'updated_type' => 'admin',
            ],
            [
                'name' => 'Warehouse Direct',
                'email' => 'hello@warehousedirect.com',
                'contact' => 'Tom Clark',
                'address' => '654 Warehouse Blvd, Central City',
                'created_at' => now(),
                'updated_at' => now(),
                'created_by' => 1,
                'updated_by' => 1,
                'created_type' => 'admin',
                'updated_type' => 'admin',
            ],
            // Add more suppliers as needed...
        ];

        DB::table('suppliers')->insert($suppliers);
    }
}
