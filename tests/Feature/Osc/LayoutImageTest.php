<?php

declare(strict_types=1);

namespace Tests\Feature\Osc;

use App\Contracts\DocumentScanner;
use App\Enums\Osc\TablingPurpose;
use App\Models\AuditLog;
use App\Models\Osc\AgendaItem;
use App\Models\Osc\LayoutImage;
use App\Models\Osc\Meeting;
use App\Models\User;
use App\Services\Osc\AgendaService;
use App\Services\Osc\LayoutImageService;
use App\Services\Osc\MeetingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LayoutImageTest extends TestCase
{
    use RefreshDatabase;

    private const STALE = 'Rekod telah berubah. Muat semula sebelum meneruskan.';

    private User $secretary;

    private User $chair;

    private User $planner;

    private User $member;

    private Meeting $meeting;

    private AgendaItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
        Notification::fake();
        $this->secretary = User::factory()->create(['role' => 'osc_secretary', 'department_id' => 'JPBD']);
        $this->chair = User::factory()->create(['role' => 'senior_building_officer', 'department_id' => 'JBANG']);
        $this->planner = User::factory()->create(['role' => 'planning_officer', 'department_id' => 'JPBD']);
        $this->member = User::factory()->create(['role' => 'intake_officer', 'department_id' => 'JBANG']);
        $this->meeting = app(MeetingService::class)->create([
            'number' => 'Bil. 21/2026', 'scheduled_at' => now()->addWeek(), 'venue' => 'Bilik OSC',
            'proposal_cutoff_at' => now()->addDays(2), 'paper_cutoff_at' => now()->addDays(4),
            'chair_id' => $this->chair->id, 'secretary_id' => $this->secretary->id,
        ], $this->secretary);
        app(MeetingService::class)->setMembers($this->meeting, [
            'chair_id' => $this->chair->id, 'secretary_id' => $this->secretary->id, 'member_ids' => [$this->member->id],
        ], $this->meeting->row_version, $this->secretary);
        $this->meeting->refresh();
        $this->item = app(AgendaService::class)->proposeStandalone($this->meeting, [
            'standalone_type' => 'Penamaan Jalan & Taman', 'title' => 'A', 'reference' => null,
            'purpose' => TablingPurpose::Permohonan, 'presenter_id' => $this->planner->id, 'department_id' => 'JPBD',
        ], $this->secretary);
        $this->scanner(null);
    }

    private function scanner(?string $failure): void
    {
        $this->app->instance(DocumentScanner::class, new class($failure) implements DocumentScanner
        {
            public function __construct(private readonly ?string $failure) {}

            public function assertClean(string $path): void
            {
                if ($this->failure !== null) {
                    throw new \RuntimeException($this->failure);
                }
            }
        });
    }

    private function png(int $width = 400, int $height = 200, string $name = 'pelan.png'): UploadedFile
    {
        $row = "\0".str_repeat("\x80\x80\x80", $width);
        $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $bytes = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress(str_repeat($row, $height))).$chunk('IEND', '');

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    /** Three points mapping a 400x200 image onto roughly 400 m x 200 m in Seberang Perai. */
    private function points(): array
    {
        return [
            ['pixel' => [0, 0], 'map' => [100.4000, 5.4020]],
            ['pixel' => [400, 0], 'map' => [100.4036, 5.4020]],
            ['pixel' => [0, 200], 'map' => [100.4000, 5.4002]],
        ];
    }

    private function place(?User $actor = null, array $overrides = [])
    {
        return $this->actingAs($actor ?? $this->planner)->post(route('osc.layout.store', $this->item), $overrides + [
            'layout' => $this->png(), 'control_points' => $this->points(),
        ]);
    }

    private function itemLayout(): LayoutImage
    {
        return LayoutImage::query()->where('owner_type', 'agenda_item')->where('owner_id', $this->item->id)->firstOrFail();
    }

    public function test_presenter_places_layout_with_three_points(): void
    {
        $this->place()->assertSessionHasNoErrors()->assertRedirect();

        $layout = $this->itemLayout();
        $this->assertSame([400, 200, 'image/png', $this->planner->id, 1], [$layout->width, $layout->height, $layout->mime_type, $layout->placed_by, $layout->row_version]);
        Storage::disk('local')->assertExists($layout->path);
        $this->assertSame(hash_file('sha256', Storage::disk('local')->path($layout->path)), $layout->sha256);

        $corners = json_decode(DB::selectOne('SELECT ST_AsGeoJSON(corners) AS g FROM core.osc_layout_images WHERE id = ?', [$layout->id])->g, true)['coordinates'][0];
        $this->assertCount(5, $corners);
        $this->assertEqualsWithDelta(100.4000, $corners[0][0], 0.0001); // top-left
        $this->assertEqualsWithDelta(5.4020, $corners[0][1], 0.0001);
        $this->assertEqualsWithDelta(100.4036, $corners[1][0], 0.0001); // top-right
        $this->assertEqualsWithDelta(5.4002, $corners[3][1], 0.0001);   // bottom-left
        [$west, $south, $east, $north] = config('gis.region_bbox');
        foreach ($corners as [$lon, $lat]) {
            $this->assertTrue($lon >= $west && $lon <= $east && $lat >= $south && $lat <= $north);
        }
        $audit = AuditLog::query()->where('event', 'osc_layout_placed')->firstOrFail();
        $this->assertSame($this->item->id, $audit->auditable_id);
        $this->assertNull($audit->before_values);
        $this->assertSame($layout->sha256, $audit->after_values['sha256']);
        $this->assertCount(4, $audit->after_values['corners']);
    }

    public function test_replacing_keeps_the_old_file_checks_the_version_and_audits(): void
    {
        $this->place();
        $first = $this->itemLayout();
        $oldPath = $first->path;
        $oldSha = $first->sha256;

        $this->place($this->planner, ['layout' => $this->png(400, 300)])->assertSessionHasErrors(['layout' => self::STALE]);
        $this->place($this->planner, ['layout' => $this->png(400, 300), 'expected_version' => 5])->assertSessionHasErrors(['layout' => self::STALE]);

        $this->place($this->secretary, ['layout' => $this->png(400, 300), 'expected_version' => 1])->assertSessionHasNoErrors();

        $this->assertSame(1, LayoutImage::query()->where('owner_id', $this->item->id)->count());
        $second = $this->itemLayout();
        $this->assertSame([400, 300, 2, $this->secretary->id], [$second->width, $second->height, $second->row_version, $second->placed_by]);
        $this->assertNotSame($oldPath, $second->path);
        Storage::disk('local')->assertExists($oldPath);
        Storage::disk('local')->assertExists($second->path);
        $audit = AuditLog::query()->where('event', 'osc_layout_placed')->latest('id')->firstOrFail();
        $this->assertSame($oldSha, $audit->before_values['sha256']);
        $this->assertSame($second->sha256, $audit->after_values['sha256']);
        $this->assertCount(4, $audit->before_values['corners']);
    }

    public function test_only_presenter_proposer_or_manager_may_place(): void
    {
        $this->place($this->member)->assertForbidden();
        $this->place($this->chair)->assertForbidden();
        $this->actingAs($this->member)->get(route('osc.layout.edit', $this->item))->assertForbidden();
        $this->assertDatabaseCount('core.osc_layout_images', 0);
        $this->place($this->secretary)->assertSessionHasNoErrors();
        $this->actingAs($this->planner)->get(route('osc.layout.edit', $this->item))->assertOk()->assertSee('Simpan kedudukan');
    }

    public function test_collinear_points_rejected(): void
    {
        $this->place(null, ['control_points' => [
            ['pixel' => [0, 0], 'map' => [100.4000, 5.4020]],
            ['pixel' => [100, 100], 'map' => [100.4010, 5.4010]],
            ['pixel' => [200, 200], 'map' => [100.4020, 5.4000]],
        ]])->assertSessionHasErrors(['control_points' => 'Titik kawalan tidak sah.']);
        $this->assertDatabaseCount('core.osc_layout_images', 0);
        Storage::disk('local')->assertDirectoryEmpty('osc/layouts');
    }

    public function test_layout_outside_the_region_is_rejected(): void
    {
        $this->place(null, ['control_points' => [
            ['pixel' => [0, 0], 'map' => [101.0000, 5.4020]],
            ['pixel' => [400, 0], 'map' => [101.0036, 5.4020]],
            ['pixel' => [0, 200], 'map' => [101.0000, 5.4002]],
        ]])->assertSessionHasErrors(['control_points' => 'Lokasi berada di luar kawasan MBSP.']);
        $this->assertDatabaseCount('core.osc_layout_images', 0);
    }

    public function test_malformed_input_is_rejected(): void
    {
        $this->place(null, ['control_points' => array_slice($this->points(), 0, 2)])->assertSessionHasErrors('control_points');
        $bad = $this->points();
        $bad[0]['pixel'] = ['x', 1];
        $this->place(null, ['control_points' => $bad])->assertSessionHasErrors('control_points');
        $this->place(null, ['layout' => UploadedFile::fake()->createWithContent('x.png', 'not an image')])->assertSessionHasErrors('layout');
        $gif = base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
        $this->place(null, ['layout' => UploadedFile::fake()->createWithContent('x.png', $gif)])->assertSessionHasErrors('layout');
        $this->assertDatabaseCount('core.osc_layout_images', 0);
    }

    public function test_infected_image_refused(): void
    {
        $this->scanner('infected');

        $this->place()->assertSessionHasErrors(['layout' => 'Imej gagal imbasan keselamatan dan tidak diterima.']);
        $this->assertDatabaseCount('core.osc_layout_images', 0);
        Storage::disk('local')->assertDirectoryEmpty('osc/layouts');
        $this->assertDatabaseMissing('audit_logs', ['event' => 'osc_layout_placed']);
    }

    public function test_layout_image_access_follows_item_visibility(): void
    {
        $this->place();
        $url = route('osc.layout.show', $this->itemLayout());

        // An endorser of another department, and a member before the item is published, are refused.
        $this->actingAs($this->chair)->get($url)->assertForbidden();
        $this->actingAs($this->member)->get($url)->assertForbidden();
        app(AgendaService::class)->accept($this->item->fresh(), $this->item->fresh()->row_version, $this->secretary);
        app(AgendaService::class)->publish($this->meeting, $this->meeting->fresh()->row_version, $this->secretary);
        $this->actingAs($this->member)->get($url)->assertOk();

        $response = $this->actingAs($this->secretary)->get($url)->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->actingAs($this->planner)->get($url)->assertOk();
    }

    private function giveItemBoundary(): void
    {
        DB::update("UPDATE core.osc_agenda_items SET boundary = ST_GeomFromText('MULTIPOLYGON(((100.4 5.4,100.401 5.4,100.401 5.401,100.4 5.401,100.4 5.4)))', 4326) WHERE id = ?", [$this->item->id]);
    }

    public function test_item_layout_is_in_map_data(): void
    {
        $this->giveItemBoundary();
        $this->place();

        $props = collect($this->actingAs($this->secretary)->getJson(route('osc.meetings.map-data', $this->meeting))->assertOk()->json('features'))
            ->firstWhere('properties.kind', 'item')['properties'];
        $this->assertSame(route('osc.layout.show', $this->itemLayout()), $props['layout_url']);
        $this->assertCount(4, $props['layout_corners']);
        $this->assertEqualsWithDelta(100.4, $props['layout_corners'][0][0], 0.0001);
        $this->assertEqualsWithDelta(5.402, $props['layout_corners'][0][1], 0.0001);
        $this->assertEqualsWithDelta(5.4002, $props['layout_corners'][3][1], 0.0001);
    }

    public function test_item_without_other_geometry_is_located_by_its_layout(): void
    {
        $this->place()->assertSessionHasNoErrors();

        $json = $this->actingAs($this->secretary)->getJson(route('osc.meetings.map-data', $this->meeting))->assertOk()->json();
        $feature = collect($json['features'])->firstWhere('properties.kind', 'item');
        $this->assertNotNull($feature);
        $this->assertSame('layout', $feature['properties']['geometry_source']);
        $this->assertSame('Polygon', $feature['geometry']['type']);
        $ring = $feature['geometry']['coordinates'][0];
        $this->assertCount(5, $ring);
        $this->assertSame($ring[0], $ring[4]);
        $this->assertEqualsWithDelta(100.4, $ring[0][0], 0.0001);
        $this->assertEqualsWithDelta(5.402, $ring[0][1], 0.0001);
        $this->assertSame([], $json['properties']['unmapped']);

        $this->giveItemBoundary(); // A drawn boundary still wins over the layout footprint.
        $feature = collect($this->actingAs($this->secretary)->getJson(route('osc.meetings.map-data', $this->meeting))->json('features'))
            ->firstWhere('properties.kind', 'item');
        $this->assertSame('item', $feature['properties']['geometry_source']);
    }

    private function legacyLayout(): LayoutImage
    {
        DB::insert(
            "INSERT INTO core.osc_legacy_papers (id, title, status_code, year, imported_at, geom) VALUES (77, 'Lama', '1', '2019', now(), ST_SetSRID(ST_MakePoint(100.4005, 5.4005), 4326))",
        );
        Storage::disk('local')->put('osc/layouts/legacy/x.png', $this->png()->getContent());
        DB::insert(
            "INSERT INTO core.osc_layout_images (id, owner_type, owner_id, path, mime_type, width, height, sha256, corners, source_srid, row_version, created_at, updated_at)
             VALUES (?, 'legacy', '77', 'osc/layouts/legacy/x.png', 'image/png', 400, 200, ?, ST_GeomFromText('POLYGON((100.4 5.402,100.4036 5.402,100.4036 5.4002,100.4 5.4002,100.4 5.402))', 4326), 3857, 1, now(), now())",
            [(string) Str::ulid(), str_repeat('a', 64)],
        );

        return LayoutImage::query()->where('owner_type', 'legacy')->firstOrFail();
    }

    public function test_legacy_layout_hidden_without_view_grant(): void
    {
        $legacy = $this->legacyLayout();
        $url = route('osc.layout.show', $legacy);

        $this->actingAs($this->member)->get($url)->assertForbidden();
        $this->actingAs($this->secretary)->get($url)->assertOk();
        $this->actingAs($this->chair)->get($url)->assertOk(); // holds osc.meeting.view
    }

    public function test_legacy_layout_rides_on_the_past_decision_feature_for_viewers(): void
    {
        $legacy = $this->legacyLayout();
        $this->giveItemBoundary();

        $features = $this->actingAs($this->secretary)->getJson(route('osc.meetings.map-data', $this->meeting))->assertOk()->json('features');
        $past = collect($features)->firstWhere('properties.kind', 'legacy')['properties'];
        $this->assertSame(route('osc.layout.show', $legacy), $past['layout_url']);
        $this->assertCount(4, $past['layout_corners']);
    }

    public function test_an_owner_has_at_most_one_layout_row(): void
    {
        $this->legacyLayout();

        $this->expectException(UniqueConstraintViolationException::class);
        DB::insert(
            "INSERT INTO core.osc_layout_images (id, owner_type, owner_id, path, mime_type, width, height, sha256, corners, row_version, created_at, updated_at)
             VALUES (?, 'legacy', '77', 'osc/layouts/legacy/y.png', 'image/png', 400, 200, ?, ST_GeomFromText('POLYGON((100.4 5.402,100.4036 5.402,100.4036 5.4002,100.4 5.4002,100.4 5.402))', 4326), 1, now(), now())",
            [(string) Str::ulid(), str_repeat('b', 64)],
        );
    }

    public function test_oversized_pixel_count_refused_and_nothing_stored(): void
    {
        $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $bytes = chr(137).'PNG'.chr(13).chr(10).chr(26).chr(10).$chunk('IHDR', pack('NNCCCCC', 6001, 6001, 8, 2, 0, 0, 0)).$chunk('IDAT', gzcompress(chr(0))).$chunk('IEND', '');

        $this->place(null, ['layout' => UploadedFile::fake()->createWithContent('big.png', $bytes)])
            ->assertSessionHasErrors(['layout' => 'Imej terlalu besar (maksimum 36 juta piksel).']);
        $this->assertDatabaseCount('core.osc_layout_images', 0);
        Storage::disk('local')->assertDirectoryEmpty('osc/layouts');
    }

    public function test_scan_failure_on_replace_keeps_row_and_leaves_no_new_file(): void
    {
        $this->place();
        $first = $this->itemLayout();
        $before = Storage::disk('local')->allFiles('osc/layouts');
        $this->scanner('infected');

        $this->place($this->planner, ['layout' => $this->png(400, 300), 'expected_version' => 1])
            ->assertSessionHasErrors(['layout' => 'Imej gagal imbasan keselamatan dan tidak diterima.']);

        $this->assertSame($before, Storage::disk('local')->allFiles('osc/layouts'));
        $this->assertSame([1, $first->sha256], [$this->itemLayout()->row_version, $this->itemLayout()->sha256]);
        $this->assertSame(1, AuditLog::query()->where('event', 'osc_layout_placed')->count());
    }

    private const JPEG_1X1 = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAMCAgICAgMCAgIDAwMDBAYEBAQEBAgGBgUGCQgKCgkICQkKDA8MCgsOCwkJDRENDg8QEBEQCgwSExIQEw8QEBD/yQALCAABAAEBAREA/8wABgAQEAX/2gAIAQEAAD8A0s8g/9k=';

    /** The 1x1 JPEG with an APP1 Exif segment (IFD0 holding only Orientation) inserted after SOI. */
    public static function jpegWithOrientation(int $orientation, bool $littleEndian = false): string
    {
        $s = fn (int $v): string => pack($littleEndian ? 'v' : 'n', $v);
        $l = fn (int $v): string => pack($littleEndian ? 'V' : 'N', $v);
        $tiff = ($littleEndian ? 'II' : 'MM').$s(42).$l(8).$s(1).$s(0x0112).$s(3).$l(1).$s($orientation).$s(0).$l(0);
        $payload = "Exif\0\0".$tiff;
        $jpeg = base64_decode(self::JPEG_1X1);

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);
    }

    private function placeJpeg(string $bytes)
    {
        return $this->place(null, ['layout' => UploadedFile::fake()->createWithContent('p.jpg', $bytes), 'control_points' => [
            ['pixel' => [0, 0], 'map' => [100.4000, 5.4020]],
            ['pixel' => [1, 0], 'map' => [100.4036, 5.4020]],
            ['pixel' => [0, 1], 'map' => [100.4000, 5.4002]],
        ]]);
    }

    public function test_jpeg_with_rotating_exif_orientation_is_refused(): void
    {
        $this->assertSame([1, 1], array_slice(getimagesizefromstring(self::jpegWithOrientation(6)), 0, 2)); // still a valid JPEG

        $this->placeJpeg(self::jpegWithOrientation(6))
            ->assertSessionHasErrors(['layout' => 'Imej mempunyai orientasi EXIF; simpan semula tanpa putaran.']);
        $this->placeJpeg(self::jpegWithOrientation(3, true))
            ->assertSessionHasErrors(['layout' => 'Imej mempunyai orientasi EXIF; simpan semula tanpa putaran.']);
        $this->assertDatabaseCount('core.osc_layout_images', 0);

        $this->placeJpeg(self::jpegWithOrientation(1))->assertSessionHasNoErrors();
        $this->assertSame([1, 1], [$this->itemLayout()->width, $this->itemLayout()->height]);
    }

    public function test_jpeg_upload_is_placed(): void
    {
        $jpeg = base64_decode(self::JPEG_1X1);
        $this->place(null, ['layout' => UploadedFile::fake()->createWithContent('p.jpg', $jpeg), 'control_points' => [
            ['pixel' => [0, 0], 'map' => [100.4000, 5.4020]],
            ['pixel' => [1, 0], 'map' => [100.4036, 5.4020]],
            ['pixel' => [0, 1], 'map' => [100.4000, 5.4002]],
        ]])->assertSessionHasNoErrors();

        $layout = $this->itemLayout();
        $this->assertSame([1, 1, 'image/jpeg'], [$layout->width, $layout->height, $layout->mime_type]);
        $this->assertStringEndsWith('.jpg', $layout->path);
    }

    public function test_missing_stored_file_is_404(): void
    {
        $this->place();
        $layout = $this->itemLayout();
        Storage::disk('local')->delete($layout->path);

        $this->actingAs($this->secretary)->get(route('osc.layout.show', $layout))->assertNotFound();
    }

    public function test_application_owner_layout_is_forbidden_for_everyone(): void
    {
        $this->legacyLayout();
        DB::update("UPDATE core.osc_layout_images SET owner_type = 'application' WHERE owner_type = 'legacy'");
        $layout = LayoutImage::query()->where('owner_type', 'application')->firstOrFail();

        foreach ([$this->secretary, $this->chair, $this->planner, $this->member] as $user) {
            $this->actingAs($user)->get(route('osc.layout.show', $layout))->assertForbidden();
        }
    }

    public function test_map_data_without_view_grant_has_no_legacy_layout(): void
    {
        $this->legacyLayout();
        $this->giveItemBoundary();
        app(AgendaService::class)->accept($this->item->fresh(), $this->item->fresh()->row_version, $this->secretary);
        app(AgendaService::class)->publish($this->meeting, $this->meeting->fresh()->row_version, $this->secretary);

        $features = $this->actingAs($this->member)->getJson(route('osc.meetings.map-data', $this->meeting))->assertOk()->json('features');
        $this->assertNotEmpty($features);
        foreach ($features as $feature) {
            $this->assertNotSame('legacy', $feature['properties']['kind']);
            $this->assertNull($feature['properties']['layout_url'] ?? null);
            $this->assertNull($feature['properties']['layout_corners'] ?? null);
        }
    }

    public function test_attempt_losing_a_concurrent_placement_never_deletes_the_committed_file(): void
    {
        $this->place();
        $test = $this;
        $this->app->instance(DocumentScanner::class, new class($test) implements DocumentScanner
        {
            private bool $inside = false;

            public function __construct(private readonly LayoutImageTest $test) {}

            public function assertClean(string $path): void
            {
                if ($this->inside) {
                    return;
                }
                $this->inside = true;
                // While attempt A is being scanned, attempt B (same bytes, same version) commits completely.
                $this->test->commitConcurrentPlacement();
            }
        });

        $this->place($this->planner, ['layout' => $this->png(400, 300), 'expected_version' => 1])
            ->assertSessionHasErrors(['layout' => self::STALE]);

        $layout = $this->itemLayout();
        $this->assertSame([2, $this->secretary->id], [$layout->row_version, $layout->placed_by]);
        $this->assertSame('osc/layouts/items/'.$this->item->id.'/'.$layout->sha256.'.png', $layout->path);
        Storage::disk('local')->assertExists($layout->path);
        $this->assertSame([], Storage::disk('local')->allFiles('osc/layouts/tmp'));
    }

    public function commitConcurrentPlacement(): void
    {
        app(LayoutImageService::class)->place($this->item, $this->png(400, 300), $this->points(), 1, $this->secretary);
    }
}
