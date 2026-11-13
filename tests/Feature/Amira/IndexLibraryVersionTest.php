<?php

namespace Tests\Feature\Amira;

use App\Jobs\IndexLibraryVersion;
use App\Models\LibraryDocument;
use App\Models\LibraryDocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Library text indexing with the real pdftotext and Ghostscript where installed.
 *
 * @group local-tools
 */
class IndexLibraryVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    private function version(string $bytes): LibraryDocumentVersion
    {
        $pp = User::factory()->create(['department_id' => 'JBANG', 'role' => 'penolong_pengarah']);
        $document = new LibraryDocument;
        $document->forceFill(['id' => (string) Str::ulid(), 'department_id' => 'JBANG', 'title' => 'Garis Panduan', 'category' => 'garis_panduan',
            'visibility' => 'public', 'status' => 'active', 'current_version' => 1, 'created_by' => $pp->id])->save();
        Storage::disk('local')->put("library/JBANG/{$document->id}/v1.pdf", $bytes);
        $version = new LibraryDocumentVersion;
        $version->forceFill(['document_id' => $document->id, 'version' => 1, 'original_name' => 'g.pdf', 'path' => "library/JBANG/{$document->id}/v1.pdf",
            'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'uploaded_by' => $pp->id, 'created_at' => now()])->save();

        return $version;
    }

    public function test_two_page_pdf_is_indexed_per_page(): void
    {
        $gs = (string) config('kaunter.precheck.ghostscript');
        foreach ([(string) config('kaunter.precheck.pdftotext'), $gs] as $tool) {
            if (! is_file($tool) && (new ExecutableFinder)->find($tool) === null) {
                $this->markTestSkipped("{$tool} is not installed.");
            }
        }
        $dir = sys_get_temp_dir().'/library-index-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $ps = $dir.'/two.ps';
        file_put_contents($ps, "%!PS\n/Helvetica findfont 14 scalefont setfont\n72 700 moveto (ANJAKAN HADAPAN MINIMUM ENAM METER) show showpage\n72 700 moveto (KETINGGIAN PAGAR MAKSIMUM) show showpage\n");
        (new Process([$gs, '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dQUIET', '-sDEVICE=pdfwrite', '-sOutputFile='.$dir.'/two.pdf', $ps]))->mustRun();
        $version = $this->version((string) file_get_contents($dir.'/two.pdf'));
        array_map('unlink', glob($dir.'/*') ?: []);
        rmdir($dir);

        (new IndexLibraryVersion($version->id))->handle();

        $chunks = DB::table('core.library_chunks')->where('version_id', $version->id)->orderBy('chunk_no')->get();
        $this->assertSame([1, 2], $chunks->pluck('page')->map(fn ($page) => (int) $page)->all());
        $this->assertStringContainsString('ANJAKAN', $chunks[0]->body);
        $this->assertStringContainsString('PAGAR', $chunks[1]->body);
    }

    public function test_missing_pdftotext_leaves_no_chunks_without_failing(): void
    {
        config(['kaunter.precheck.pdftotext' => sys_get_temp_dir().'/no-such-pdftotext']);
        $version = $this->version('%PDF-1.4 not really');
        (new IndexLibraryVersion($version->id))->handle();
        $this->assertSame(0, DB::table('core.library_chunks')->count());
    }
}
