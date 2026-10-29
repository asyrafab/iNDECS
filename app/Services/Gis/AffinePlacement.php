<?php

namespace App\Services\Gis;

use InvalidArgumentException;

/**
 * Computes the four map-space corners of a scanned plan from a world file or from three control points.
 * Corner order: top-left, top-right, bottom-right, bottom-left.
 */
final class AffinePlacement
{
    /** Minimum control-point triangle area, as a fraction of the image area. */
    public const MIN_TRIANGLE_FRACTION = 0.005;

    /**
     * @param  array<int, float|int|string>  $w  [A, D, B, E, C, F] in world-file line order
     * @return array<int, array{0: float, 1: float}>
     */
    public static function fromWorldFile(array $w, int $width, int $height): array
    {
        [$a, $d, $b, $e, $c, $f] = array_map('floatval', array_values($w));

        $corners = [];
        foreach ([[0, 0], [$width, 0], [$width, $height], [0, $height]] as [$col, $row]) {
            // The world file locates the centre of the top-left pixel; step back half a pixel to the outer edge.
            $corners[] = [
                $a * $col + $b * $row + $c - ($a + $b) / 2,
                $d * $col + $e * $row + $f - ($d + $e) / 2,
            ];
        }

        return $corners;
    }

    /**
     * @param  array<int, array{0: float|int, 1: float|int}>  $pixel  three [col, row] points (pixel-centre convention, like a world file)
     * @param  array<int, array{0: float|int, 1: float|int}>  $map  the three matching [x, y] map points
     * @return array<int, array{0: float, 1: float}>
     */
    public static function fromControlPoints(array $pixel, array $map, int $width, int $height): array
    {
        if (count($pixel) !== 3 || count($map) !== 3) {
            throw new InvalidArgumentException('Titik kawalan tidak sah.');
        }
        [[$c0, $r0], [$c1, $r1], [$c2, $r2]] = array_values($pixel);

        $det = $c0 * ($r1 - $r2) - $r0 * ($c1 - $c2) + ($c1 * $r2 - $c2 * $r1);
        // |det| is twice the pixel triangle's area. A triangle under 0.5 % of the image is nearly collinear (or tiny),
        // and small clicking errors would then swing the placement wildly.
        if (abs($det) / 2 < self::MIN_TRIANGLE_FRACTION * max(1, $width) * max(1, $height)) {
            throw new InvalidArgumentException('Titik kawalan tidak sah.');
        }

        // Solve v = a*col + b*row + c for the three points by Cramer's rule.
        $solve = function (float $v0, float $v1, float $v2) use ($c0, $r0, $c1, $r1, $c2, $r2, $det): array {
            $a = ($v0 * ($r1 - $r2) - $r0 * ($v1 - $v2) + ($v1 * $r2 - $v2 * $r1)) / $det;
            $b = ($c0 * ($v1 - $v2) - $v0 * ($c1 - $c2) + ($c1 * $v2 - $c2 * $v1)) / $det;
            $c = ($c0 * ($r1 * $v2 - $r2 * $v1) - $r0 * ($c1 * $v2 - $c2 * $v1) + $v0 * ($c1 * $r2 - $c2 * $r1)) / $det;

            return [$a, $b, $c];
        };

        [$m0, $m1, $m2] = array_values($map);
        [$a, $b, $c] = $solve((float) $m0[0], (float) $m1[0], (float) $m2[0]);
        [$d, $e, $f] = $solve((float) $m0[1], (float) $m1[1], (float) $m2[1]);

        return self::fromWorldFile([$a, $d, $b, $e, $c, $f], $width, $height);
    }
}
