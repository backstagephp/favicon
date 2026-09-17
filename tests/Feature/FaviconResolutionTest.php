<?php

use Backstage\Favicon\Facades\Favicon;
use Backstage\Favicon\FaviconException;
use Illuminate\Support\Facades\Http;

function samplePng(int $size = 64): string
{
    $image = new Imagick();
    $image->newImage($size, $size, new ImagickPixel('blue'));
    $image->setImageFormat('png32');
    $bytes = $image->getImageBlob();
    $image->clear();

    return $bytes;
}

function sampleSvg(): string
{
    return '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="red"/></svg>';
}

it('discovers an svg icon over other candidates and converts it to png', function () {
    Http::fake([
        'https://example.com/' => Http::response(
            '<html><head><link rel="icon" href="/icon.svg" type="image/svg+xml"><link rel="icon" href="/icon.png" type="image/png"></head></html>'
        ),
        'https://example.com/icon.svg' => Http::response(sampleSvg()),
    ]);

    $path = Favicon::for('https://example.com')->get('png', 32);

    expect($path)->toBeFile();

    $info = getimagesize($path);
    expect($info[0])->toBe(32)->and($info[1])->toBe(32);
});

it('falls back to /favicon.ico when no link tags are present', function () {
    $ico = (new \Backstage\Favicon\ImagickConverter(config('favicon')))
        ->toIco(samplePng(), 'png', [16, 32]);

    Http::fake([
        'https://noicons.test/' => Http::response('<html><head></head></html>'),
        'https://noicons.test/favicon.ico' => Http::response($ico),
    ]);

    $path = Favicon::for('https://noicons.test')->get('png', 16);

    expect($path)->toBeFile();
});

it('serves svg pass-through only when the source is actually svg', function () {
    Http::fake([
        'https://svgsite.test/' => Http::response(
            '<html><head><link rel="icon" href="/icon.svg" type="image/svg+xml"></head></html>'
        ),
        'https://svgsite.test/icon.svg' => Http::response(sampleSvg()),
    ]);

    $path = Favicon::for('https://svgsite.test')->get('svg');

    expect(file_get_contents($path))->toContain('<svg');
});

it('throws when svg is requested but the source is not svg', function () {
    Http::fake([
        'https://pngsite.test/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png" type="image/png"></head></html>'
        ),
        'https://pngsite.test/icon.png' => Http::response(samplePng()),
    ]);

    Favicon::for('https://pngsite.test')->get('svg');
})->throws(FaviconException::class);

it('does not re-fetch from the source while the cached favicon is still fresh', function () {
    Http::fake([
        'https://fresh.test/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png" type="image/png"></head></html>'
        ),
        'https://fresh.test/icon.png' => Http::response(samplePng()),
    ]);

    Favicon::for('https://fresh.test')->get('png', 32);
    Http::assertSentCount(2);

    Favicon::for('https://fresh.test')->get('png', 32);
    Http::assertSentCount(2);
});

it('re-fetches once the ttl expires', function () {
    config()->set('favicon.ttl_days', 1);

    Http::fake([
        'https://ttl.test/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png" type="image/png"></head></html>'
        ),
        'https://ttl.test/icon.png' => Http::response(samplePng()),
    ]);

    Favicon::for('https://ttl.test')->get('png', 32);
    Http::assertSentCount(2);

    $this->travel(2)->days();

    Favicon::for('https://ttl.test')->get('png', 32);
    Http::assertSentCount(4);
});

it('force-refreshes even when still within the ttl', function () {
    Http::fake([
        'https://forced.test/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png" type="image/png"></head></html>'
        ),
        'https://forced.test/icon.png' => Http::response(samplePng()),
    ]);

    Favicon::for('https://forced.test')->get('png', 32);
    Http::assertSentCount(2);

    Favicon::for('https://forced.test')->refresh()->get('png', 32);
    Http::assertSentCount(4);
});

it('warms every configured size and generates ico, apple touch icon, and manifest', function () {
    Http::fake([
        'https://warm.test/' => Http::response(
            '<html><head><link rel="icon" href="/icon.png" type="image/png"></head></html>'
        ),
        'https://warm.test/icon.png' => Http::response(samplePng()),
    ]);

    Favicon::for('https://warm.test')->warm(['png'], [16, 32]);

    $disk = \Illuminate\Support\Facades\Storage::disk(config('favicon.disk'));
    $domain = 'warm.test';

    expect($disk->exists("favicons/{$domain}/variants/png/16.png"))->toBeTrue();
    expect($disk->exists("favicons/{$domain}/variants/png/32.png"))->toBeTrue();
    expect($disk->exists("favicons/{$domain}/favicon.ico"))->toBeTrue();
    expect($disk->exists("favicons/{$domain}/apple-touch-icon.png"))->toBeTrue();
    expect($disk->exists("favicons/{$domain}/site.webmanifest"))->toBeTrue();
});
