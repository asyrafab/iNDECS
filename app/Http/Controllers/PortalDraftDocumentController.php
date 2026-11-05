<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApplicantDraft;
use App\Models\ApplicantDraftDocument;
use App\Services\Precheck\DraftChecklist;
use App\Services\Precheck\DraftDocumentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function confirm(Request $request, string $draft, string $document): RedirectResponse
    {
        $owned = $this->draft($request, $draft);
        $this->documents->confirm($request->user(), $this->document($owned, $document));

        return redirect()->to(route('portal.drafts.edit', $owned).'#dokumen')->with('status', 'Pengesahan anda direkodkan.');
    }

    public function download(Request $request, string $draft, string $document): StreamedResponse
    {
        $record = $this->document($this->draft($request, $draft), $document);
        abort_unless(Storage::disk('local')->exists($record->path), 404);

        return Storage::disk('local')->download($record->path, $record->original_name, [
            'Content-Type' => $record->mime, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Rendered checklist fragment for polling while checks run. */
    public function checklist(Request $request, string $draft): View
    {
        $owned = $this->draft($request, $draft);

        return view('public.drafts.partials.checklist', ['draft' => $owned, 'checklist' => $this->checklist->for($owned)]);
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
