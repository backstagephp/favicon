<?php

namespace Backstage\Favicon\Sources;

final class HtmlLinkParser
{
    // Deliberately excludes rel="mask-icon": it's a Safari-only pinned-tab
    // asset, conventionally a monochrome silhouette meant to be tinted via
    // the link's `color` attribute, not a representative favicon.
    private const ICON_RELS = [
        'icon',
        'shortcut icon',
        'apple-touch-icon',
        'apple-touch-icon-precomposed',
    ];

    // iOS renders touch icons at 180x180, so one declared without a
    // `sizes` attribute is assumed to be that large rather than unknown,
    // letting it outrank a small, explicitly sized tab icon.
    private const APPLE_TOUCH_ICON_SIZE = 180;

    /**
     * @return array{icons: FaviconSource[], manifest: string|null}
     */
    public function parse(string $html, string $baseUrl): array
    {
        $icons = [];
        $manifest = null;

        if (trim($html) === '') {
            return ['icons' => $icons, 'manifest' => $manifest];
        }

        $document = new \DOMDocument();

        libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOBLANKS);
        libxml_clear_errors();

        foreach ($document->getElementsByTagName('link') as $link) {
            $rel = strtolower(trim($link->getAttribute('rel')));
            $href = trim($link->getAttribute('href'));

            if ($href === '') {
                continue;
            }

            if ($rel === 'manifest') {
                $manifest = $this->resolveUrl($href, $baseUrl);

                continue;
            }

            if (! in_array($rel, self::ICON_RELS, true)) {
                continue;
            }

            $absolute = $this->resolveUrl($href, $baseUrl);
            $type = $this->guessTypeFromUrl($absolute, $link->getAttribute('type'));
            $sizeHint = $this->parseSizes($link->getAttribute('sizes'))
                ?? (str_starts_with($rel, 'apple-touch-icon') ? self::APPLE_TOUCH_ICON_SIZE : null);

            $icons[] = new FaviconSource($absolute, $type, $sizeHint);
        }

        return ['icons' => $icons, 'manifest' => $manifest];
    }

    /**
     * @param  array<int, array{src?: string, sizes?: string, type?: string}>  $manifestIcons
     * @return FaviconSource[]
     */
    public function parseManifestIcons(array $manifestIcons, string $baseUrl): array
    {
        $icons = [];

        foreach ($manifestIcons as $icon) {
            if (empty($icon['src'])) {
                continue;
            }

            $absolute = $this->resolveUrl($icon['src'], $baseUrl);
            $type = $this->guessTypeFromUrl($absolute, $icon['type'] ?? null);
            $sizeHint = $this->parseSizes($icon['sizes'] ?? null);

            $icons[] = new FaviconSource($absolute, $type, $sizeHint);
        }

        return $icons;
    }

    private function parseSizes(?string $sizes): ?int
    {
        if (! $sizes || strtolower(trim($sizes)) === 'any') {
            return null;
        }

        // "sizes" can list multiple, space separated (e.g. "16x16 32x32"); take the largest.
        $largest = null;

        foreach (preg_split('/\s+/', trim($sizes)) as $entry) {
            if (preg_match('/^(\d+)x(\d+)$/i', $entry, $matches)) {
                $value = (int) $matches[1];
                $largest = $largest === null ? $value : max($largest, $value);
            }
        }

        return $largest;
    }

    private function guessTypeFromUrl(string $url, ?string $mime): string
    {
        if ($mime) {
            $mime = strtolower(trim($mime));

            $map = [
                'image/svg+xml' => 'svg',
                'image/x-icon' => 'ico',
                'image/vnd.microsoft.icon' => 'ico',
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                'image/avif' => 'avif',
            ];

            if (isset($map[$mime])) {
                return $map[$mime];
            }
        }

        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        return match ($extension) {
            'svg' => 'svg',
            'ico' => 'ico',
            'jpg', 'jpeg' => 'jpg',
            'webp' => 'webp',
            'avif' => 'avif',
            'gif' => 'gif',
            default => 'png',
        };
    }

    private function resolveUrl(string $href, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$href;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        if (str_starts_with($href, '/')) {
            return "{$scheme}://{$host}{$port}{$href}";
        }

        $basePath = $base['path'] ?? '/';
        $directory = rtrim(str_contains($basePath, '/') ? dirname($basePath) : '', '/');

        return "{$scheme}://{$host}{$port}{$directory}/{$href}";
    }
}
