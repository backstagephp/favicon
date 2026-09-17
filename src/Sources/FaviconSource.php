<?php

namespace Backstage\Favicon\Sources;

final class FaviconSource
{
    public function __construct(
        public readonly string $url,
        public readonly string $type,
        public readonly ?int $sizeHint = null,
    ) {}
}
