<?php

declare(strict_types=1);

namespace App\Services\Gis;

/**
 * Reads the EXIF Orientation tag (0x0112) of a JPEG from its APP1 segment, without the exif extension (which is not
 * installed everywhere). Browsers apply this orientation to the displayed image, while getimagesize() reports raw
 * dimensions, so a rotated JPEG would be placed differently from its preview.
 */
final class JpegOrientation
{
    private const MAX_SCAN_BYTES = 1048576;

    /** The orientation value (1-8), or null when the file carries none or is not a readable JPEG. */
    public static function read(string $path): ?int
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }
        try {
            $data = fread($handle, self::MAX_SCAN_BYTES);
        } finally {
            fclose($handle);
        }

        return is_string($data) ? self::fromBytes($data) : null;
    }

    public static function fromBytes(string $data): ?int
    {
        if (strlen($data) < 4 || substr($data, 0, 2) !== "\xFF\xD8") {
            return null;
        }
        $offset = 2;
        $length = strlen($data);
        while ($offset + 4 <= $length) {
            if ($data[$offset] !== "\xFF") {
                return null;
            }
            $marker = ord($data[$offset + 1]);
            if ($marker === 0xFF) { // fill byte
                $offset++;

                continue;
            }
            if ($marker === 0xD9 || $marker === 0xDA) { // end of image or start of scan: no more metadata
                return null;
            }
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) { // markers without a length
                $offset += 2;

                continue;
            }
            $segmentLength = unpack('n', substr($data, $offset + 2, 2))[1];
            if ($segmentLength < 2) {
                return null;
            }
            if ($marker === 0xE1 && substr($data, $offset + 4, 6) === "Exif\0\0") {
                $orientation = self::fromTiff(substr($data, $offset + 10, $segmentLength - 8));
                if ($orientation !== null) {
                    return $orientation;
                }
            }
            $offset += 2 + $segmentLength;
        }

        return null;
    }

    private static function fromTiff(string $tiff): ?int
    {
        if (strlen($tiff) < 8) {
            return null;
        }
        $order = substr($tiff, 0, 2);
        if ($order !== 'II' && $order !== 'MM') {
            return null;
        }
        $short = $order === 'II' ? 'v' : 'n';
        $long = $order === 'II' ? 'V' : 'N';
        $read = function (string $format, int $at, int $bytes) use ($tiff): ?int {
            return $at >= 0 && $at + $bytes <= strlen($tiff) ? unpack($format, substr($tiff, $at, $bytes))[1] : null;
        };
        if ($read($short, 2, 2) !== 42) {
            return null;
        }
        $ifd = $read($long, 4, 4);
        $count = $ifd === null ? null : $read($short, $ifd, 2);
        if ($count === null) {
            return null;
        }
        for ($i = 0; $i < $count; $i++) {
            $entry = $ifd + 2 + $i * 12;
            $tag = $read($short, $entry, 2);
            if ($tag === null) {
                return null;
            }
            if ($tag === 0x0112) {
                // Type SHORT (3), count 1: the value sits in the first two bytes of the value field.
                return $read($short, $entry + 2, 2) === 3 ? $read($short, $entry + 8, 2) : null;
            }
        }

        return null;
    }
}
