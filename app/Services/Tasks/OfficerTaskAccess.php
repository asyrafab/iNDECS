<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Application;
use App\Models\BangunanApplication;
use App\Models\OfficerTask;
use App\Models\User;
use App\Services\CorePermissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class OfficerTaskAccess
{
    /** Supported record types, intersected with module access and explicit task authority. */
    public function types(User $actor, string $action = 'view'): array
    {
        $types = ['PKM' => 'JPBD', 'PL' => 'JLAND', 'LJUP' => 'JKEJ', 'PKT' => 'JKEJ', 'PKP' => 'JKEJ'];
        foreach (BangunanApplication::TYPES as $type) {
            $types[$type] = 'JBANG';
        }

        return array_filter($types, function (string $department, string $type) use ($actor, $action): bool {
            $module = match ($type) {
                'PKM' => $actor->canAccessPkm(),
                'PL' => $actor->canAccessLandskap(),
                'LJUP' => $actor->canPerformLjup('access'),
                'PKT', 'PKP' => $actor->canPerformEngineering('access', $type),
                default => $actor->canPerformBangunan('access', $type),
            };

            return $module && app(CorePermissionService::class)->userCan($actor, 'core.tasks.view', $department, $type)
                && app(CorePermissionService::class)->userCan($actor, 'core.tasks.'.$action, $department, $type);
        }, ARRAY_FILTER_USE_BOTH);
    }

    public function applications(User $actor, string $action = 'view'): Builder
    {
        $types = $this->types($actor, $action);

        return Application::query()->where(function (Builder $query) use ($types, $actor): void {
            $query->whereRaw('false');
            foreach ($types as $type => $department) {
                $query->orWhere(function (Builder $group) use ($type, $department, $actor): void {
                    $group->where('application_type_id', $type)->where('responsible_department_id', $department);
                    if ($type === 'LJUP') {
                        $group->visibleToLjupOfficer($actor);
                    } else {
                        $group->visibleTo($actor);
                    }
                });
            }
        });
    }

    public function tasks(User $actor): Builder
    {
        return OfficerTask::query()->whereIn('application_id', $this->applications($actor)->select('core.applications.id'))
            ->where(fn (Builder $query) => $query->where('assignee_id', $actor->id)
                ->orWhereIn('application_id', $this->applications($actor, 'assign')->select('core.applications.id')));
    }

    public function canAssign(User $actor, Application $application): bool
    {
        return $this->applications($actor, 'assign')->whereKey($application->id)->exists();
    }

    public function assignees(User $actor, Application $application): Collection
    {
        abort_unless($this->canAssign($actor, $application), 403);

        return User::query()->where('account_type', 'staff')->where('is_active', true)
            ->where('department_id', $application->responsible_department_id)->orderBy('name')->get()
            ->filter(fn (User $candidate) => $this->applications($candidate, 'work')->whereKey($application->id)->exists()
                && $this->applications($candidate)->whereKey($application->id)->exists())->values();
    }

    public function recordUrl(Application $application): string
    {
        return route(match ($application->application_type_id) {
            'PKM' => 'perancang.show', 'PL' => 'landskap.show', 'LJUP', 'PKT', 'PKP' => 'kejuruteraan.show', default => 'bangunan.show',
        }, $application);
    }
}
