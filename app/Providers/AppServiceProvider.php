<?php

namespace App\Providers;

use App\Airs\Events\AirsCheckCompleted;
use App\Contracts\DocumentScanner;
use App\Contracts\Identity\MyDigitalIdClient;
use App\Contracts\StaffAuthenticator;
use App\Contracts\TextExtractor;
use App\Events\CheckPaymentConfirmed;
use App\Models\CheckPayment;
use App\Services\ClamAvDocumentScanner;
use App\Services\Identity\MyDigitalIdOidcClient;
use App\Services\MbspStaffAuthenticator;
use App\Services\PaidCheck\PaidCheckJourneyService;
use App\Services\PaidCheck\PaymentGateway;
use App\Services\PaidCheck\StagingPaymentGateway;
use App\Services\Precheck\LocalTextExtractor;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MyDigitalIdClient::class, MyDigitalIdOidcClient::class);
        $this->app->bind(StaffAuthenticator::class, MbspStaffAuthenticator::class);
        $this->app->bind(DocumentScanner::class, ClamAvDocumentScanner::class);
        $this->app->bind(TextExtractor::class, LocalTextExtractor::class);
        // Only the staging driver exists until the MBSP payment gateway is integrated; it is refused in production.
        $this->app->bind(PaymentGateway::class, fn () => match ((string) config('airs.payment_driver')) {
            'staging' => new StagingPaymentGateway,
            default => throw new \RuntimeException('Unsupported AIRS_PAYMENT_DRIVER.'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Paid AIRS check journey (synchronous listeners keep state changes in order).
        Event::listen(CheckPaymentConfirmed::class, fn ($event) => app(PaidCheckJourneyService::class)
            ->paid(CheckPayment::query()->findOrFail($event->paymentId)));
        Event::listen(AirsCheckCompleted::class, fn ($event) => app(PaidCheckJourneyService::class)
            ->checkCompleted($event));
    }
}
