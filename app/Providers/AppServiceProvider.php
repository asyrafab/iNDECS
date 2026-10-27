<?php

namespace App\Providers;

use App\Contracts\StaffAuthenticator;
use App\Services\MbspStaffAuthenticator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(StaffAuthenticator::class, MbspStaffAuthenticator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
