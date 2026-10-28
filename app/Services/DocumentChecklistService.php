<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistItem;
use App\Models\DocumentRequirement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DocumentChecklistService
{
    public function guard(Application $record, ?string $guard, User $actor, ?string $remarks): void
    {
        match ($guard) {
            null => null,
            'open_document_checklist' => $this->open($record, $actor),
            'complete_document_checklist' => $this->complete($record),
            'return_document_checklist' => $this->returnForCorrection($record, $remarks),
            'resubmit_document_checklist' => $this->resubmit($record),
            default => throw ValidationException::withMessages(['workflow' => 'Pengawal aliran kerja tidak dikenali.']),
        };
    }

    public function assertPermission(Application $record, User $actor, string $action): void
    {
        abort_unless($actor->canAccessApplication($record), 403);
        $template = DB::table('core.workflow_templates')->where('id', $record->workflow_template_id)
            ->where('application_type_id', $record->application_type_id)->where('is_active', true)->first();
        $permission = $template?->{'document_'.$action.'_permission'};
        abort_unless(is_string($permission) && app(CorePermissionService::class)->userCan($actor, $permission, $actor->department_id, $record->application_type_id), 403);
    }

    public function assertVersion(Application $record, int $expected): void
    {
        if ($record->row_version !== $expected) {
            throw ValidationException::withMessages(['document' => 'Rekod telah berubah. Muat semula sebelum meneruskan.']);
        }
    }

    public function checklist(Application $record): DocumentChecklist
    {
        $checklist = DocumentChecklist::query()->where('application_id', $record->id)->first();
        if (! $checklist) {
            throw ValidationException::withMessages(['document' => 'Senarai semak belum diwujudkan daripada katalog yang disahkan.']);
        }

        return $checklist;
    }

    public function assertStage(Application $record, bool $upload = false): void
    {
        if ($record->current_stage !== ApplicationStage::DocumentVerification
            || ! in_array($record->current_status, $upload ? [ApplicationStatus::InReview, ApplicationStatus::ReturnedForAmendment] : [ApplicationStatus::InReview], true)) {
            throw ValidationException::withMessages(['document' => 'Tindakan dokumen tidak tersedia pada peringkat semasa.']);
        }
    }

    public function open(Application $record, User $actor): void
    {
        if (DocumentChecklist::query()->where('application_id', $record->id)->exists()) {
            throw ValidationException::withMessages(['workflow' => 'Senarai semak telah diwujudkan.']);
        }
        $catalogue = DB::table('core.document_catalogues')->where('application_type_id', $record->application_type_id)->lockForUpdate()->first();
        $requirements = DocumentRequirement::query()->with('documentType')->where('application_type_id', $record->application_type_id)
            ->where('is_active', true)->whereNull('permission_category')->whereNull('permission_type')->orderBy('sort_order')->get();
        if (! $catalogue || $requirements->isEmpty() || ! $requirements->contains('is_required', true)
            || $requirements->contains(fn ($requirement) => ! $requirement->documentType->is_active)) {
            throw ValidationException::withMessages(['workflow' => 'Katalog dokumen perlu disahkan oleh pegawai yang diberi kuasa sebelum semakan dimulakan.']);
        }
        $checklist = DocumentChecklist::query()->create([
            'application_id' => $record->id, 'catalogue_version' => $catalogue->version,
            'source_reference' => $catalogue->source_reference, 'opened_by' => $actor->id, 'opened_at' => now(), 'status' => 'open',
        ]);
        foreach ($requirements as $requirement) {
            $checklist->items()->create([
                'document_type_id' => $requirement->document_type_id, 'document_name' => $requirement->documentType->name,
                'allowed_extensions' => $requirement->documentType->allowed_extensions,
                'max_file_size_kb' => $requirement->documentType->max_file_size_kb,
                'is_required' => $requirement->is_required, 'sort_order' => $requirement->sort_order, 'status' => 'pending',
            ]);
        }
    }

    public function initialise(Application $application, int $expected, User $actor): void
    {
        DB::transaction(function () use ($application, $expected, $actor): void {
            $record = Application::query()->lockForUpdate()->findOrFail($application->id);
            $this->assertPermission($record, $actor, 'review');
            $this->assertVersion($record, $expected);
            $this->assertStage($record);
            $this->open($record, $actor);
            $record->increment('row_version');
            $this->audit($record, $actor, 'document_checklist_initialised', [], ['catalogue_version' => $this->checklist($record)->catalogue_version]);
        });
    }

    public function verify(Application $application, DocumentChecklistItem $item, array $data, User $actor): void
    {
        DB::transaction(function () use ($application, $item, $data, $actor): void {
            $record = Application::query()->lockForUpdate()->findOrFail($application->id);
            $this->assertPermission($record, $actor, 'review');
            $this->assertVersion($record, (int) $data['row_version']);
            $this->assertStage($record);
            $checklist = $this->checklist($record);
            $item = DocumentChecklistItem::query()->where('checklist_id', $checklist->id)->findOrFail($item->id);
            if (! in_array($data['status'], ['accepted', 'rejected', 'not_applicable'], true)
                || ($data['status'] === 'rejected' && blank($data['remarks'] ?? null))) {
                throw ValidationException::withMessages(['document' => 'Keputusan sah dan alasan penolakan diperlukan.']);
            }
            if ($data['status'] === 'not_applicable' && ($item->is_required || blank($data['remarks'] ?? null))) {
                throw ValidationException::withMessages(['document' => 'Dokumen wajib tidak boleh dikecualikan; pengecualian dokumen pilihan memerlukan alasan.']);
            }
            $document = $record->documents()->where('document_type_id', $item->document_type_id)->where('is_current', true)->first();
            if ($data['status'] !== 'not_applicable' && (! $document || $document->id !== ($data['document_id'] ?? null))) {
                throw ValidationException::withMessages(['document' => 'Semak revisi dokumen semasa sebelum mengesahkannya.']);
            }
            if ($document && ! $this->cleanDocument($document)) {
                throw ValidationException::withMessages(['document' => 'Dokumen belum melepasi imbasan keselamatan atau fail tidak tersedia.']);
            }
            $before = ['status' => $item->status, 'document_id' => $item->document_id, 'remarks' => $item->remarks];
            $item->update(['status' => $data['status'], 'document_id' => $document?->id,
                'remarks' => $data['remarks'] ?? null, 'checked_by' => $actor->id, 'checked_at' => now()]);
            if ($document && $data['status'] !== 'not_applicable') {
                $document->update(['verification_status' => DocumentVerificationStatus::from($data['status']),
                    'verification_remarks' => $data['remarks'] ?? null, 'verified_by' => $actor->id, 'verified_at' => now()]);
            }
            $record->increment('row_version');
            $this->audit($record, $actor, 'document_verified', $before, ['item_id' => $item->id, 'document_id' => $document?->id, 'status' => $item->status, 'remarks' => $item->remarks]);
        });
    }

    public function audit(Application $record, User $actor, string $event, array $before, array $after): void
    {
        AuditLog::query()->create(['auditable_type' => Application::class, 'auditable_id' => $record->id,
            'event' => $event, 'actor_id' => $actor->id, 'before_values' => $before,
            'after_values' => $after + ['row_version' => $record->row_version], 'ip_address' => request()?->ip()]);
    }

    private function complete(Application $record): void
    {
        $this->assertStage($record);
        $checklist = $this->checklist($record);
        $items = $checklist->items()->get();
        $documents = $record->currentDocuments()->get()->keyBy('document_type_id');
        if ($checklist->status !== 'open' || $items->isEmpty() || ! $items->contains('is_required', true)) {
            throw ValidationException::withMessages(['workflow' => 'Senarai semak sah diperlukan sebelum pengesahan selesai.']);
        }
        foreach ($items as $item) {
            $document = $documents->get($item->document_type_id);
            if (! $item->is_required && (! $document || $item->status === 'not_applicable')) {
                continue;
            }
            if ($item->status !== 'accepted' || ! $document || $item->document_id !== $document->id
                || $document->verification_status !== DocumentVerificationStatus::Accepted || ! $this->cleanDocument($document)) {
                throw ValidationException::withMessages(['workflow' => 'Semua dokumen wajib dan dokumen pilihan yang dikemukakan mesti disahkan pada revisi semasa.']);
            }
        }
        $checklist->update(['status' => 'completed']);
    }

    private function cleanDocument(ApplicationDocument $document): bool
    {
        $disk = Storage::disk('local');
        if ($document->scan_status !== 'clean' || ! $disk->exists($document->storage_path)) {
            return false;
        }
        $hash = hash_file('sha256', $disk->path($document->storage_path));

        return is_string($hash) && hash_equals($document->checksum_sha256, $hash);
    }

    private function returnForCorrection(Application $record, ?string $remarks): void
    {
        $this->assertStage($record);
        if (blank($remarks)) {
            throw ValidationException::withMessages(['remarks' => 'Alasan pemulangan diperlukan.']);
        }
        $this->checklist($record)->update(['status' => 'returned']);
    }

    private function resubmit(Application $record): void
    {
        $checklist = $this->checklist($record);
        if ($checklist->status !== 'returned') {
            throw ValidationException::withMessages(['workflow' => 'Permohonan belum dipulangkan untuk pembetulan.']);
        }
        $documents = $record->currentDocuments()->get()->keyBy('document_type_id');
        foreach ($checklist->items as $item) {
            if (($item->is_required && ! $documents->has($item->document_type_id)) || $item->status === 'rejected') {
                throw ValidationException::withMessages(['workflow' => 'Kemukakan dokumen wajib dan gantikan dokumen yang ditolak sebelum menghantar semula.']);
            }
        }
        $checklist->update(['status' => 'open']);
    }
}
