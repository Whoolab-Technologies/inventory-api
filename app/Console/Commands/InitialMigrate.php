<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\StatusesTableSeeder;
class InitialMigrate extends Command
{
    protected $signature = 'migrate:inital';
    protected $description = 'Migrate all databases, including subdirectories';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Migrate the default migrations (in case you want to migrate the main folder too)
        $this->call('migrate', [
            '--path' => 'database/migrations/2025_06_21_214912_create_statuses_table.php'
        ]);

        // Seed the statuses table
        $this->call(StatusesTableSeeder::class);

        // Then run the rest of the migrations in V1 folder
        $this->call('migrate', [
            '--path' => 'database/migrations/V1'
        ]);
    }
}
