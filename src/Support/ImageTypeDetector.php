<?php

namespace Backstage\Favicon\Support;

final class ImageTypeDetector
{
    /**
     * Detect the real image type from its bytes, ignoring any (often wrong)
     * Content-Type header or file extension supplied by the source.
     */
    public static function detect(string $bytes): ?string
    {
        if (strlen($bytes) < 12) {
            return self::sniffSvg($bytes);
        }

        if (str_starts_with($bytes, "\x89PNG\x0d\x0a\x1a\x0a")) {
            return 'png';
        }

        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'jpg';
        }

        if (str_starts_with($bytes, "GIF87a") || str_starts_with($bytes, "GIF89a")) {
            return 'gif';
        }

        if (str_starts_with($bytes, "\x00\x00\x01\x00") || str_starts_with($bytes, "\x00\x00\x02\x00")) {
            return 'ico';
        }

        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'webp';
        }

        if (substr($bytes, 4, 4) === 'ftyp' && in_array(substr($bytes, 8, 4), ['avif', 'avis'], true)) {
            return 'avif';
        }

        return self::sniffSvg($bytes);
    }

    private static function sniffSvg(string $bytes): ?string
    {
        $head = ltrim(substr($bytes, 0, 512));

        if ($head === '') {
            return null;
        }

        if (str_starts_with($head, '<?xml') || str_starts_with($head, '<svg')) {
            return str_contains($head, '<svg') || str_contains(substr($bytes, 0, 2048), '<svg')
                ? 'svg'
                : null;
        }

        return null;
    }
}
