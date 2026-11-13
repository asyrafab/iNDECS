<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\IndexLibraryVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Queues text indexing for every active library document's current version that has no chunks yet (ADR-369). */
final class IndexLibrary extends Command
{
    protected $signature = 'amira:index-library';

    protected $description = 'Index the text of current library document versions for AMIRA answers';

    public function handle(): int
    {
        $ids = DB::table('core.library_document_versions as v')
            ->join('core.library_documents as d', fn ($join) => $join->on('d.id', '=', 'v.document_id')->on('d.current_version', '=', 'v.version'))
            ->where('d.status', 'active')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('core.library_chunks as c')->whereColumn('c.version_id', 'v.id'))
            ->pluck('v.id');
        foreach ($ids as $id) {
            IndexLibraryVersion::dispatch((int) $id);
        }
        $this->info("Dihantar untuk indeks: {$ids->count()} versi dokumen.");

        return self::SUCCESS;
    }
}
