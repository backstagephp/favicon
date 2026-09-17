<?php

namespace Backstage\Favicon\Jobs;

use Backstage\Favicon\FaviconManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchFaviconJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  string[]|null  $types
     * @param  int[]|null  $sizes
     */
    public function __construct(
        public readonly string $url,
        public readonly ?array $types = null,
        public readonly ?array $sizes = null,
        public readonly bool $force = false,
    ) {}

    public function handle(FaviconManager $manager): void
    {
        $pending = $manager->for($this->url);

        if ($this->force) {
            $pending->refresh();
        }

        $pending->warm($this->types, $this->sizes);
    }
}
