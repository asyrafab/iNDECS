<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Models\ApplicationType;
use App\Services\PaidCheck\PaidCheckJourneyService;
use App\Services\Precheck\DraftChecklist;
use App\Services\Precheck\DraftDocumentService;
use App\Services\Precheck\DraftDropService;
use App\Services\Precheck\DropResult;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** ADR-363: the owner's draft uploads and free pre-check. Foreign drafts and documents are 404. */
final class PortalDraftDocumentController extends Controller
{
    public function __construct(private readonly DraftDocumentService $documents, private readonly DraftChecklist $checklist) {}

    public function store(Request $request, string $draft, string $code): RedirectResponse
    {
        $owned = $this->draft($request, $draft);
        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => 'Pilih fail dokumen untuk dimuat naik.']);
        }
        $this->documents->upload($request->user(), $owned, $code, $file);

        return redirect()->to(route('portal.drafts.edit', $owned).'#dokumen')->with('status', 'Dokumen diterima dan sedang disemak.');
    }

    /** Upload-first: a new draft from dropped files (the first batch); later batches go to add(). */
    public function drop(Request $request, DraftDropService $drops): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            // Only types with a published checklist: an unsorted file must always have a tray to show in.
            'application_type_id' => ['required', Rule::in(ApplicationType::query()->portal()->pluck('id')->all()),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! DB::table('core.document_catalogues')->where('application_type_id', $value)->exists()) {
                        $fail('Senarai semak dokumen belum tersedia untuk jenis permohonan ini. Isi butiran draf dahulu.');
                    }
                }],
            'files' => ['required', 'array', 'min:1', 'max:10'], 'files.*' => ['file'],
        ], ['files.required' => 'Pilih sekurang-kurangnya satu fail.'],
            ['application_type_id' => 'jenis permohonan', 'files' => 'fail']);
        $draft = $drops->createDraft($request->user(), $data['application_type_id']);

        return $this->dropped($request, $draft, $this->acceptAll($request, $drops, $draft));
    }

    public function add(Request $request, DraftDropService $drops, string $draft): JsonResponse|RedirectResponse
    {
        $owned = $this->draft($request, $draft);
        $request->validate(['files' => ['required', 'array', 'min:1', 'max:10'], 'files.*' => ['file']],
            ['files.required' => 'Pilih sekurang-kurangnya satu fail.'], ['files' => 'fail']);

        return $this->dropped($request, $owned, $this->acceptAll($request, $drops, $owned));
    }

    public function assign(Request $request, string $draft, string $document): RedirectResponse
    {
        $owned = $this->draft($request, $draft);
        $data = $request->validate(['item_code' => ['required', 'string', 'max:40']], [], ['item_code' => 'dokumen senarai semak']);
        $this->documents->assign($request->user(), $this->document($owned, $document), $data['item_code']);

        return redirect()->to(route('portal.drafts.edit', $owned).'#dokumen')->with('status', 'Fail diletakkan dalam senarai semak dan sedang disemak.');
    }

    public function remove(Request $request, string $draft, string $document): RedirectResponse
    {
        $owned = $this->draft($request, $draft);
        $this->documents->remove($request->user(), $this->document($owned, $document));

        return redirect()->to(route('portal.drafts.edit', $owned).'#dokumen')->with('status', 'Fail dibuang daripada draf.');
    }

    private function acceptAll(Request $request, DraftDropService $drops, ApplicantDraft $draft): DropResult
    {
        $result = new DropResult;
        foreach ((array) $request->file('files', []) as $file) {
            if ($file instanceof UploadedFile) {
                $result = $result->merge($drops->accept($request->user(), $draft, $file));
            }
        }

        return $result;
    }

    private function dropped(Request $request, ApplicantDraft $draft, DropResult $result): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['draft_url' => route('portal.drafts.edit', $draft).'#dokumen', 'add_url' => route('portal.drafts.documents.add', $draft),
                'accepted' => array_map(fn (ApplicantDraftDocument $document) => $document->original_name, $result->accepted), 'rejected' => $result->rejected]);
        }

        return redirect()->to(route('portal.drafts.edit', $draft).'#dokumen')
            ->with('status', count($result->accepted).' fail diterima dan sedang dipadankan secara automatik.')
            ->with('drop_rejected', $result->rejected);
    }

    public function confirm(Request $request, string $draft, string $document): RedirectResponse
    {
        $owned = $this->draft($request, $draft);
        $this->documents->confirm($request->user(), $this->document($owned, $document));

        return redirect()->to(route('portal.drafts.edit', $owned).'#dokumen')->with('status', 'Pengesahan anda direkodkan.');
    }

    public function download(Request $request, string $draft, string $document): StreamedResponse
    {
        $record = $this->document($this->draft($request, $draft), $document);
        // An unscanned or refused drop is never served back.
        abort_if($record->scan_pending || $record->rejected_reason !== null, 404);
        abort_unless(Storage::disk('local')->exists($record->path), 404);

        return Storage::disk('local')->download($record->path, $record->original_name, [
            'Content-Type' => $record->mime, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Rendered checklist fragment for polling while checks run. */
    public function checklist(Request $request, string $draft): View
    {
        $owned = $this->draft($request, $draft);

        return view('public.drafts.partials.checklist', ['draft' => $owned, 'checklist' => $this->checklist->for($owned),
            'editable' => PaidCheckJourneyService::draftEditable($owned),
            'checkResult' => $owned->journey()->exists() ? true : null]);
    }

    private function draft(Request $request, string $id): ApplicantDraft
    {
        return ApplicantDraft::query()->ownedBy($request->user())->findOrFail($id);
    }

    private function document(ApplicantDraft $draft, string $id): ApplicantDraftDocument
    {
        return ApplicantDraftDocument::query()->where('draft_id', $draft->id)->findOrFail($id);
    }
}
