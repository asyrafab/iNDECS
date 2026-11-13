<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApplicantDraft;
use App\Models\CheckPayment;
use App\Services\PaidCheck\CheckPaymentService;
use App\Services\PaidCheck\StagingPaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PortalCheckPaymentController extends Controller
{
    public function __construct(private readonly CheckPaymentService $payments) {}

    public function start(Request $request, string $draft): RedirectResponse
    {
        $owned = ApplicantDraft::query()->ownedBy($request->user())->findOrFail($draft);
        $payment = $this->payments->start($request->user(), $owned, $request->boolean('acknowledge_missing'));

        return redirect()->away($this->payments->checkoutUrl($payment));
    }

    public function staging(Request $request, string $payment): View
    {
        $owned = $this->stagingPayment($request, $payment);

        return view('public.payments.staging', ['payment' => $owned, 'draft' => $owned->draft]);
    }

    public function confirmStaging(Request $request, string $payment): RedirectResponse
    {
        $owned = $this->stagingPayment($request, $payment);
        $this->payments->confirm($owned->reference, 'staging-'.$owned->id, CarbonImmutable::now());

        return to_route('portal.drafts.edit', $owned->draft_id)->with('status', 'Bayaran ujian disahkan. Semakan AIRS sedang dijalankan.');
    }

    private function stagingPayment(Request $request, string $id): CheckPayment
    {
        abort_unless(StagingPaymentGateway::available(), 404);

        return CheckPayment::query()->where('user_id', $request->user()->id)->where('gateway', 'staging')->findOrFail($id);
    }
}
