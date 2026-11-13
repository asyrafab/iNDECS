<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApplicantDraft;
use App\Models\ApplicationDocument;
use App\Models\PaidCheckJourney;
use App\Services\PaidCheck\ApplicantCheckPresenter;
use App\Services\PaidCheck\PaidCheckJourneyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The applicant's own AIRS check results and evidence documents. Owner only; everything else 404s. */
final class PortalCheckResultController extends Controller
{
    public function __construct(private readonly ApplicantCheckPresenter $presenter) {}

    public function show(Request $request, string $draft): View
    {
        $journey = $this->journey($request, $draft);

        return view('public.drafts.partials.check-result', ['draft' => $journey->draft, 'result' => $this->presenter->for($journey)]);
    }

    public function document(Request $request, string $draft, string $document): StreamedResponse
    {
        $journey = $this->journey($request, $draft);
        $file = ApplicationDocument::query()->where('application_id', $journey->application_id)->findOrFail($document);

        return Storage::disk('local')->response($file->storage_path, $file->original_name, ['Content-Type' => $file->mime_type], 'inline');
    }

    public function extend(Request $request, string $draft, PaidCheckJourneyService $journeys): RedirectResponse
    {
        $journeys->extend($request->user(), $this->journey($request, $draft));

        return to_route('portal.drafts.edit', $draft)->with('status', 'Tempoh pindaan dilanjutkan 5 hari bekerja.');
    }

    public function amend(Request $request, string $draft, PaidCheckJourneyService $journeys): RedirectResponse
    {
        $journeys->submitAmendment($request->user(), $this->journey($request, $draft));

        return to_route('portal.drafts.edit', $draft)->with('status', 'Pindaan dihantar. Semakan semula AIRS sedang dijalankan.');
    }

    public function submit(Request $request, string $draft, PaidCheckJourneyService $journeys): RedirectResponse
    {
        $journeys->submitAsIs($request->user(), $this->journey($request, $draft));

        return to_route('portal.drafts.edit', $draft)->with('status', 'Permohonan dihantar kepada Jabatan Kejuruteraan.');
    }

    private function journey(Request $request, string $draft): PaidCheckJourney
    {
        $owned = ApplicantDraft::query()->ownedBy($request->user())->findOrFail($draft);

        return PaidCheckJourney::query()->where('draft_id', $owned->id)->firstOrFail();
    }
}
