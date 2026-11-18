<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApplicationType;
use App\Models\Department;
use App\Models\LibraryDocument;
use App\Models\LibraryDocumentVersion;
use App\Services\Library\LibraryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** ADR-362: staff department library. Every staff role reads; only a Penolong Pengarah changes. */
final class LibraryController extends Controller
{
    public function __construct(private readonly LibraryService $library) {}

    public function index(Request $request): View
    {
        $this->authorizeView($request);
        $departments = Department::query()->where('is_active', true)->orderBy('name')->get();
        $department = $departments->firstWhere('id', $request->query('department')) ?? $departments->firstWhere('id', $request->user()->department_id) ?? $departments->first();
        $documents = $department ? LibraryDocument::query()->where('department_id', $department->id)->with('applicationTypes')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderBy('category')->orderBy('title')->get() : collect();

        return view('library.index', ['departments' => $departments, 'department' => $department, 'documents' => $documents,
            'canManage' => $department && $request->user()->canManageLibrary($department->id)]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->canManageLibrary((string) $request->user()->department_id), 403);

        return view('library.create', ['types' => $this->types($request->user()->department_id)]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canManageLibrary((string) $request->user()->department_id), 403);
        $document = $this->library->create($request->user(), $request->only(['title', 'category', 'description', 'visibility', 'application_type_ids']), $this->file($request));

        return to_route('library.show', $document)->with('success', 'Dokumen ditambah ke perpustakaan jabatan.');
    }

    public function show(Request $request, LibraryDocument $document): View
    {
        $this->authorizeView($request);

        return view('library.show', ['document' => $document->load(['department', 'applicationTypes', 'versions.uploader']),
            'canManage' => $request->user()->canManageLibrary($document->department_id)]);
    }

    public function storeVersion(Request $request, LibraryDocument $document): RedirectResponse
    {
        abort_unless($request->user()->canManageLibrary($document->department_id), 403);
        $this->library->addVersion($request->user(), $document, $this->file($request), $request->input('notes'));

        return to_route('library.show', $document)->with('success', 'Versi baharu dimuat naik.');
    }

    public function retire(Request $request, LibraryDocument $document): RedirectResponse
    {
        abort_unless($request->user()->canManageLibrary($document->department_id), 403);
        $this->library->retire($request->user(), $document);

        return to_route('library.show', $document)->with('success', 'Dokumen dibatalkan. Ia tidak lagi dipaparkan kepada pemohon atau digunakan sebagai rujukan.');
    }

    public function download(Request $request, LibraryDocument $document, int $version): StreamedResponse
    {
        $this->authorizeView($request);
        $record = LibraryDocumentVersion::query()->where('document_id', $document->id)->where('version', $version)->firstOrFail();

        return self::stream($record);
    }

    /** Shared with the public portal download. */
    public static function stream(LibraryDocumentVersion $record): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($record->path), 404);

        return Storage::disk('local')->download($record->path, $record->original_name, [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()->canViewLibrary(), 403);
    }

    private function file(Request $request): UploadedFile
    {
        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => 'Medan fail dokumen diperlukan.']);
        }

        return $file;
    }

    private function types(?string $departmentId)
    {
        return ApplicationType::query()->where('is_active', true)->orderBy('primary_department_id')->orderBy('id')
            ->get()->sortBy(fn ($type) => $type->primary_department_id === $departmentId ? 0 : 1)->values();
    }
}
