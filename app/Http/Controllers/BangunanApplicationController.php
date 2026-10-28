<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Http\Requests\StoreBangunanApplicationRequest;
use App\Models\Application;
use App\Models\ApplicationType;
use App\Models\BangunanApplication;
use App\Services\BangunanRegistrationService;
use App\Services\CorePermissionService;
use App\Services\WorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class BangunanApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(BangunanApplication::TYPES)],
            'status' => ['nullable', Rule::enum(ApplicationStatus::class)],
        ]);
        $types = collect(BangunanApplication::TYPES)->filter(fn (string $type) => $request->user()->canPerformBangunan('access', $type))->values();
        $base = Application::query()->where('responsible_department_id', 'JBANG')
            ->whereIn('application_type_id', $types)->visibleTo($request->user());
        $metrics = ['total' => (clone $base)->count(),
            'submitted' => (clone $base)->where('current_status', ApplicationStatus::Submitted)->count(),
            'in_review' => (clone $base)->where('current_status', ApplicationStatus::InReview)->count()];
        $applications = $base->with(['applicationType', 'bangunan', 'sites'])
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters): void {
                $search = '%'.addcslashes($filters['search'], '\\%_').'%';
                $query->where(fn ($query) => $query->where('application_reference', 'ilike', $search)
                    ->orWhere('external_reference', 'ilike', $search)->orWhere('title', 'ilike', $search));
            })
            ->when(filled($filters['type'] ?? null), fn ($query) => $query->where('application_type_id', $filters['type']))
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('current_status', $filters['status']))
            ->latest('submission_date')->latest('id')->paginate(15)->withQueryString();

        return view('bangunan.index', compact('applications', 'metrics', 'filters', 'types'));
    }

    public function create(Request $request): View
    {
        $types = ApplicationType::query()->whereIn('id', BangunanApplication::TYPES)->where('is_active', true)->where('primary_department_id', 'JBANG')
            ->get()->filter(fn ($type) => $request->user()->canPerformBangunan('register', $type->id) && $request->user()->canPerformBangunan('access', $type->id));
        abort_if($types->isEmpty(), 403);
        $districts = $request->user()->role !== 'system_admin' && filled($request->user()->district) ? [$request->user()->district] : BangunanApplication::DISTRICTS;

        return view('bangunan.create', compact('types', 'districts'));
    }

    public function store(StoreBangunanApplicationRequest $request, BangunanRegistrationService $registration): RedirectResponse
    {
        $application = $registration->register($request->validated(), $request->user());

        return to_route('bangunan.show', $application)->with('success', 'Permohonan '.$application->application_reference.' berjaya didaftarkan.');
    }

    public function show(Request $request, Application $application): View
    {
        abort_unless(in_array($application->application_type_id, BangunanApplication::TYPES, true) && $application->responsible_department_id === 'JBANG', 404);
        abort_unless($request->user()->canAccessApplication($application), 403);
        $application->load(['applicationType', 'bangunan', 'sites', 'lots', 'applicationParties', 'stageHistory.actor']);
        $audit = DB::table('audit_logs as a')->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
            ->where('a.auditable_type', Application::class)->where('a.auditable_id', $application->id)
            ->orderByDesc('a.id')->select('a.created_at', 'a.event', 'u.name as actor_name')->get();
        $transitions = DB::table('core.workflow_transitions as t')
            ->join('core.workflow_templates as w', 'w.id', '=', 't.workflow_template_id')
            ->where('w.id', $application->workflow_template_id)->where('w.is_active', true)
            ->where('w.application_type_id', $application->application_type_id)->select('t.*')
            ->where('from_stage', $application->current_stage->value)->where('from_status', $application->current_status->value)
            ->get()->filter(fn ($transition) => app(CorePermissionService::class)->userCan($request->user(), $transition->permission_name, $request->user()->department_id, $application->application_type_id));

        return view('bangunan.show', compact('application', 'transitions', 'audit'));
    }

    public function transition(Request $request, Application $application, WorkflowService $workflow): RedirectResponse
    {
        abort_unless(in_array($application->application_type_id, BangunanApplication::TYPES, true) && $application->responsible_department_id === 'JBANG', 404);
        abort_unless($request->user()->canAccessApplication($application), 403);
        $data = $request->validate(['action' => ['required', 'string', 'max:100'], 'row_version' => ['required', 'integer', 'min:1'], 'remarks' => ['nullable', 'string', 'max:2000']]);
        $workflow->transition($application, $data['action'], (int) $data['row_version'], $request->user(), $data['remarks'] ?? null);

        return to_route('bangunan.show', $application)->with('success', 'Peringkat permohonan berjaya dikemas kini.');
    }
}
