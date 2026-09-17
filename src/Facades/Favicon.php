<?php

namespace Backstage\Favicon\Facades;

use Backstage\Favicon\FaviconManager;
use Backstage\Favicon\PendingFavicon;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PendingFavicon for(string $url)
 *
 * @see FaviconManager
 */
class Favicon extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FaviconManager::class;
    }
}
