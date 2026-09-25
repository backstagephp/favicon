<?php

namespace Backstage\Favicon;

use Imagick;
use ImagickPixel;

class ImagickConverter
{
    public function __construct(private readonly array $config) {}

    /**
     * Convert raw source image bytes into a square raster of the given
     * size and format. Handles multi-frame ICO (picks the largest frame)
     * and SVG (rasterizes at the target resolution) transparently.
     */
    public function toRaster(string $bytes, string $sourceType, string $targetType, int $size): string
    {
        $image = $this->load($bytes, $sourceType, $size);
        $image = $this->toSquare($image, $size);
        $image = $this->applyFormat($image, $targetType);

        $blob = $image->getImageBlob();
        $image->clear();
        $image->destroy();

        return $blob;
    }

    /**
     * Build a multi-resolution .ico from a source image, embedding one
     * frame per requested size.
     */
    public function toIco(string $bytes, string $sourceType, array $sizes): string
    {
        $ico = new Imagick();

        foreach ($sizes as $size) {
            $frame = $this->load($bytes, $sourceType, $size);
            $frame = $this->toSquare($frame, $size);
            $frame->setImageFormat('png32');
            $ico->addImage($frame);
            $frame->clear();
            $frame->destroy();
        }

        $ico->setFormat('ico');
        $blob = $ico->getImagesBlob();
        $ico->clear();
        $ico->destroy();

        return $blob;
    }

    private function load(string $bytes, string $sourceType, int $size): Imagick
    {
        if ($sourceType === 'svg') {
            return $this->loadSvg($bytes, $size);
        }

        if ($sourceType === 'ico') {
            return $this->loadLargestIcoFrame($bytes);
        }

        $image = new Imagick();
        $image->readImageBlob($bytes);
        $image->setImageBackgroundColor(new ImagickPixel('transparent'));

        if ($image->getImageAlphaChannel() === 0) {
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE);
        }

        return $image;
    }

    private function loadSvg(string $bytes, int $size): Imagick
    {
        $image = new Imagick();
        $image->setBackgroundColor(new ImagickPixel('transparent'));
        // Rasterize at the target resolution up front for crisp output
        // rather than downscaling a fixed-size render.
        $image->setResolution(300, 300);
        $image->readImageBlob($bytes);
        $image->setImageFormat('png32');

        return $image;
    }

    private function loadLargestIcoFrame(string $bytes): Imagick
    {
        // Imagick's ICO coder can't decode from an in-memory blob (it needs
        // to seek through the directory structure), only from a real file.
        $path = tempnam(sys_get_temp_dir(), 'favicon-ico-');
        file_put_contents($path, $bytes);

        try {
            $ico = new Imagick();
            $ico->readImage('ico:'.$path);

            $best = null;
            $bestWidth = -1;

            foreach ($ico as $frame) {
                $width = $frame->getImageWidth();

                if ($width > $bestWidth) {
                    $bestWidth = $width;
                    $best?->clear();
                    $best = clone $frame;
                }
            }

            $ico->clear();
            $ico->destroy();
        } finally {
            unlink($path);
        }

        if ($best === null) {
            throw new FaviconException('Unable to read any frame from the source .ico file.');
        }

        $best->setImageBackgroundColor(new ImagickPixel('transparent'));

        return $best;
    }

    private function toSquare(Imagick $image, int $size): Imagick
    {
        $image->setImageFormat('png32');
        // resizeImage rather than thumbnailImage: the latter point-samples
        // large reductions (e.g. 192 -> 32) down to 5x the target first,
        // which drops the thin strokes favicons are full of.
        $image->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1, true);
        $image->stripImage();
        $image->extentImage(
            $size,
            $size,
            (int) (-1 * (($size - $image->getImageWidth()) / 2)),
            (int) (-1 * (($size - $image->getImageHeight()) / 2)),
        );

        return $image;
    }

    private function applyFormat(Imagick $image, string $targetType): Imagick
    {
        $quality = $this->config['quality'][$targetType] ?? null;

        match ($targetType) {
            'jpg' => $this->flattenToWhite($image),
            default => null,
        };

        $image->setImageFormat($this->imagickFormat($targetType));

        if ($quality !== null) {
            $image->setImageCompressionQuality($quality);
        }

        return $image;
    }

    private function flattenToWhite(Imagick $image): void
    {
        $flattened = new Imagick();
        $flattened->newImage($image->getImageWidth(), $image->getImageHeight(), new ImagickPixel('white'));
        $flattened->setImageFormat('png32');
        $flattened->compositeImage($image, Imagick::COMPOSITE_OVER, 0, 0);
        $image->clear();
        $image->readImageBlob($flattened->getImageBlob());
        $flattened->clear();
        $flattened->destroy();
    }

    private function imagickFormat(string $type): string
    {
        return match ($type) {
            'jpg' => 'jpeg',
            'webp' => 'webp',
            'avif' => 'avif',
            default => 'png32',
        };
    }
}
