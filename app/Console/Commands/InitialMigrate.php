<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\SuppliersTableSeeder;
class InitialMigrate extends Command
{
    protected $signature = 'migrate:initial';
    protected $description = 'Migrate all databases, including subdirectories';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Migrate the default migrations (in case you want to migrate the main folder too)
        // $this->call('migrate');

        // Seed the statuses table
        //  $this->call(SuppliersTableSeeder::class);
        // $this->call(
        //     PurchaseRequestSeeder::class,
        // );
        // $this->call(PurchaseRequestItemSeeder::class);
        // $this->call(LpoSeeder::class, );
        $this->call(SuppliersTableSeeder::class);


        // Then run the rest of the migrations in V1 folder
        // $this->call('migrate', [
        //     '--path' => 'database/migrations/V1'
        // ]);
    }
}
