<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\BangunanApplication;
use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistItem;
use App\Services\ApplicationDocumentService;
use App\Services\DocumentChecklistService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BangunanDocumentController extends Controller
{
    public function index(Request $request, Application $application): View
    {
        $this->authorizeRecord($request, $application);
        $checklist = DocumentChecklist::query()->with('items')->where('application_id', $application->id)->first();
        $documents = $application->documents()->with(['uploader', 'verifier'])->orderByDesc('revision_number')->get();

        return view('bangunan.documents', compact('application', 'checklist', 'documents'));
    }

    public function initialise(Request $request, Application $application, DocumentChecklistService $service): RedirectResponse
    {
        $this->authorizeRecord($request, $application);
        $data = $request->validate(['row_version' => ['required', 'integer', 'min:1']]);
        $service->initialise($application, (int) $data['row_version'], $request->user());

        return to_route('bangunan.documents.index', $application)->with('success', 'Senarai semak diwujudkan daripada katalog yang disahkan.');
    }

    public function store(Request $request, Application $application, DocumentChecklistItem $item, ApplicationDocumentService $service): RedirectResponse
    {
        $this->authorizeRecord($request, $application);
        $data = $request->validate(['row_version' => ['required', 'integer', 'min:1'], 'document' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:20480']]);
        $service->upload($application, $item, $request->file('document'), (int) $data['row_version'], $request->user());

        return to_route('bangunan.documents.index', $application)->with('success', 'Revisi dokumen disimpan selepas imbasan keselamatan; pengesahan pegawai diperlukan.');
    }

    public function verify(Request $request, Application $application, DocumentChecklistItem $item, DocumentChecklistService $service): RedirectResponse
    {
        $this->authorizeRecord($request, $application);
        $data = $request->validate(['row_version' => ['required', 'integer', 'min:1'], 'document_id' => ['nullable', 'string', 'max:26'],
            'status' => ['required', Rule::in(['accepted', 'rejected', 'not_applicable'])],
            'remarks' => ['required_if:status,rejected,not_applicable', 'nullable', 'string', 'max:2000']]);
        $service->verify($application, $item, $data, $request->user());

        return to_route('bangunan.documents.index', $application)->with('success', 'Keputusan semakan dokumen direkodkan.');
    }

    public function download(Request $request, Application $application, ApplicationDocument $document): StreamedResponse
    {
        $this->authorizeRecord($request, $application);
        abort_unless($document->application_id === $application->id && $document->scan_status === 'clean', 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->storage_path), 404);
        abort_unless(hash_equals($document->checksum_sha256, hash_file('sha256', $disk->path($document->storage_path))), 409, 'Integriti dokumen tidak sepadan.');

        return $disk->download($document->storage_path, $document->original_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    private function authorizeRecord(Request $request, Application $application): void
    {
        abort_unless($application->responsible_department_id === 'JBANG' && in_array($application->application_type_id, BangunanApplication::TYPES, true), 404);
        abort_unless($request->user()->canAccessApplication($application), 403);
    }
}
