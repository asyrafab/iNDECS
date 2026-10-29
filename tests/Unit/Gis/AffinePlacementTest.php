<?php

declare(strict_types=1);

namespace Tests\Unit\Gis;

use App\Services\Gis\AffinePlacement;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AffinePlacementTest extends TestCase
{
    private function assertCorners(array $expected, array $actual, float $delta = 1e-6): void
    {
        $this->assertCount(4, $actual);
        foreach ($expected as $i => [$x, $y]) {
            $this->assertEqualsWithDelta($x, $actual[$i][0], $delta, "x of corner {$i}");
            $this->assertEqualsWithDelta($y, $actual[$i][1], $delta, "y of corner {$i}");
        }
    }

    public function test_world_file_corners_without_rotation(): void
    {
        $corners = AffinePlacement::fromWorldFile([0.5, 0, 0, -0.5, 1000.25, 2000.25], 40, 20);

        // The world file references pixel centres, so the outer edge sits half a pixel out.
        $this->assertCorners([[1000.0, 2000.5], [1020.0, 2000.5], [1020.0, 1990.5], [1000.0, 1990.5]], $corners, 1e-9);
    }

    public function test_world_file_corners_with_rotation_match_mjkp_sample(): void
    {
        [$a, $d, $b, $e, $c, $f] = [0.580926992306688870, 0.517903356834502170, 0.517903356834502170, -0.580926992306688870, 12791.626995539071, -9240.3983601669079];
        $corners = AffinePlacement::fromWorldFile([$a, $d, $b, $e, $c, $f], 100, 100);

        $expected = [];
        foreach ([[0, 0], [100, 0], [100, 100], [0, 100]] as [$col, $row]) {
            $expected[] = [$a * $col + $b * $row + $c - ($a + $b) / 2, $d * $col + $e * $row + $f - ($d + $e) / 2];
        }
        $this->assertCorners($expected, $corners);
        // Spot check against hand-computed values: top-left = C - (A+B)/2, F - (D+E)/2.
        $this->assertEqualsWithDelta(12791.626995539071 - 0.549415179614, $corners[0][0], 1e-6);
        $this->assertEqualsWithDelta(-9240.3983601669079 + 0.031511776388, $corners[0][1], 1e-6);
    }

    public function test_control_points_round_trip(): void
    {
        $w = [0.58, 0.52, 0.51, -0.59, 12791.6, -9240.4];
        [$a, $d, $b, $e, $c, $f] = $w;
        $pixel = [[3, 5], [90, 12], [20, 70]];
        $map = array_map(fn (array $p): array => [$a * $p[0] + $b * $p[1] + $c, $d * $p[0] + $e * $p[1] + $f], $pixel);

        // Same convention as the world file: pixel (col,row) indexes the pixel centre.
        $fromPoints = AffinePlacement::fromControlPoints($pixel, $map, 100, 80);
        $this->assertCorners(AffinePlacement::fromWorldFile($w, 100, 80), $fromPoints);
    }

    public function test_collinear_control_points_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Titik kawalan tidak sah.');

        AffinePlacement::fromControlPoints([[0, 0], [10, 10], [20, 20]], [[0, 0], [1, 1], [2, 2]], 100, 100);
    }

    public function test_nearly_collinear_points_rejected_relative_to_image_area(): void
    {
        $map = [[0, 0], [400, 0], [200, 20]];
        // Triangle area 40 000 px² on a 4000 x 3000 image is 0.33 % (< 0.5 %): rejected.
        try {
            AffinePlacement::fromControlPoints([[0, 0], [4000, 0], [2000, 20]], $map, 4000, 3000);
            $this->fail('Nearly collinear control points were accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Titik kawalan tidak sah.', $e->getMessage());
        }

        // Area 80 000 px² is 0.67 %: accepted.
        $this->assertCount(4, AffinePlacement::fromControlPoints([[0, 0], [4000, 0], [2000, 40]], $map, 4000, 3000));
    }
}
