<?php

use Backstage\Favicon\Sources\HtmlLinkParser;

it('finds and ranks icon links from html head', function () {
    $html = <<<'HTML'
        <html>
        <head>
            <link rel="icon" href="/favicon.png" type="image/png" sizes="32x32">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">
            <link rel="icon" href="/icon.svg" type="image/svg+xml">
            <link rel="manifest" href="/site.webmanifest">
        </head>
        </html>
        HTML;

    $result = (new HtmlLinkParser())->parse($html, 'https://example.com/');

    expect($result['manifest'])->toBe('https://example.com/site.webmanifest');
    expect($result['icons'])->toHaveCount(3);

    $svg = collect($result['icons'])->firstWhere('type', 'svg');
    expect($svg->url)->toBe('https://example.com/icon.svg');

    $touch = collect($result['icons'])->firstWhere('url', 'https://example.com/apple-touch-icon.png');
    expect($touch->sizeHint)->toBe(180);
});

it('resolves relative and protocol relative hrefs against the base url', function () {
    $html = <<<'HTML'
        <html><head>
            <link rel="icon" href="assets/favicon.ico">
            <link rel="icon" href="//cdn.example.com/icon.png">
        </head></html>
        HTML;

    $result = (new HtmlLinkParser())->parse($html, 'https://example.com/blog/post');

    $urls = collect($result['icons'])->pluck('url')->all();

    expect($urls)->toContain('https://example.com/blog/assets/favicon.ico');
    expect($urls)->toContain('https://cdn.example.com/icon.png');
});

it('parses manifest icon entries and picks the largest size hint', function () {
    $icons = (new HtmlLinkParser())->parseManifestIcons([
        ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => '/icon-512.png', 'sizes' => '512x512 256x256', 'type' => 'image/png'],
    ], 'https://example.com/');

    expect($icons[0]->sizeHint)->toBe(192);
    expect($icons[1]->sizeHint)->toBe(512);
});

it('assumes an apple touch icon without sizes is 180px', function () {
    $html = <<<'HTML'
        <html><head>
            <link rel="icon" href="/favicon-32.png" sizes="32x32">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
            <link rel="apple-touch-icon-precomposed" href="/apple-touch-icon-precomposed.png">
            <link rel="icon" href="/favicon.png">
        </head></html>
        HTML;

    $hints = collect((new HtmlLinkParser())->parse($html, 'https://example.com/')['icons'])
        ->mapWithKeys(fn ($icon) => [$icon->url => $icon->sizeHint]);

    expect($hints['https://example.com/apple-touch-icon.png'])->toBe(180);
    expect($hints['https://example.com/apple-touch-icon-precomposed.png'])->toBe(180);
    expect($hints['https://example.com/favicon-32.png'])->toBe(32);
    expect($hints['https://example.com/favicon.png'])->toBeNull();
});
