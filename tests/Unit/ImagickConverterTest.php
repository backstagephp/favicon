<?php

use Backstage\Favicon\ImagickConverter;

function converter(): ImagickConverter
{
    return new ImagickConverter(config('favicon'));
}

function transparentSourcePng(): string
{
    $image = new Imagick();
    $image->newImage(64, 64, new ImagickPixel('transparent'));
    $image->setImageFormat('png32');

    $circle = new ImagickDraw();
    $circle->setFillColor(new ImagickPixel('red'));
    $circle->circle(32, 32, 32, 10);
    $image->drawImage($circle);

    $bytes = $image->getImageBlob();
    $image->clear();

    return $bytes;
}

it('converts a transparent source into every supported raster type', function (string $type) {
    $bytes = converter()->toRaster(transparentSourcePng(), 'png', $type, 32);

    expect($bytes)->not->toBeEmpty();

    $image = new Imagick();
    $image->readImageBlob($bytes);

    expect($image->getImageWidth())->toBe(32);
    expect($image->getImageHeight())->toBe(32);
})->with(['png', 'jpg', 'webp', 'avif']);

it('flattens transparency onto a white background for jpg', function () {
    $bytes = converter()->toRaster(transparentSourcePng(), 'png', 'jpg', 32);

    $image = new Imagick();
    $image->readImageBlob($bytes);

    // The source is a circle with transparent corners; jpg has no alpha
    // channel, so a corner pixel should have been flattened onto
    // (near-)white rather than left black/transparent. Allow some slack
    // for jpg compression artifacts near the circle's edge.
    $corner = $image->getImagePixelColor(0, 0)->getColor();

    expect($corner['r'])->toBeGreaterThan(230);
    expect($corner['g'])->toBeGreaterThan(230);
    expect($corner['b'])->toBeGreaterThan(230);
});

it('builds a multi-resolution ico with one frame per requested size', function () {
    $bytes = converter()->toIco(transparentSourcePng(), 'png', [16, 32, 48]);

    $path = tempnam(sys_get_temp_dir(), 'ico-test-');
    file_put_contents($path, $bytes);

    $ico = new Imagick();
    $ico->readImage('ico:'.$path);

    expect($ico->getNumberImages())->toBe(3);

    unlink($path);
});
