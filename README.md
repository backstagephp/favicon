# Favicon

Fetch, convert, and cache favicons for any website in your Laravel app.

Given a site URL, the package discovers the best available icon source (preferring an SVG over an ICO over a raster PNG/WebP/AVIF over a JPG), downloads it, and converts it into whichever size/format you ask for — caching the result on disk so subsequent requests are instant until the TTL expires.

## Requirements

- PHP 8.2+
- The `imagick` PHP extension, ideally built with the `librsvg` delegate (for SVG rasterization) and `libheif` delegate (for AVIF)
- Laravel 10, 11, or 12

## Installation

```bash
composer require backstage/favicon
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=favicon-config
```

## Usage

### Facade

```php
use Backstage\Favicon\Facades\Favicon;

// Absolute filesystem path
$path = Favicon::for('https://github.com')->get('png', 32);

// Public URL (served from the configured disk)
$url = Favicon::for('https://github.com')->url('webp', 64);

// The original SVG, only if the discovered source was actually an SVG
$url = Favicon::for('https://github.com')->url('svg');

// Force a re-fetch from the source site, ignoring the TTL
$url = Favicon::for('https://github.com')->refresh()->url('png', 32);
```

Supported types: `png`, `jpg`, `webp`, `avif`, and `svg` (pass-through only — a raster source can't be vectorized).

### Blade component

```blade
<x-favicon url="https://github.com" size="32" type="png" class="rounded" />
```

### Eloquent trait

```php
use Backstage\Favicon\Concerns\HasFavicon;

class Site extends Model
{
    use HasFavicon;

    // Reads from $site->website by default; override per-model:
    protected string $faviconSource = 'homepage_url';
}
```

```php
$site->favicon;                 // accessor, default type/size
$site->faviconUrl('webp', 64);
```

### Artisan command

```bash
# Fetch the default type/size
php artisan favicon:fetch https://github.com

# Pre-warm every configured size for specific types
php artisan favicon:fetch https://github.com --types=png,webp --sizes=16,32,180

# Ignore the TTL
php artisan favicon:fetch https://github.com --force

# Dispatch to the queue instead of running synchronously
php artisan favicon:fetch https://github.com --queue
```

### Queued pre-warming

```php
use Backstage\Favicon\Jobs\FetchFaviconJob;

FetchFaviconJob::dispatch('https://github.com', types: ['png', 'webp'], sizes: [32, 180]);
```

`->warm()` (used internally by the job/command) also generates a multi-resolution `favicon.ico`, an `apple-touch-icon.png`, and a `site.webmanifest` with Android Chrome icons, per the `generate` config.

## How source discovery works

For a given site, the package:

1. Fetches the page and parses `<link rel="icon">`, `rel="shortcut icon"`, `rel="apple-touch-icon"`, `rel="apple-touch-icon-precomposed"`, and `rel="mask-icon"` tags, plus `<link rel="manifest">` and its `icons` array.
2. Falls back to `/favicon.ico` at the domain root.
3. Ranks every candidate: SVG first, then ICO, then PNG/WebP/AVIF (largest declared size wins), then JPG.
4. Downloads the best candidate and sniffs its real type from magic bytes (not the `Content-Type` header, which is often wrong).
5. If the source is a multi-frame `.ico`, the largest embedded frame is used.

## Storage

No database table is used. Each fetched favicon is cached under the configured disk (default `public`) at:

```
favicons/{domain}/
├── meta.json                # source url/type/hash + fetched_at, used for TTL checks
├── source.{ext}              # cached original bytes
├── icon.svg                  # only when the source is svg
├── variants/{type}/{size}.{ext}
├── favicon.ico
├── apple-touch-icon.png
├── android-chrome-{size}x{size}.png
└── site.webmanifest
```

A favicon is considered fresh for `favicon.ttl_days` (default 30) days from `fetched_at`; after that, the next resolution re-fetches from the source site.

## Testing

```bash
composer test
```

## License

MIT.
