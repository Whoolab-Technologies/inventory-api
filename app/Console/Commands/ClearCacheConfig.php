<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearCacheConfig extends Command
{
    protected $signature = 'clear:all';
    protected $description = 'Clear cache and config';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->call('route:clear');
        $this->call('cache:clear');
        $this->call('config:clear');
        $this->info('Cache, config, and route cache cleared successfully.');
    }
}
