<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Airs\Models\ReviewFinding;
use App\Airs\Models\ReviewVersion;
use App\Airs\Review\FindingFeedbackService;
use App\Airs\Review\MatrixProfileProjection;
use App\Airs\Review\ProfileFieldReviewService;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationRecommendation;
use App\Models\PaidCheckJourney;
use App\Services\ApplicationRecommendationService;
use App\Services\Kejuruteraan\EngineeringApplicationAccess;
use App\Services\Kejuruteraan\LjupRecommendationSource;
use App\Services\Kejuruteraan\OfficerCheckPresenter;
use App\Support\DepartmentStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Jabatan Kejuruteraan workspace for LJUP applications and their AIRS checks. */
final class KejuruteraanApplicationController extends Controller
{
    public function __construct(private readonly OfficerCheckPresenter $presenter) {}

    public function index(Request $request): View
    {
        $applications = app(EngineeringApplicationAccess::class)->applications($request->user())
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(25);
        $journeys = PaidCheckJourney::query()->whereIn('application_id', $applications->pluck('id'))->get()->keyBy('application_id');

        return view('kejuruteraan.index', compact('applications', 'journeys'));
    }

    public function show(Request $request, string $application): View
    {
        $native = app(EngineeringApplicationAccess::class)->applications($request->user())
            ->whereIn('application_type_id', ['PKT', 'PKP'])->find($application);
        if ($native) {
            $native->load(['applicationType', 'sites', 'lots', 'applicationParties', 'stageHistory.actor']);

            return view('kejuruteraan.native-show', ['application' => $native,
                'notes' => DB::table(DepartmentStructure::applicationTable($native->application_type_id))->where('application_id', $native->id)->value('technical_notes')]);
        }
        $record = $this->application($request, $application);
        $recommendationContext = null;
        $recommendations = collect();
        if ($request->user()->canPerformLjup('review')) {
            try {
                $recommendationContext = app(ApplicationRecommendationService::class)->context($request->user(), $record->id, app(LjupRecommendationSource::class));
            } catch (HttpExceptionInterface $exception) {
                if ($exception->getStatusCode() !== 409) {
                    throw $exception;
                }
            }
            $recommendations = ApplicationRecommendation::query()->where('application_id', $record->id)
                ->with(['actor', 'letterDraft'])->orderByDesc('revision')->get();
        }

        return view('kejuruteraan.show', ['application' => $record, 'view' => $this->presenter->for($record),
            'recommendationContext' => $recommendationContext, 'recommendations' => $recommendations,
            'recommendationOutcomes' => app(LjupRecommendationSource::class)->outcomes()]);
    }

    public function storeRecommendation(Request $request, string $application, ApplicationRecommendationService $service, LjupRecommendationSource $source): RedirectResponse
    {
        $record = $this->application($request, $application);
        $recommendation = $service->record($request->user(), $record->id, $source, $request->all());

        return redirect()->route('kejuruteraan.recommendations.preview', [$record, $recommendation])
            ->with('status', 'Syor dalaman dan draf surat disimpan. Tiada keputusan rasmi dikeluarkan.');
    }

    public function previewRecommendation(Request $request, string $application, string $recommendation): Response
    {
        abort_unless($request->user()->canPerformLjup('review'), 403);
        $record = $this->application($request, $application);
        $revision = ApplicationRecommendation::query()->where('application_id', $record->id)->with('letterDraft')->findOrFail($recommendation);
        $draft = $revision->letterDraft;
        abort_unless($draft && $draft->template_key === 'internal-recommendation' && $draft->template_version === 1, 409);

        return response()->view('kejuruteraan.letters.internal-v1', ['application' => $record, 'recommendation' => $revision, 'draft' => $draft,
            'letter' => $draft->content_snapshot])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function document(Request $request, string $application, string $document): StreamedResponse
    {
        $record = $this->application($request, $application);
        $file = ApplicationDocument::query()->where('application_id', $record->id)->findOrFail($document);

        return Storage::disk('local')->response($file->storage_path, $file->original_name, [
            'Content-Type' => $file->mime_type, 'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "frame-ancestors 'self'", 'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function storeFeedback(Request $request, string $application, string $finding, FindingFeedbackService $feedback): RedirectResponse
    {
        abort_unless($request->user()->canPerformLjup('review'), 403);
        $record = $this->application($request, $application);
        $found = ReviewFinding::query()->whereKey($finding)
            ->whereHas('reviewVersion.reviewCase', fn ($query) => $query->where('application_id', $record->id))->firstOrFail();
        $data = $request->validate(['officer_status' => ['required', 'string'], 'officer_comment' => ['nullable', 'string', 'max:2000'],
            'after_save' => ['nullable', Rule::in(['stay', 'next'])], 'review_mode' => ['nullable', Rule::in(['focus', 'overview'])]]);
        $feedback->record($request->user(), $found, $data['officer_status'], $data['officer_comment'] ?? null);

        if (($data['after_save'] ?? 'stay') === 'next') {
            $version = $found->reviewVersion()->firstOrFail();
            $next = $this->presenter->nextUnreviewed($version, $found->id);
            if ($next !== null) {
                return redirect(route('kejuruteraan.show', ['application' => $record->id, 'review' => 'focus', 'finding' => $next]).'#finding-'.$next)
                    ->with('status', 'Ulasan disimpan. Teruskan dengan dapatan seterusnya yang belum diulas.');
            }

            return redirect(route('kejuruteraan.show', ['application' => $record->id, 'review' => 'focus']).'#review-complete-'.$version->check_number)
                ->with('status', 'Ulasan disimpan. Semua dapatan dalam semakan ini telah mempunyai ulasan pegawai. Ini bukan keputusan rasmi.');
        }

        $parameters = ['application' => $record->id];
        if (isset($data['review_mode'])) {
            $parameters['review'] = $data['review_mode'];
            $parameters['finding'] = $found->id;
        }

        return redirect(route('kejuruteraan.show', $parameters).'#finding-'.$found->id)->with('status', 'Ulasan pegawai bagi '.$found->rule_id.' disimpan.');
    }

    public function storeFieldReview(Request $request, string $application, ProfileFieldReviewService $fields): RedirectResponse
    {
        abort_unless($request->user()->canPerformLjup('review'), 403);
        $record = $this->application($request, $application);
        $data = $request->validate([
            'review_version_id' => ['required', 'string'],
            'field' => ['required', Rule::in(MatrixProfileProjection::FIELDS)],
            'disposition' => ['required', Rule::in(['ACCEPTED', 'CORRECTED', 'REJECTED'])],
            'value' => ['nullable', 'string', 'max:2000'], 'reason' => ['required', 'string', 'max:2000'],
            'expected_revision' => ['required', 'integer', 'min:0'],
        ]);
        $version = ReviewVersion::query()->whereKey($data['review_version_id'])
            ->whereHas('reviewCase', fn ($query) => $query->where('application_id', $record->id))->firstOrFail();
        $fields->record($request->user(), $version, $data['field'], $data['disposition'], $data['value'] ?? null, $data['reason'], (int) $data['expected_revision']);

        return redirect(route('kejuruteraan.show', $record).'#profil')->with('status', 'Semakan medan disimpan. Rekod rasmi iNDECS tidak diubah.');
    }

    private function application(Request $request, string $id): Application
    {
        abort_unless($request->user()->canPerformLjup('access'), 403);

        return Application::query()->visibleToLjupOfficer($request->user())->findOrFail($id);
    }
}
