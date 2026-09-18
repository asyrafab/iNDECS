<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\CompletenessItemStatus;
use App\Http\Requests\StorePkmApplicationRequest;
use App\Http\Requests\StorePkmDocumentRequest;
use App\Http\Requests\UpdateCompletenessItemRequest;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentType;
use App\Models\PkmCompletenessItem;
use App\Services\PkmDocumentService;
use App\Services\PkmRegistrationService;
use App\Services\PkmWorkflowService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PkmApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());
        $status = ApplicationStatus::tryFrom($request->string('status')->toString());

        $applications = Application::query()
            ->pkm()
            ->with(['pkm', 'sites'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('application_reference', 'like', "%{$search}%")
                        ->orWhere('external_reference', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($status !== null, fn ($query) => $query->where('current_status', $status))
            ->latest('submission_date')
            ->paginate(15)
            ->withQueryString();

        return view('pkm.index', [
            'applications' => $applications,
            'statuses' => ApplicationStatus::cases(),
            'selectedStatus' => $status?->value,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('pkm.create', ['today' => now()->toDateString()]);
    }

    public function store(
        StorePkmApplicationRequest $request,
        PkmRegistrationService $registration,
    ): RedirectResponse {
        $application = $registration->register($request->validated());

        return redirect()
            ->route('pkm.show', $application)
            ->with('success', "Permohonan {$application->application_reference} berjaya didaftarkan.");
    }

    public function show(Application $application): View
    {
        abort_unless($application->application_type_id === 'PKM', 404);

        $application->load([
            'pkm.components',
            'applicationParties.party',
            'sites',
            'lots',
            'stageHistory',
            'assignee',
            'currentDocuments.documentType',
            'completenessReviews.items.documentType',
            'completenessReviews.openedBy',
            'completenessReviews.decidedBy',
        ]);

        $activeReview = $application->completenessReviews->first();
        $currentDocuments = $application->currentDocuments->keyBy('document_type_id');

        return view('pkm.show', compact('application', 'activeReview', 'currentDocuments'));
    }

    public function startReview(
        Request $request,
        Application $application,
        PkmWorkflowService $workflow,
    ): RedirectResponse {
        try {
            $workflow->startCompletenessReview($application, $request->user());
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Semakan kelengkapan telah dimulakan.');
    }

    public function storeDocument(
        StorePkmDocumentRequest $request,
        Application $application,
        DocumentType $documentType,
        PkmDocumentService $documents,
    ): RedirectResponse {
        try {
            $document = $documents->upload(
                $application,
                $documentType,
                $request->file('document'),
                $request->user(),
            );
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "Dokumen dimuat naik sebagai versi {$document->revision_number}.");
    }

    public function downloadDocument(
        Application $application,
        ApplicationDocument $document,
    ): StreamedResponse {
        abort_unless($application->application_type_id === 'PKM' && $document->application_id === $application->id, 404);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        return Storage::disk('local')->download($document->storage_path, $document->original_name);
    }

    public function updateCompletenessItem(
        UpdateCompletenessItemRequest $request,
        Application $application,
        PkmCompletenessItem $item,
        PkmWorkflowService $workflow,
    ): RedirectResponse {
        try {
            $workflow->reviewItem(
                $application,
                $item,
                CompletenessItemStatus::from($request->validated('status')),
                $request->validated('remarks'),
                $request->user(),
            );
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Status dokumen telah dikemas kini.');
    }

    public function completeReview(
        Request $request,
        Application $application,
        PkmWorkflowService $workflow,
    ): RedirectResponse {
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);

        try {
            $workflow->completeReview($application, $data['remarks'] ?? null, $request->user());
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Permohonan disahkan lengkap dan diteruskan ke penilaian fi.');
    }

    public function returnForAmendment(
        Request $request,
        Application $application,
        PkmWorkflowService $workflow,
    ): RedirectResponse {
        $data = $request->validate(['remarks' => ['required', 'string', 'max:2000']]);

        try {
            $workflow->returnForAmendment($application, $data['remarks'], $request->user());
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Permohonan dikembalikan untuk pindaan.');
    }

    public function resubmit(
        Request $request,
        Application $application,
        PkmWorkflowService $workflow,
    ): RedirectResponse {
        try {
            $workflow->resubmit($application, $request->user());
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pindaan dihantar semula dan kitaran semakan baharu dibuka.');
    }
}
