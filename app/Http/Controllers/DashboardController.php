<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\BangunanApplication;
use App\Services\Tasks\OfficerTaskAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, string $department = 'perancang'): Response
    {
        $actor = $request->user();
        [$departmentId, $name, $types, $allowed] = match ($department) {
            'perancang' => ['JPBD', 'Jabatan Perancang Bandar', ['PKM'], $actor->canAccessPkm()],
            'bangunan' => ['JBANG', 'Jabatan Bangunan', array_values(array_filter(BangunanApplication::TYPES,
                fn (string $type) => $actor->canPerformBangunan('access', $type))), $actor->canAccessBangunan()],
            'kejuruteraan' => ['JKEJ', 'Jabatan Kejuruteraan', ['LJUP'], $actor->canAccessKejuruteraan()],
            'landskap' => ['JLAND', 'Jabatan Landskap', ['PL'], $actor->canAccessLandskap()],
            default => abort(404),
        };
        abort_unless($allowed, 403);
        $base = Application::query()->where('responsible_department_id', $departmentId)->whereIn('application_type_id', $types);
        if ($department === 'kejuruteraan') {
            $base->visibleToLjupOfficer($actor);
        } else {
            $base->visibleTo($actor);
        }
        $counts = (clone $base)->selectRaw('current_status, count(*) as aggregate')->groupBy('current_status')->pluck('aggregate', 'current_status');

        $metrics = [
            'total' => $counts->sum(),
            'submitted' => $counts->get('submitted', 0),
            'in_review' => $counts->get('in_review', 0),
            'approved' => $counts->get('approved', 0),
        ];
        $statuses = collect(ApplicationStatus::cases())->map(fn (ApplicationStatus $status) => [
            'status' => $status, 'count' => (int) $counts->get($status->value, 0),
        ]);
        $recentApplications = (clone $base)->orderByDesc('created_at')->orderByDesc('id')->limit(6)->get();
        $access = app(OfficerTaskAccess::class);
        $hasTasks = array_intersect_key($access->types($actor), array_flip($types)) !== [];
        $taskBase = $access->tasks($actor)->where('department_id', $departmentId)->whereIn('status', ['open', 'in_progress']);
        $taskMetrics = ['accessible' => (clone $taskBase)->count(), 'mine' => (clone $taskBase)->where('assignee_id', $actor->id)->count(),
            'overdue' => (clone $taskBase)->whereDate('due_date', '<', today(config('app.display_timezone', 'Asia/Kuala_Lumpur'))->toDateString())->count()];
        $tasks = (clone $taskBase)->with(['application', 'assignee'])->orderByRaw('due_date ASC NULLS LAST')->orderByDesc('id')->limit(5)->get();
        $canAssign = array_intersect_key($access->types($actor, 'assign'), array_flip($types)) !== [];
        $registerUrl = match ($department) {
            'perancang' => $actor->canPerformPkm('perancang.pkm.register') ? route('perancang.create') : null,
            'bangunan' => collect($types)->contains(fn ($type) => $actor->canPerformBangunan('register', $type)) ? route('bangunan.create') : null,
            'landskap' => $actor->canPerformLandskap('register') ? route('landskap.create') : null,
            default => null,
        };

        return response()->view('dashboard', compact('department', 'departmentId', 'name', 'types', 'metrics', 'statuses',
            'recentApplications', 'hasTasks', 'taskMetrics', 'tasks', 'canAssign', 'registerUrl'))->header('Cache-Control', 'no-store');
    }
}
