<?php

namespace App\Providers;

use App\Contracts\DocumentScanner;
use App\Contracts\StaffAuthenticator;
use App\Services\ClamAvDocumentScanner;
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
        $this->app->bind(DocumentScanner::class, ClamAvDocumentScanner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
