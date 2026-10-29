<?php

declare(strict_types=1);

namespace App\Services\Osc;

use App\Contracts\DocumentScanner;
use App\Models\AuditLog;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\AgendaItemPaper;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class AgendaPaperService
{
    private const MAX_BYTES = 50 * 1024 * 1024;

    public function __construct(private readonly DocumentScanner $scanner) {}

    public function upload(AgendaItem $item, UploadedFile $file, User $actor): AgendaItemPaper
    {
        abort_unless(
            OscAccess::canManage($actor) || $item->presenter_id === $actor->id || $item->proposed_by === $actor->id,
            403,
        );
        $realPath = $file->getRealPath();
        if (! $file->isValid() || $realPath === false || $file->getSize() === 0 || $file->getSize() > self::MAX_BYTES
            || (new \finfo(FILEINFO_MIME_TYPE))->file($realPath) !== 'application/pdf'
            || strlen($file->getClientOriginalName()) > 255) {
            throw ValidationException::withMessages(['paper' => 'Kertas kerja mestilah fail PDF tidak melebihi 50 MB.']);
        }

        $path = null;
        try {
            return DB::transaction(function () use ($item, $file, $actor, $realPath, &$path): AgendaItemPaper {
                $record = AgendaItem::query()->lockForUpdate()->findOrFail($item->id);
                $version = ((int) $record->papers()->reorder()->max('version')) + 1;
                $path = 'osc/papers/'.$record->meeting_id.'/'.$record->id.'/v'.$version.'.pdf';
                if (! Storage::disk('local')->put($path, (string) file_get_contents($realPath))) {
                    throw ValidationException::withMessages(['paper' => 'Dokumen tidak dapat disimpan.']);
                }
                try {
                    $this->scanner->assertClean(Storage::disk('local')->path($path));
                } catch (Throwable) {
                    throw ValidationException::withMessages(['paper' => 'Dokumen gagal imbasan keselamatan dan tidak diterima.']);
                }
                $sha256 = hash_file('sha256', $realPath);
                $paper = $record->papers()->create([
                    'version' => $version, 'original_name' => $file->getClientOriginalName(), 'path' => $path,
                    'mime_type' => 'application/pdf', 'size_bytes' => $file->getSize(), 'sha256' => $sha256,
                    'uploaded_by' => $actor->id,
                ]);
                $this->audit($record, 'osc_paper_uploaded', $actor, null,
                    ['paper_id' => $paper->id, 'version' => $version, 'sha256' => $sha256]);

                return $paper;
            });
        } catch (Throwable $error) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }

    public function download(AgendaItemPaper $paper, User $actor): StreamedResponse
    {
        $item = $paper->item()->with('meeting')->firstOrFail();
        abort_unless(OscAccess::canViewMeeting($actor, $item->meeting), 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($paper->path), 409, 'Fail kertas kerja tidak sah.');
        $stream = $disk->readStream($paper->path);
        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);
        abort_unless(hash_equals($paper->sha256, hash_final($context)), 409, 'Fail kertas kerja tidak sah.');

        $this->audit($item, 'osc_paper_viewed', $actor, null, ['paper_id' => $paper->id, 'version' => $paper->version]);

        return $disk->download($paper->path, $paper->original_name, ['Content-Type' => 'application/pdf']);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private function audit(AgendaItem $item, string $event, User $actor, ?array $before, array $after): void
    {
        AuditLog::query()->create([
            'auditable_type' => AgendaItem::class, 'auditable_id' => $item->id, 'event' => $event,
            'actor_id' => $actor->id, 'before_values' => $before, 'after_values' => $after,
            'ip_address' => request()?->ip(),
        ]);
    }
}
