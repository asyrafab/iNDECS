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
        $this->app->bind(\App\Contracts\Identity\MyDigitalIdClient::class, \App\Services\Identity\MyDigitalIdOidcClient::class);
        $this->app->bind(StaffAuthenticator::class, MbspStaffAuthenticator::class);
        $this->app->bind(DocumentScanner::class, ClamAvDocumentScanner::class);
        $this->app->bind(\App\Contracts\TextExtractor::class, \App\Services\Precheck\LocalTextExtractor::class);
        // Only the staging driver exists until the MBSP payment gateway is integrated; it is refused in production.
        $this->app->bind(\App\Services\PaidCheck\PaymentGateway::class, fn () => match ((string) config('airs.payment_driver')) {
            'staging' => new \App\Services\PaidCheck\StagingPaymentGateway,
            default => throw new \RuntimeException('Unsupported AIRS_PAYMENT_DRIVER.'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Paid AIRS check journey (synchronous listeners keep state changes in order).
        \Illuminate\Support\Facades\Event::listen(\App\Events\CheckPaymentConfirmed::class, fn ($event) => app(\App\Services\PaidCheck\PaidCheckJourneyService::class)
            ->paid(\App\Models\CheckPayment::query()->findOrFail($event->paymentId)));
        \Illuminate\Support\Facades\Event::listen(\App\Airs\Events\AirsCheckCompleted::class, fn ($event) => app(\App\Services\PaidCheck\PaidCheckJourneyService::class)
            ->checkCompleted($event));
    }
}
