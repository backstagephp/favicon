<?php

use Backstage\Favicon\Concerns\HasFavicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class SiteWithDefaults extends Model
{
    use HasFavicon;

    protected $guarded = [];

    protected string $faviconType = 'webp';

    protected int $faviconSize = 64;
}

class SiteWithoutDefaults extends Model
{
    use HasFavicon;

    protected $guarded = [];
}

it('uses the model $faviconType/$faviconSize when set', function () {
    Http::fake([
        'https://model-defaults.test/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png" type="image/png"></head></html>'
        ),
        'https://model-defaults.test/icon.png' => Http::response(transparentSourcePng()),
    ]);

    $site = new SiteWithDefaults(['website' => 'https://model-defaults.test']);

    expect($site->favicon)->toContain('/webp/64.webp');
    expect($site->faviconUrl())->toContain('/webp/64.webp');
});

it('falls back to the package config default when the model sets no type/size', function () {
    Http::fake([
        'https://no-model-defaults.test/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png" type="image/png"></head></html>'
        ),
        'https://no-model-defaults.test/icon.png' => Http::response(transparentSourcePng()),
    ]);

    $site = new SiteWithoutDefaults(['website' => 'https://no-model-defaults.test']);

    $default = config('favicon.default_type');
    $size = config('favicon.default_size');

    expect($site->favicon)->toContain("/{$default}/{$size}.{$default}");
});

it('lets an explicit call argument override the model default', function () {
    Http::fake([
        'https://override.test/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png" type="image/png"></head></html>'
        ),
        'https://override.test/icon.png' => Http::response(transparentSourcePng()),
    ]);

    $site = new SiteWithDefaults(['website' => 'https://override.test']);

    expect($site->faviconUrl('png', 16))->toContain('/png/16.png');
});
