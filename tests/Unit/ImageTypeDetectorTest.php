<?php

use Backstage\Favicon\Support\ImageTypeDetector;

it('detects image types from magic bytes, ignoring extensions or headers', function (string $type) {
    $image = new Imagick();
    $image->newImage(4, 4, new ImagickPixel('red'));
    $image->setImageFormat($type === 'jpg' ? 'jpeg' : $type);
    $bytes = $image->getImageBlob();
    $image->clear();

    expect(ImageTypeDetector::detect($bytes))->toBe($type);
})->with(['png', 'jpg', 'gif', 'webp']);

it('detects svg from its markup', function () {
    $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"></svg>';

    expect(ImageTypeDetector::detect($svg))->toBe('svg');
});

it('detects ico from its magic header', function () {
    $ico = "\x00\x00\x01\x00".str_repeat("\x00", 20);

    expect(ImageTypeDetector::detect($ico))->toBe('ico');
});

it('returns null for unrecognized bytes', function () {
    expect(ImageTypeDetector::detect('not an image'))->toBeNull();
});
