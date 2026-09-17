<?php

namespace Backstage\Favicon;

class PendingFavicon
{
    private bool $forceRefresh = false;

    public function __construct(
        private readonly FaviconManager $manager,
        private readonly string $siteUrl,
    ) {}

    /**
     * Force the next resolution to re-fetch from the source site, ignoring
     * the cached TTL.
     */
    public function refresh(): static
    {
        $this->forceRefresh = true;

        return $this;
    }

    /**
     * Resolve (fetching/converting if needed) and return the absolute
     * filesystem path to the requested favicon variant.
     */
    public function get(?string $type = null, ?int $size = null): string
    {
        return $this->manager->absolutePath($this->resolve($type, $size));
    }

    /**
     * Resolve and return the public URL to the requested favicon variant.
     */
    public function url(?string $type = null, ?int $size = null): string
    {
        return $this->manager->url($this->resolve($type, $size));
    }

    public function exists(?string $type = null, ?int $size = null): bool
    {
        $type ??= $this->manager->config()['default_type'];
        $size ??= $type === 'svg' ? null : $this->manager->config()['default_size'];

        $domainKey = $this->manager->domainKey($this->siteUrl);

        return $this->manager->exists($this->manager->variantPath($domainKey, $type, $size));
    }

    /**
     * Resolve every configured size for the given type(s), plus derived
     * outputs (favicon.ico / apple-touch-icon / site.webmanifest). Intended
     * for pre-warming via the artisan command or queued job.
     */
    public function warm(?array $types = null, ?array $sizes = null): void
    {
        $config = $this->manager->config();
        $types ??= $config['types'];
        $sizes ??= $config['sizes'];

        $meta = $this->manager->ensureSource($this->siteUrl, $this->forceRefresh);
        $this->forceRefresh = false;
        $domainKey = $this->manager->domainKey($this->siteUrl);

        foreach ($types as $type) {
            foreach ($sizes as $size) {
                $this->manager->resolveVariant($domainKey, $meta, $type, $size);
            }
        }

        $this->manager->generateDerivatives($domainKey, $meta);
    }

    private function resolve(?string $type, ?int $size): string
    {
        $config = $this->manager->config();
        $type ??= $config['default_type'];
        $size = $type === 'svg' ? null : ($size ?? $config['default_size']);

        $meta = $this->manager->ensureSource($this->siteUrl, $this->forceRefresh);
        $this->forceRefresh = false;

        $domainKey = $this->manager->domainKey($this->siteUrl);

        return $this->manager->resolveVariant($domainKey, $meta, $type, $size);
    }
}
