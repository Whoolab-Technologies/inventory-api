<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RollbackAll extends Command
{
    protected $signature = 'migrate:rollback-all';
    protected $description = 'Rollback all databases, including subdirectories';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Rollback the default migrations (in case you want to rollback the main folder too)
        $this->call('migrate:rollback');

        // Rollback migrations in v1 directory
        $this->call('migrate:rollback', ['--path' => 'database/migrations/V1']);
        $this->call('migrate:rollback', ['--path' => 'database/migrations/V1/pr']);
        $this->call('migrate:rollback', ['--path' => 'database/migrations/V1/demo-updates']);

        // Rollback migrations in v2 directory
        // $this->call('migrate:rollback', ['--path' => 'database/migrations/v2']);
    }
}
