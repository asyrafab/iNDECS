<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\OfficerTask;
use App\Models\OfficerTaskAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class OfficerTaskService
{
    public function __construct(private readonly OfficerTaskAccess $access) {}

    public function create(User $actor, string $applicationId, array $input): OfficerTask
    {
        return DB::transaction(function () use ($actor, $applicationId, $input): OfficerTask {
            $application = $this->access->applications($actor, 'assign')->lockForUpdate()->findOrFail($applicationId);
            $data = Validator::make($input, [
                'title' => ['required', 'string', 'max:255'], 'instructions' => ['nullable', 'string', 'max:5000'],
                'assignee_id' => ['required', 'integer'], 'due_date' => ['nullable', 'date_format:Y-m-d'],
            ])->validate();
            $this->assignee($application, (int) $data['assignee_id']);
            $task = OfficerTask::query()->create($data + ['application_id' => $application->id,
                'department_id' => $application->responsible_department_id, 'created_by' => $actor->id, 'status' => 'open', 'revision' => 1]);
            $this->record($task, $actor, 'created', [], null, []);

            return $task;
        });
    }

    public function act(User $actor, string $taskId, array $input): OfficerTask
    {
        return DB::transaction(function () use ($actor, $taskId, $input): OfficerTask {
            // Filter before returning either metadata or validation feedback for a task.
            $task = $this->access->tasks($actor)->lockForUpdate()->findOrFail($taskId);
            $application = $this->access->applications($actor)->lockForUpdate()->findOrFail($task->application_id);
            $data = Validator::make($input, [
                'revision' => ['required', 'integer', 'min:1'], 'action' => ['required', Rule::in(['start', 'complete', 'assign', 'cancel'])],
                'note' => ['required_unless:action,start', 'nullable', 'string', 'max:5000'],
                'assignee_id' => ['required_if:action,assign', 'nullable', 'integer'],
                'due_date' => ['nullable', 'date_format:Y-m-d'],
                'document_ids' => ['nullable', 'array', 'max:20'], 'document_ids.*' => ['required', 'ulid', 'distinct'],
            ])->validate();
            if ($task->revision !== (int) $data['revision']) {
                abort(409, 'Tugasan telah berubah. Muat semula sebelum meneruskan.');
            }
            if (in_array($task->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages(['task' => 'Tugasan telah ditutup dan tidak boleh diubah.']);
            }
            $action = $data['action'];
            if (in_array($action, ['assign', 'cancel'], true)) {
                abort_unless($this->access->canAssign($actor, $application), 403);
            } else {
                abort_unless($task->assignee_id === $actor->id
                    && $this->access->applications($actor, 'work')->whereKey($application->id)->exists(), 403);
            }
            $before = $this->snapshot($task);
            $evidence = [];
            if ($action === 'assign') {
                $this->assignee($application, (int) $data['assignee_id']);
                $task->assignee_id = (int) $data['assignee_id'];
                $task->due_date = $data['due_date'] ?? null;
                $task->status = 'open';
            } elseif ($action === 'start') {
                if ($task->status !== 'open') {
                    throw ValidationException::withMessages(['task' => 'Tugasan ini telah dimulakan.']);
                }
                $task->status = 'in_progress';
            } elseif ($action === 'complete') {
                if ($task->status !== 'in_progress') {
                    throw ValidationException::withMessages(['task' => 'Mulakan tugasan sebelum merekodkan penyelesaian.']);
                }
                foreach ($data['document_ids'] ?? [] as $documentId) {
                    $document = ApplicationDocument::query()->where('application_id', $application->id)->lockForUpdate()->findOrFail($documentId);
                    $disk = Storage::disk('local');
                    if ($document->scan_status !== 'clean' || ! $disk->exists($document->storage_path)
                        || ! hash_equals($document->checksum_sha256, hash_file('sha256', $disk->path($document->storage_path)))) {
                        throw ValidationException::withMessages(['document_ids' => 'Bukti mesti berupa dokumen yang telah diimbas dengan selamat.']);
                    }
                    $evidence[] = ['document_id' => $document->id, 'revision' => $document->revision_number,
                        'checksum_sha256' => $document->checksum_sha256, 'name' => $document->original_name];
                }
                $task->status = 'completed';
                $task->completed_at = now();
            } else {
                $task->status = 'cancelled';
            }
            $task->revision++;
            $task->save();
            $this->record($task, $actor, $action, $before, $data['note'] ?? null, $evidence);

            return $task;
        });
    }

    private function assignee(Application $application, int $userId): void
    {
        $candidate = User::query()->lockForUpdate()->find($userId);
        if (! $candidate || ! $candidate->is_active || $candidate->account_type !== 'staff'
            || $candidate->department_id !== $application->responsible_department_id
            || ! $this->access->applications($candidate, 'work')->whereKey($application->id)->exists()
            || ! $this->access->applications($candidate)->whereKey($application->id)->exists()) {
            throw ValidationException::withMessages(['assignee_id' => 'Pilih pegawai aktif dalam jabatan dan skop rekod yang sama.']);
        }
    }

    private function snapshot(OfficerTask $task): array
    {
        return ['application_id' => $task->application_id, 'department_id' => $task->department_id, 'title' => $task->title,
            'instructions' => $task->instructions, 'assignee_id' => $task->assignee_id,
            'assignee_name' => User::query()->whereKey($task->assignee_id)->value('name'), 'due_date' => $task->due_date?->format('Y-m-d'),
            'status' => $task->status, 'revision' => $task->revision, 'completed_at' => $task->completed_at?->toIso8601String()];
    }

    private function record(OfficerTask $task, User $actor, string $action, array $before, ?string $note, array $evidence): void
    {
        $history = OfficerTaskAction::query()->create(['task_id' => $task->id, 'revision' => $task->revision, 'actor_id' => $actor->id,
            'action' => $action, 'before_values' => $before, 'after_values' => $this->snapshot($task), 'note' => $note, 'evidence' => $evidence]);
        foreach ($evidence as $pin) {
            DB::table('core.officer_task_evidence')->insert(['action_id' => $history->id, 'document_id' => $pin['document_id']]);
        }
        AuditLog::query()->create(['auditable_type' => OfficerTask::class, 'auditable_id' => $task->id,
            'event' => 'officer_task_'.$action, 'actor_id' => $actor->id, 'before_values' => $before,
            'after_values' => $this->snapshot($task) + ['application_id' => $task->application_id, 'title' => $task->title, 'note' => $note, 'evidence' => $evidence],
            'ip_address' => request()?->ip()]);
    }
}
