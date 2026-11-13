<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SyncAirsReview;
use App\Models\AirsFeedback;
use App\Models\AirsReview;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Services\Airs\AirsReviews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class AirsReviewController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->canAccessPkm() || $request->user()->canAccessBangunan(), 403);
        $reviews = AirsReview::query()->with('application')->latest()->limit(100)->get()
            ->filter(fn ($review) => $request->user()->canAccessApplication($review->application));

        return view('airs.index', compact('reviews'));
    }

    public function application(Request $request, Application $application, AirsReviews $service)
    {
        abort_unless($request->user()->canAccessApplication($application), 403);
        $reviews = AirsReview::query()->where('application_id', $application->id)->latest()->get();

        return view('airs.application', ['application' => $application, 'reviews' => $reviews,
            'submission' => $service->latestSubmission($application), 'module' => config('airs.modules.'.$application->application_type_id),
            'canReview' => $service->canReview($application, $request->user())]);
    }

    public function store(Request $request, Application $application, AirsReviews $service)
    {
        $data = $request->validate(['lighting_types' => 'sometimes|array|max:3', 'lighting_types.*' => ['required', 'distinct', Rule::in(['BESI', 'KONKRIT', 'SOLAR'])]]);
        $review = $service->queue($application, $request->user(), $data['lighting_types'] ?? []);

        return to_route('airs.show', $review)->with('success', 'Permintaan semakan AIRS direkodkan.');
    }

    public function show(Request $request, AirsReview $review, AirsReviews $service)
    {
        abort_unless($request->user()->canAccessApplication($review->application), 403);
        $feedback = AirsFeedback::query()->where('review_id', $review->id)->latest()->get()->groupBy('finding_id');

        return view('airs.show', ['review' => $review, 'application' => $review->application,
            'current' => $service->isCurrent($review), 'feedback' => $feedback,
            'canReview' => $service->canReview($review->application, $request->user())]);
    }

    public function refresh(Request $request, AirsReview $review, AirsReviews $service)
    {
        $service->assertReviewer($review->application, $request->user());
        SyncAirsReview::dispatch($review->id)->onQueue(config('airs.queue'));

        return back()->with('success', 'Penyegerakan AIRS dijadualkan.');
    }

    public function feedback(Request $request, AirsReview $review, AirsReviews $service)
    {
        $data = $request->validate(['event_id' => 'required|ulid', 'finding_id' => 'required|ulid',
            'status' => ['required', Rule::in(AirsReviews::STATUSES)], 'comment' => 'nullable|string|max:2000']);
        $service->feedback($review, $request->user(), $data);

        return back()->with('success', 'Maklum balas direkodkan untuk dihantar kepada AIRS.');
    }

    public function document(Request $request, AirsReview $review, ApplicationDocument $document)
    {
        abort_unless($request->user()->canAccessApplication($review->application), 403);
        $entry = collect($review->submission->document_manifest)->firstWhere('id', $document->id);
        abort_unless($entry && $document->application_id === $review->application_id, 404);
        abort_unless(Storage::disk('local')->exists($document->storage_path)
            && hash_file('sha256', Storage::disk('local')->path($document->storage_path)) === $entry['sha256'], 409);

        return Storage::disk('local')->download($document->storage_path, $document->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
