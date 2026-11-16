<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApplicantDraft;
use App\Models\ApplicationType;
use App\Models\AuditLog;
use App\Models\CheckPayment;
use App\Models\LibraryDocument;
use App\Models\User;
use App\Services\Amira\ContextBuilder;
use App\Services\Amira\ProposalSanitiser;
use App\Services\PaidCheck\ApplicantCheckPresenter;
use App\Services\PaidCheck\PaidCheckJourneyService;
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
        // ADR-369: "Guna butiran ini" from AMIRA opens this form prefilled; only offered values survive, and nothing is saved until the PSP saves.
        $prefill = $this->prefill($request->query('prefill'));
        $draft = new ApplicantDraft(['application_type_id' => $prefill['application_type'] ?? $selected?->id]);
        $draft->fill(array_intersect_key($prefill, array_flip(ApplicantDraft::PARTICULARS)));

        return view('public.drafts.edit', ['draft' => $draft, 'types' => $types, 'board' => $this->board->for($request->user())]);
    }

    private function prefill(mixed $query): array
    {
        if (! is_array($query)) {
            return [];
        }
        $numeric = fn (mixed $value, bool $integer) => is_string($value) && is_numeric($value)
            ? ($integer ? (ctype_digit($value) ? (int) $value : null) : (float) $value) : null;

        return ProposalSanitiser::clean([
            'application_type' => is_string($query['application_type'] ?? null) ? $query['application_type'] : null,
            'work_type' => is_string($query['work_type'] ?? null) ? $query['work_type'] : null,
            'building_category' => is_string($query['building_category'] ?? null) ? $query['building_category'] : null,
            'storeys' => $numeric($query['storeys'] ?? null, true),
            'floor_area' => $numeric($query['floor_area'] ?? null, false),
            'site_conditions' => is_array($query['site_conditions'] ?? null) ? array_values($query['site_conditions']) : [],
        ], app(ContextBuilder::class)->options()) ?? [];
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
            'board' => $this->board->for($request->user(), justSaved: $request->session()->has('status'), focus: $draft),
            'checklist' => app(DraftChecklist::class)->for($draft),
            'checkResult' => ($journey = $draft->journey()->first()) ? app(ApplicantCheckPresenter::class)->for($journey) : null,
            'editable' => PaidCheckJourneyService::draftEditable($draft),
        ]);
    }

    public function update(Request $request, string $draft)
    {
        $this->requireVerified($request);
        // Check ownership before validating input; foreign IDs always return 404.
        $owned = ApplicantDraft::query()->ownedBy($request->user())->findOrFail($draft);
        abort_unless(PaidCheckJourneyService::draftEditable($owned), 403, 'Draf dikunci semasa semakan AIRS.');
        $data = $this->validated($request);
        // Once a payment has been started (pending or paid) the draft's application type is fixed.
        $paymentStarted = CheckPayment::query()->where('draft_id', $owned->id)->whereIn('status', ['pending', 'paid'])->exists();
        if ($paymentStarted && $data['application_type_id'] !== $owned->application_type_id) {
            throw ValidationException::withMessages(['application_type_id' => 'Jenis permohonan tidak boleh ditukar selepas bayaran.']);
        }
        $request->validate(['version' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($request, $owned, $data): void {
            $locked = ApplicantDraft::query()->ownedBy($request->user())->lockForUpdate()->findOrFail($owned->id);
            if ($locked->version !== $request->integer('version')) {
                throw ValidationException::withMessages(['version' => 'Draf telah berubah di tetingkap lain. Salin perubahan anda dan muat semula draf sebelum menyimpan.']);
            }
            $locked->fill($data);
            // The applicant has now typed a real title over the one a drop created.
            $locked->title_provisional = false;
            $locked->version++;
            $locked->save();
            $this->recordRevision($locked, $request->user());
        });

        return redirect()->route('portal.drafts.edit', $owned)->with('status', 'Perubahan draf disimpan. Draf ini belum dihantar kepada MBSP.');
    }

    public function destroy(Request $request, string $draft)
    {
        DB::transaction(function () use ($request, $draft): void {
            $owned = ApplicantDraft::query()->ownedBy($request->user())->lockForUpdate()->findOrFail($draft);
            abort_unless($owned->removable(), 403, 'Draf tidak boleh dibuang selepas bayaran atau semakan AIRS bermula.');
            $request->validate(['version' => ['required', 'integer', 'min:1']]);
            if ($owned->version !== $request->integer('version')) {
                throw ValidationException::withMessages(['version' => 'Draf telah berubah di tetingkap lain. Muat semula sebelum membuang draf.']);
            }
            $owned->delete();
            AuditLog::query()->create([
                'auditable_type' => ApplicantDraft::class, 'auditable_id' => $owned->id,
                'event' => 'applicant_draft_removed', 'actor_id' => $request->user()->id,
                'before_values' => ['version' => $owned->version, 'deleted_at' => null],
                'after_values' => ['deleted_at' => $owned->deleted_at->toIso8601String()],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('portal.home')->with('status', 'Draf telah dibuang daripada Fail saya.');
    }

    private function types(): Builder
    {
        return ApplicationType::query()->portal();
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
            // LJUP drafts declare the proposed lighting types; AIRS applies the matching rules.
            'lighting_types' => ['exclude_unless:application_type_id,LJUP', 'required', 'array', 'min:1'],
            'lighting_types.*' => ['exclude_unless:application_type_id,LJUP', 'string', 'distinct', Rule::in(array_keys(config('airs.lighting_types')))],
        ], [], ['application_type_id' => 'jenis permohonan', 'title' => 'tajuk cadangan', 'site_address' => 'alamat tapak', 'notes' => 'catatan',
            'work_type' => 'jenis kerja', 'building_category' => 'kategori bangunan', 'storeys' => 'bilangan tingkat',
            'floor_area' => 'keluasan lantai', 'site_conditions' => 'keadaan tapak', 'site_conditions.*' => 'keadaan tapak',
            'lighting_types' => 'jenis lampu', 'lighting_types.*' => 'jenis lampu']);

        // A particular left blank is cleared, so the checklist always reflects what the form shows.
        return $data + array_fill_keys(ApplicantDraft::PARTICULARS, null) + ['lighting_types' => null];
    }

    private function requireVerified(Request $request): void
    {
        abort_unless($request->user()->email_verified_at, 403, 'Sahkan emel anda melalui halaman Akaun saya sebelum menyediakan draf.');
    }

    private function recordRevision(ApplicantDraft $draft, User $actor): void
    {
        DB::table('core.applicant_draft_revisions')->insert([
            'draft_id' => $draft->id, 'actor_id' => $actor->id, 'version' => $draft->version,
            'snapshot' => json_encode($draft->only(['application_type_id', 'title', 'site_address', 'notes', ...ApplicantDraft::PARTICULARS, 'lighting_types']), JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
