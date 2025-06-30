<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\V1\NotificationService;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

        $this->app->singleton(NotificationService::class, function () {
            return new NotificationService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
