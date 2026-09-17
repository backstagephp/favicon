<?php

namespace Backstage\Favicon;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;

class FaviconManager
{
    public function __construct(
        private readonly array $config,
        private readonly Filesystem $disk,
        private readonly FaviconDiscoverer $discoverer,
        private readonly FaviconDownloader $downloader,
        private readonly ImagickConverter $converter,
    ) {}

    public function for(string $url): PendingFavicon
    {
        return new PendingFavicon($this, $url);
    }

    public function config(): array
    {
        return $this->config;
    }

    public function domainKey(string $url): string
    {
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? $url);

        return preg_replace('/[^a-z0-9.\-]/', '_', $host);
    }

    public function isFresh(string $domainKey): bool
    {
        $meta = $this->readMeta($domainKey);

        if ($meta === null) {
            return false;
        }

        $fetchedAt = Carbon::parse($meta['fetched_at']);

        return $fetchedAt->diffInDays(Carbon::now()) < $this->config['ttl_days'];
    }

    public function readMeta(string $domainKey): ?array
    {
        $path = $this->metaPath($domainKey);

        if (! $this->disk->exists($path)) {
            return null;
        }

        $contents = $this->disk->get($path);
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Fetch the best available source image for a site and cache it,
     * writing sidecar metadata used for freshness checks.
     */
    public function ensureSource(string $siteUrl, bool $force = false): array
    {
        $domainKey = $this->domainKey($siteUrl);

        if (! $force && $this->isFresh($domainKey)) {
            return $this->readMeta($domainKey);
        }

        $candidates = $this->discoverer->discover($siteUrl);
        $result = $this->downloader->downloadFirstValid($candidates);

        if ($result === null) {
            throw new FaviconException("Unable to discover or download a favicon for [{$siteUrl}].");
        }

        $meta = [
            'site_url' => $siteUrl,
            'domain' => $domainKey,
            'source_url' => $result['url'],
            'source_type' => $result['type'],
            'source_hash' => hash('sha256', $result['bytes']),
            'fetched_at' => Carbon::now()->toIso8601String(),
        ];

        // Wipe everything from a previous fetch (variants, derivatives, old
        // source file) so nothing generated from a stale source can leak.
        $this->disk->deleteDirectory($this->basePath($domainKey));

        $this->disk->put($this->sourcePath($domainKey, $result['type']), $result['bytes']);
        $this->disk->put($this->metaPath($domainKey), json_encode($meta, JSON_PRETTY_PRINT));

        return $meta;
    }

    public function variantPath(string $domainKey, string $type, ?int $size): string
    {
        if ($type === 'svg') {
            return "{$this->basePath($domainKey)}/icon.svg";
        }

        return "{$this->variantsDir($domainKey)}/{$type}/{$size}.{$type}";
    }

    public function resolveVariant(string $domainKey, array $meta, string $type, ?int $size): string
    {
        $path = $this->variantPath($domainKey, $type, $size);

        if ($this->disk->exists($path)) {
            return $path;
        }

        $sourceBytes = $this->disk->get($this->sourcePath($domainKey, $meta['source_type']));

        if ($type === 'svg') {
            if ($meta['source_type'] !== 'svg') {
                throw new FaviconException("Source favicon for [{$meta['site_url']}] is not an SVG; cannot serve type 'svg'.");
            }

            $this->disk->put($path, $sourceBytes);

            return $path;
        }

        if (! in_array($type, $this->config['types'], true)) {
            throw new FaviconException("Unsupported favicon type [{$type}]. Supported: ".implode(', ', $this->config['types']).', svg.');
        }

        $size ??= $this->config['default_size'];
        $blob = $this->converter->toRaster($sourceBytes, $meta['source_type'], $type, $size);
        $this->disk->put($path, $blob);

        return $path;
    }

    public function generateDerivatives(string $domainKey, array $meta): void
    {
        $sourceBytes = $this->disk->get($this->sourcePath($domainKey, $meta['source_type']));
        $generate = $this->config['generate'];

        if ($generate['ico'] ?? false) {
            $ico = $this->converter->toIco($sourceBytes, $meta['source_type'], $this->config['ico_sizes']);
            $this->disk->put("{$this->basePath($domainKey)}/favicon.ico", $ico);
        }

        if ($generate['apple_touch_icon'] ?? false) {
            $size = $this->config['apple_touch_icon_size'];
            $png = $this->converter->toRaster($sourceBytes, $meta['source_type'], 'png', $size);
            $this->disk->put("{$this->basePath($domainKey)}/apple-touch-icon.png", $png);
        }

        if ($generate['manifest'] ?? false) {
            $this->generateManifest($domainKey, $meta, $sourceBytes);
        }
    }

    private function generateManifest(string $domainKey, array $meta, string $sourceBytes): void
    {
        $icons = [];

        foreach ($this->config['manifest_sizes'] as $size) {
            $filename = "android-chrome-{$size}x{$size}.png";
            $png = $this->converter->toRaster($sourceBytes, $meta['source_type'], 'png', $size);
            $this->disk->put("{$this->basePath($domainKey)}/{$filename}", $png);

            $icons[] = [
                'src' => $this->disk->url("{$this->basePath($domainKey)}/{$filename}"),
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
            ];
        }

        $manifest = [
            'name' => $meta['domain'],
            'icons' => $icons,
        ];

        $this->disk->put(
            "{$this->basePath($domainKey)}/site.webmanifest",
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function absolutePath(string $relativePath): string
    {
        return $this->disk->path($relativePath);
    }

    public function url(string $relativePath): string
    {
        return $this->disk->url($relativePath);
    }

    public function exists(string $relativePath): bool
    {
        return $this->disk->exists($relativePath);
    }

    private function basePath(string $domainKey): string
    {
        return "{$this->config['path']}/{$domainKey}";
    }

    private function variantsDir(string $domainKey): string
    {
        return "{$this->basePath($domainKey)}/variants";
    }

    private function sourcePath(string $domainKey, string $sourceType): string
    {
        return "{$this->basePath($domainKey)}/source.{$sourceType}";
    }

    private function metaPath(string $domainKey): string
    {
        return "{$this->basePath($domainKey)}/meta.json";
    }
}
