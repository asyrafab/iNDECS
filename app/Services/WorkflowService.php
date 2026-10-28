<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WorkflowService
{
    public function __construct(private readonly DocumentChecklistService $checklists) {}

    public function transition(Application $application, string $action, int $expectedVersion, User $actor, ?string $remarks = null): Application
    {
        return DB::transaction(function () use ($application, $action, $expectedVersion, $actor, $remarks): Application {
            $record = Application::query()->lockForUpdate()->findOrFail($application->id);
            abort_unless($actor->canAccessApplication($record), 403);
            if ($record->row_version !== $expectedVersion) {
                throw ValidationException::withMessages(['workflow' => 'Rekod telah berubah. Muat semula sebelum meneruskan.']);
            }
            $transition = DB::table('core.workflow_transitions as t')
                ->join('core.workflow_templates as w', 'w.id', '=', 't.workflow_template_id')
                ->where('w.id', $record->workflow_template_id)->where('w.is_active', true)
                ->where('w.application_type_id', $record->application_type_id)
                ->where('t.action', $action)->where('t.from_stage', $record->current_stage->value)
                ->where('t.from_status', $record->current_status->value)->select('t.*')->first();
            if (! $transition) {
                throw ValidationException::withMessages(['workflow' => 'Tindakan tidak dibenarkan pada peringkat semasa.']);
            }
            abort_unless(app(CorePermissionService::class)->userCan($actor, $transition->permission_name, $actor->department_id, $record->application_type_id), 403);
            $this->checklists->guard($record, $transition->guard, $actor, $remarks);
            $before = ['current_stage' => $record->current_stage->value, 'current_status' => $record->current_status->value, 'row_version' => $record->row_version];
            $record->update(['current_stage' => $transition->to_stage, 'current_status' => $transition->to_status, 'row_version' => $record->row_version + 1]);
            $record->stageHistory()->create([
                'previous_stage' => $before['current_stage'], 'previous_status' => $before['current_status'],
                'new_stage' => $record->current_stage, 'new_status' => $record->current_status,
                'action' => $action, 'actor_id' => $actor->id, 'remarks' => $remarks,
            ]);
            AuditLog::query()->create([
                'auditable_type' => Application::class, 'auditable_id' => $record->id, 'event' => $action,
                'actor_id' => $actor->id, 'before_values' => $before,
                'after_values' => ['current_stage' => $record->current_stage->value, 'current_status' => $record->current_status->value, 'row_version' => $record->row_version],
                'ip_address' => request()?->ip(),
            ]);

            return $record;
        });
    }
}
