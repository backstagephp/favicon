<?php

namespace Backstage\Favicon\Console\Commands;

use Backstage\Favicon\FaviconException;
use Backstage\Favicon\FaviconManager;
use Backstage\Favicon\Jobs\FetchFaviconJob;
use Illuminate\Console\Command;

class FetchFaviconCommand extends Command
{
    protected $signature = 'favicon:fetch
        {url : The site URL to fetch a favicon for}
        {--sizes= : Comma separated sizes, e.g. 16,32,180 (defaults to config)}
        {--types= : Comma separated types, e.g. png,webp (defaults to config)}
        {--force : Ignore the TTL and re-fetch from the source site}
        {--queue : Dispatch to the queue instead of running synchronously}';

    protected $description = 'Fetch, convert, and cache the favicon for a given URL';

    public function handle(FaviconManager $manager): int
    {
        $url = $this->argument('url');
        $sizes = $this->parseList($this->option('sizes'));
        $types = $this->parseList($this->option('types'));
        $force = (bool) $this->option('force');

        if ($this->option('queue')) {
            FetchFaviconJob::dispatch(
                $url,
                $types,
                $sizes ? array_map('intval', $sizes) : null,
                $force,
            );

            $this->info("Queued favicon fetch for [{$url}].");

            return self::SUCCESS;
        }

        try {
            $pending = $manager->for($url);

            if ($force) {
                $pending->refresh();
            }

            $pending->warm($types, $sizes ? array_map('intval', $sizes) : null);
        } catch (FaviconException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Favicon for [{$url}] fetched and cached.");

        return self::SUCCESS;
    }

    private function parseList(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return array_map('trim', explode(',', $value));
    }
}
