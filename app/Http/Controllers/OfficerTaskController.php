<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\OfficerTask;
use App\Services\Tasks\OfficerTaskAccess;
use App\Services\Tasks\OfficerTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class OfficerTaskController extends Controller
{
    public function __construct(private readonly OfficerTaskAccess $access, private readonly OfficerTaskService $tasks) {}

    public function index(Request $request)
    {
        $actor = $request->user();
        $types = $this->access->types($actor);
        abort_if($types === [], 403);
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['mine', 'team'])], 'status' => ['nullable', Rule::in(array_keys(OfficerTask::STATUSES))],
            'department' => ['nullable', Rule::in(array_values($types))], 'overdue' => ['nullable', 'boolean'], 'search' => ['nullable', 'string', 'max:255'],
        ]);
        $query = $this->access->tasks($actor)->with(['application', 'assignee'])
            ->when(($filters['scope'] ?? 'mine') !== 'team', fn ($q) => $q->where('assignee_id', $actor->id))
            ->when($filters['department'] ?? null, fn ($q, $department) => $q->where('department_id', $department))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['overdue'] ?? false, fn ($q) => $q->whereIn('status', ['open', 'in_progress'])->whereDate('due_date', '<', today(config('app.display_timezone', 'Asia/Kuala_Lumpur'))->toDateString()))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($match) => $match->where('title', 'ilike', '%'.$search.'%')
                ->orWhereHas('application', fn ($app) => $app->where('application_reference', 'ilike', '%'.$search.'%'))));

        return view('tasks.index', ['tasks' => $query->orderByRaw('due_date ASC NULLS LAST')->orderByDesc('id')->paginate(25)->withQueryString(),
            'filters' => $filters, 'departments' => array_values(array_unique($types)), 'canAssign' => $this->access->types($actor, 'assign') !== []]);
    }

    public function create(Request $request)
    {
        abort_if($this->access->types($request->user(), 'assign') === [], 403);
        $data = $request->validate(['application' => ['nullable', 'ulid'], 'search' => ['nullable', 'string', 'max:255']]);
        $application = isset($data['application']) ? $this->access->applications($request->user(), 'assign')->findOrFail($data['application']) : null;
        $applications = $this->access->applications($request->user(), 'assign')
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where(fn ($match) => $match->where('application_reference', 'ilike', '%'.$search.'%')->orWhere('title', 'ilike', '%'.$search.'%')))
            ->orderByDesc('id')->limit(100)->get();

        return view('tasks.create', ['applications' => $applications, 'application' => $application,
            'assignees' => $application ? $this->access->assignees($request->user(), $application) : collect(), 'search' => $data['search'] ?? '']);
    }

    public function store(Request $request)
    {
        $request->validate(['application_id' => ['required', 'ulid']]);
        $task = $this->tasks->create($request->user(), $request->string('application_id')->toString(), $request->all());

        return to_route('tasks.show', $task)->with('status', 'Tugasan dicipta dan diagihkan kepada pegawai.');
    }

    public function show(Request $request, string $task)
    {
        $task = $this->access->tasks($request->user())->with(['application', 'assignee', 'actions.actor'])->findOrFail($task);
        $canAssign = $this->access->canAssign($request->user(), $task->application);

        return view('tasks.show', ['task' => $task, 'recordUrl' => $this->access->recordUrl($task->application), 'canAssign' => $canAssign,
            'canWork' => $task->assignee_id === $request->user()->id && $this->access->applications($request->user(), 'work')->whereKey($task->application_id)->exists(),
            'assignees' => $canAssign ? $this->access->assignees($request->user(), $task->application) : collect(),
            'documents' => $task->application->documents()->where('scan_status', 'clean')->orderBy('original_name')->get()]);
    }

    public function act(Request $request, string $task)
    {
        $this->tasks->act($request->user(), $task, $request->all());

        return to_route('tasks.show', $task)->with('status', 'Tindakan tugasan direkodkan.');
    }

    public function evidence(Request $request, string $task, string $action, string $document)
    {
        $task = $this->access->tasks($request->user())->findOrFail($task);
        $action = $task->actions()->findOrFail($action);
        $pin = collect($action->evidence)->firstWhere('document_id', $document);
        abort_unless($pin, 404);
        $file = ApplicationDocument::query()->where('application_id', $task->application_id)->where('scan_status', 'clean')->findOrFail($document);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($file->storage_path) && (int) $pin['revision'] === $file->revision_number
            && hash_equals($pin['checksum_sha256'], $file->checksum_sha256)
            && hash_equals($pin['checksum_sha256'], hash_file('sha256', $disk->path($file->storage_path))), 404);
        AuditLog::query()->create(['auditable_type' => OfficerTask::class, 'auditable_id' => $task->id,
            'event' => 'officer_task_evidence_downloaded', 'actor_id' => $request->user()->id,
            'after_values' => ['action_id' => $action->id, 'document_id' => $file->id, 'checksum_sha256' => $pin['checksum_sha256']], 'ip_address' => $request->ip()]);

        return $disk->download($file->storage_path, $pin['name'], ['Content-Type' => $file->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }
}
