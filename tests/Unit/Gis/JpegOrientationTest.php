<?php

declare(strict_types=1);

namespace Tests\Unit\Gis;

use App\Services\Gis\JpegOrientation;
use PHPUnit\Framework\TestCase;
use Tests\Feature\Osc\LayoutImageTest;

final class JpegOrientationTest extends TestCase
{
    public function test_reads_orientation_in_both_byte_orders(): void
    {
        $this->assertSame(6, JpegOrientation::fromBytes(LayoutImageTest::jpegWithOrientation(6)));
        $this->assertSame(8, JpegOrientation::fromBytes(LayoutImageTest::jpegWithOrientation(8, true)));
        $this->assertSame(1, JpegOrientation::fromBytes(LayoutImageTest::jpegWithOrientation(1)));
    }

    public function test_missing_or_malformed_metadata_is_null(): void
    {
        $plain = LayoutImageTest::jpegWithOrientation(6);
        $plain = substr($plain, 0, 2).substr($plain, 2 + 2 + unpack('n', substr($plain, 4, 2))[1]); // drop the APP1 segment
        $this->assertNull(JpegOrientation::fromBytes($plain));
        $this->assertNull(JpegOrientation::fromBytes("\x89PNG\r\n\x1a\n"));
        $this->assertNull(JpegOrientation::fromBytes("\xFF\xD8\xFF\xE1\x00\x10Exif\0\0MM\0\x2A\xFF\xFF\xFF\xFF"));
        $this->assertNull(JpegOrientation::fromBytes(''));
    }
}
