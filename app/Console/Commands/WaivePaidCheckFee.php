<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ApplicantDraft;
use App\Models\User;
use App\Services\PaidCheck\CheckPaymentService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/** Audited fee waiver for a draft's paid AIRS check (until a waiver-request flow is designed). */
final class WaivePaidCheckFee extends Command
{
    protected $signature = 'paid-check:waive {draft : Draft ID} {--actor= : E-mail of the authorised officer} {--reason= : Reason with the approved policy reference}';

    protected $description = 'Waive the paid AIRS check fee for a draft (audited)';

    public function handle(CheckPaymentService $payments): int
    {
        $officer = User::query()->where('email', (string) $this->option('actor'))->first();
        $draft = ApplicantDraft::query()->find((string) $this->argument('draft'));
        if ($officer === null || $draft === null) {
            $this->error('Pegawai atau draf tidak dijumpai.');

            return self::FAILURE;
        }
        try {
            $payment = $payments->waive($officer, $draft, (string) $this->option('reason'));
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }
        $this->info("Fi dikecualikan: {$payment->reference}");

        return self::SUCCESS;
    }
}
