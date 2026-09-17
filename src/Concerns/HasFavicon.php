<?php

namespace Backstage\Favicon\Concerns;

use Backstage\Favicon\FaviconManager;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasFavicon
{
    /**
     * Resolve the favicon URL for this model's source URL attribute
     * (config('favicon.model_source_attribute'), or $faviconSource if the
     * model defines one).
     */
    public function faviconUrl(?string $type = null, ?int $size = null): ?string
    {
        $sourceUrl = $this->{$this->faviconSourceAttribute()};

        if (! $sourceUrl) {
            return null;
        }

        return app(FaviconManager::class)->for($sourceUrl)->url($type, $size);
    }

    protected function favicon(): Attribute
    {
        return Attribute::make(get: fn () => $this->faviconUrl());
    }

    protected function faviconSourceAttribute(): string
    {
        return property_exists($this, 'faviconSource')
            ? $this->faviconSource
            : config('favicon.model_source_attribute', 'website');
    }
}
