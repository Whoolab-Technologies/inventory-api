<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateAll extends Command
{
    protected $signature = 'migrate:all';
    protected $description = 'Migrate all databases, including subdirectories';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Migrate the default migrations (in case you want to migrate the main folder too)
        $this->call('migrate');

        // Migrate migrations in v1 directory
        $this->call('migrate', ['--path' => 'database/migrations/V1']);

        // $this->call('migrate', ['--path' => 'database/migrations/V1/updates']);

        // Migrate migrations in v2 directory
        //  $this->call('migrate', ['--path' => 'database/migrations/v2']);
    }
}
