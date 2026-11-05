<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApplicantDraft;
use App\Models\ApplicationType;
use App\Models\LibraryDocument;
use App\Models\User;
use App\Services\Portal\PortalBoard;
use App\Services\Precheck\DraftChecklist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicPortalController extends Controller
{
    private const TYPES = ['PKM', 'PB', 'PBK', 'PBS', 'PMT', 'TEL'];

    public function __construct(private readonly PortalBoard $board) {}

    public function home(Request $request)
    {
        $types = $this->types()->get();

        return view('public.home', [
            'user' => $request->user(),
            'types' => $types,
            'board' => $this->board->for($request->user()),
            // ADR-362: public, active department library documents offered per application type.
            'libraryByType' => $types->mapWithKeys(fn ($type) => [$type->id => LibraryDocument::query()->publicFor($type->id)->get()])->all(),
        ]);
    }

    /** Current version of an active public library document. Anything else is 404. */
    public function download(string $document): StreamedResponse
    {
        $record = LibraryDocument::query()->where('status', 'active')->where('visibility', 'public')->findOrFail($document);

        return LibraryController::stream($record->currentVersion() ?? abort(404));
    }

    public function index(Request $request)
    {
        return view('public.drafts.index', [
            'drafts' => ApplicantDraft::query()->ownedBy($request->user())->with('applicationType')
                ->orderByDesc('updated_at')->orderByDesc('id')->paginate(15),
        ]);
    }

    public function create(Request $request)
    {
        $this->requireVerified($request);

        $types = $this->types()->get();
        $selected = $types->firstWhere('id', $request->query('type'));

        return view('public.drafts.edit', ['draft' => new ApplicantDraft(['application_type_id' => $selected?->id]), 'types' => $types, 'board' => $this->board->for($request->user())]);
    }

    public function store(Request $request)
    {
        $this->requireVerified($request);
        $data = $this->validated($request);
        $draft = DB::transaction(function () use ($request, $data): ApplicantDraft {
            // Serialize per-owner creation so concurrent requests cannot exceed the quota.
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if (ApplicantDraft::query()->ownedBy($request->user())->count() >= 100) {
                throw ValidationException::withMessages(['title' => 'Had 100 draf telah dicapai. Sila sambung draf sedia ada.']);
            }
            $draft = new ApplicantDraft($data);
            $draft->user_id = $request->user()->id;
            $draft->version = 1;
            $draft->save();
            $this->recordRevision($draft, $request->user());

            return $draft;
        });

        return redirect()->route('portal.drafts.edit', $draft)->with('status', 'Draf disimpan. Draf ini belum dihantar kepada MBSP.');
    }

    public function edit(Request $request, string $draft)
    {
        $this->requireVerified($request);
        $draft = ApplicantDraft::query()->ownedBy($request->user())->findOrFail($draft);

        return view('public.drafts.edit', [
            'draft' => $draft,
            'types' => $this->types()->get(),
            'board' => $this->board->for($request->user(), justSaved: $request->session()->has('status')),
            'checklist' => app(DraftChecklist::class)->for($draft),
        ]);
    }

    public function update(Request $request, string $draft)
    {
        $this->requireVerified($request);
        // Check ownership before validating input; foreign IDs always return 404.
        $owned = ApplicantDraft::query()->ownedBy($request->user())->findOrFail($draft);
        $data = $this->validated($request);
        $request->validate(['version' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($request, $owned, $data): void {
            $locked = ApplicantDraft::query()->ownedBy($request->user())->lockForUpdate()->findOrFail($owned->id);
            if ($locked->version !== $request->integer('version')) {
                throw ValidationException::withMessages(['version' => 'Draf telah berubah di tetingkap lain. Salin perubahan anda dan muat semula draf sebelum menyimpan.']);
            }
            $locked->fill($data);
            $locked->version++;
            $locked->save();
            $this->recordRevision($locked, $request->user());
        });

        return redirect()->route('portal.drafts.edit', $owned)->with('status', 'Perubahan draf disimpan. Draf ini belum dihantar kepada MBSP.');
    }

    private function types(): Builder
    {
        return ApplicationType::query()->whereIn('id', self::TYPES)->where('is_active', true)
            ->whereHas('primaryDepartment', fn (Builder $query) => $query->where('is_active', true))
            ->with('primaryDepartment')->orderBy('primary_department_id')->orderBy('id');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'application_type_id' => ['required', Rule::in($this->types()->pluck('id')->all())],
            'title' => ['required', 'string', 'max:500'],
            'site_address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            // Project particulars (ADR-363) drive the checklist rules; values come only from config/kaunter.php.
            'work_type' => ['nullable', Rule::in(array_keys(config('kaunter.work_types')))],
            'building_category' => ['nullable', Rule::in(array_keys(config('kaunter.building_categories')))],
            'storeys' => ['nullable', 'integer', 'min:1', 'max:100'],
            'floor_area' => ['nullable', 'numeric', 'min:1', 'max:1000000'],
            'site_conditions' => ['nullable', 'array'],
            'site_conditions.*' => ['string', 'distinct', Rule::in(array_keys(config('kaunter.site_conditions')))],
        ], [], ['application_type_id' => 'jenis permohonan', 'title' => 'tajuk cadangan', 'site_address' => 'alamat tapak', 'notes' => 'catatan',
            'work_type' => 'jenis kerja', 'building_category' => 'kategori bangunan', 'storeys' => 'bilangan tingkat',
            'floor_area' => 'keluasan lantai', 'site_conditions' => 'keadaan tapak', 'site_conditions.*' => 'keadaan tapak']);

        // A particular left blank is cleared, so the checklist always reflects what the form shows.
        return $data + array_fill_keys(ApplicantDraft::PARTICULARS, null);
    }

    private function requireVerified(Request $request): void
    {
        abort_unless($request->user()->email_verified_at, 403, 'Sahkan emel anda melalui halaman Akaun saya sebelum menyediakan draf.');
    }

    private function recordRevision(ApplicantDraft $draft, User $actor): void
    {
        DB::table('core.applicant_draft_revisions')->insert([
            'draft_id' => $draft->id, 'actor_id' => $actor->id, 'version' => $draft->version,
            'snapshot' => json_encode($draft->only(['application_type_id', 'title', 'site_address', 'notes', ...ApplicantDraft::PARTICULARS]), JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
