<?php

namespace Backstage\Favicon;

use Backstage\Favicon\Console\Commands\FetchFaviconCommand;
use Backstage\Favicon\Sources\HtmlLinkParser;
use Backstage\Favicon\View\Components\FaviconComponent;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class FaviconServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/favicon.php', 'favicon');

        $this->app->singleton(FaviconDiscoverer::class, fn ($app) => new FaviconDiscoverer(
            $app->make(HttpFactory::class),
            new HtmlLinkParser(),
            $app['config']['favicon'],
        ));

        $this->app->singleton(FaviconDownloader::class, fn ($app) => new FaviconDownloader(
            $app->make(HttpFactory::class),
            $app['config']['favicon'],
        ));

        $this->app->singleton(ImagickConverter::class, fn ($app) => new ImagickConverter(
            $app['config']['favicon'],
        ));

        $this->app->singleton(FaviconManager::class, fn ($app) => new FaviconManager(
            $app['config']['favicon'],
            $app['filesystem']->disk($app['config']['favicon']['disk']),
            $app->make(FaviconDiscoverer::class),
            $app->make(FaviconDownloader::class),
            $app->make(ImagickConverter::class),
        ));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/favicon.php' => config_path('favicon.php'),
        ], 'favicon-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'favicon');

        Blade::component('favicon', FaviconComponent::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                FetchFaviconCommand::class,
            ]);
        }
    }
}
