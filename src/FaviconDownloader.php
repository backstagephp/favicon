<?php

namespace Backstage\Favicon;

use Backstage\Favicon\Sources\FaviconSource;
use Backstage\Favicon\Support\ImageTypeDetector;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

class FaviconDownloader
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config,
    ) {}

    /**
     * Download and validate the first working candidate, returning its
     * bytes and sniffed (not trusted-from-header) image type.
     *
     * @param  FaviconSource[]  $candidates
     * @return array{bytes: string, type: string, url: string}|null
     */
    public function downloadFirstValid(array $candidates): ?array
    {
        foreach ($candidates as $candidate) {
            $result = $this->attempt($candidate);

            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @return array{bytes: string, type: string, url: string}|null
     */
    private function attempt(FaviconSource $candidate): ?array
    {
        try {
            $response = $this->http
                ->withUserAgent($this->config['http']['user_agent'])
                ->timeout($this->config['http']['timeout'])
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->get($candidate->url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $bytes = $response->body();

        if ($bytes === '' || strlen($bytes) > $this->config['http']['max_bytes']) {
            return null;
        }

        $type = ImageTypeDetector::detect($bytes);

        if ($type === null) {
            return null;
        }

        return ['bytes' => $bytes, 'type' => $type, 'url' => $candidate->url];
    }
}
