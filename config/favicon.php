<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk generated favicon variants are written to and
    | served from. Must be a disk configured in config/filesystems.php.
    |
    */
    'disk' => env('FAVICON_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Path prefix
    |--------------------------------------------------------------------------
    |
    | Directory (relative to the disk root) favicons are stored under.
    | The final layout is: {path}/{domain}/{...variants}
    |
    */
    'path' => env('FAVICON_PATH', 'favicons'),

    /*
    |--------------------------------------------------------------------------
    | Freshness (TTL)
    |--------------------------------------------------------------------------
    |
    | Number of days a fetched favicon is considered fresh. After this many
    | days, the next resolution re-fetches and re-converts from the source
    | site instead of serving the cached files.
    |
    */
    'ttl_days' => (int) env('FAVICON_TTL_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    */
    'default_type' => 'png',
    'default_size' => 32,

    /*
    |--------------------------------------------------------------------------
    | Sizes used when pre-warming (queue job / artisan command without
    | explicit --sizes) and when generating derived outputs.
    |
    */
    'sizes' => [16, 32, 48, 64, 128, 180, 192, 512],

    /*
    |--------------------------------------------------------------------------
    | Supported conversion types
    |--------------------------------------------------------------------------
    |
    | Raster types the converter can produce via Imagick. 'svg' is handled
    | separately as a pass-through (see below) since raster targets can't
    | be meaningfully vectorized.
    |
    */
    'types' => ['png', 'jpg', 'webp', 'avif'],

    /*
    |--------------------------------------------------------------------------
    | Source discovery priority
    |--------------------------------------------------------------------------
    |
    | Lower number = preferred. Used to rank candidate favicon sources found
    | on the page (<link> tags, manifest icons, /favicon.ico fallback).
    |
    */
    'source_priority' => [
        'svg' => 0,
        'ico' => 1,
        'png' => 2,
        'webp' => 2,
        'avif' => 2,
        'jpg' => 3,
        'jpeg' => 3,
        'gif' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Derived outputs
    |--------------------------------------------------------------------------
    |
    | Additional artifacts generated alongside the sized rasters whenever a
    | favicon is (re)fetched.
    |
    */
    'generate' => [
        'ico' => true,              // multi-resolution favicon.ico (16/32/48)
        'apple_touch_icon' => true, // 180x180 apple-touch-icon.png
        'manifest' => true,         // site.webmanifest + android-chrome icons
    ],

    'ico_sizes' => [16, 32, 48],
    'apple_touch_icon_size' => 180,
    'manifest_sizes' => [192, 512],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout' => (int) env('FAVICON_HTTP_TIMEOUT', 10),
        'user_agent' => env('FAVICON_USER_AGENT', 'Mozilla/5.0 (compatible; FaviconFetcher/1.0; +https://github.com/backstagephp/favicon)'),
        'max_bytes' => (int) env('FAVICON_MAX_BYTES', 5 * 1024 * 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image quality
    |--------------------------------------------------------------------------
    */
    'quality' => [
        'jpg' => 85,
        'webp' => 85,
        'avif' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Eloquent trait
    |--------------------------------------------------------------------------
    |
    | Default model attribute Backstage\Favicon\Concerns\HasFavicon reads the
    | source URL from, unless the model defines its own $faviconSource.
    |
    */
    'model_source_attribute' => 'website',

];
