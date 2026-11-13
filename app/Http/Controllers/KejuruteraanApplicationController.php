<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Airs\Models\ReviewFinding;
use App\Airs\Review\FindingFeedbackService;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use App\Models\ApplicationDocument;
use App\Models\PaidCheckJourney;
use App\Services\Kejuruteraan\OfficerCheckPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Jabatan Kejuruteraan workspace for LJUP applications and their AIRS checks. */
final class KejuruteraanApplicationController extends Controller
{
    public function __construct(private readonly OfficerCheckPresenter $presenter) {}

    public function index(Request $request): View
    {
        $applications = Application::query()->visibleToLjupOfficer($request->user())
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(25);
        $journeys = PaidCheckJourney::query()->whereIn('application_id', $applications->pluck('id'))->get()->keyBy('application_id');

        return view('kejuruteraan.index', compact('applications', 'journeys'));
    }

    public function show(Request $request, string $application): View
    {
        $record = $this->application($request, $application);

        return view('kejuruteraan.show', ['application' => $record, 'view' => $this->presenter->for($record)]);
    }

    public function document(Request $request, string $application, string $document): StreamedResponse
    {
        $record = $this->application($request, $application);
        $file = ApplicationDocument::query()->where('application_id', $record->id)->findOrFail($document);

        return Storage::disk('local')->response($file->storage_path, $file->original_name, ['Content-Type' => $file->mime_type], 'inline');
    }

    public function storeFeedback(Request $request, string $application, string $finding, FindingFeedbackService $feedback): RedirectResponse
    {
        abort_unless($request->user()->canPerformLjup('review'), 403);
        $record = $this->application($request, $application);
        $found = ReviewFinding::query()->whereKey($finding)
            ->whereHas('reviewVersion.reviewCase', fn ($query) => $query->where('application_id', $record->id))->firstOrFail();
        $data = $request->validate(['officer_status' => ['required', 'string'], 'officer_comment' => ['nullable', 'string', 'max:2000']]);
        $feedback->record($request->user(), $found, $data['officer_status'], $data['officer_comment'] ?? null);

        return redirect(route('kejuruteraan.show', $record).'#finding-'.$found->id)->with('status', 'Ulasan pegawai bagi '.$found->rule_id.' disimpan.');
    }

    private function application(Request $request, string $id): Application
    {
        return Application::query()->visibleToLjupOfficer($request->user())->findOrFail($id);
    }
}
