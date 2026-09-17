<?php

namespace Backstage\Favicon;

use Backstage\Favicon\Sources\FaviconSource;
use Backstage\Favicon\Sources\HtmlLinkParser;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

class FaviconDiscoverer
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly HtmlLinkParser $parser,
        private readonly array $config,
    ) {}

    /**
     * Discover candidate favicon sources for a site, ranked best-first.
     *
     * @return FaviconSource[]
     */
    public function discover(string $siteUrl): array
    {
        $base = $this->normalizeBaseUrl($siteUrl);
        $explicit = [];

        try {
            $response = $this->request($base);

            if ($response->successful()) {
                $parsed = $this->parser->parse($response->body(), $base);
                $explicit = array_merge($explicit, $parsed['icons']);

                if ($parsed['manifest']) {
                    $explicit = array_merge($explicit, $this->manifestIcons($parsed['manifest'], $base));
                }
            }
        } catch (Throwable) {
            // Fall through to the /favicon.ico convention below.
        }

        // The /favicon.ico convention is a guess, not something the page
        // asserted, so it's always tried last — it never outranks a
        // candidate the site actually declared, regardless of format.
        $fallback = new FaviconSource(rtrim($base, '/').'/favicon.ico', 'ico');

        return [...$this->rank($explicit), $fallback];
    }

    /**
     * @return FaviconSource[]
     */
    private function manifestIcons(string $manifestUrl, string $base): array
    {
        try {
            $response = $this->request($manifestUrl);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json() ?? [];

            return $this->parser->parseManifestIcons($data['icons'] ?? [], $base);
        } catch (Throwable) {
            return [];
        }
    }

    private function request(string $url)
    {
        return $this->http
            ->withUserAgent($this->config['http']['user_agent'])
            ->timeout($this->config['http']['timeout'])
            ->withOptions(['allow_redirects' => ['max' => 5]])
            ->get($url);
    }

    /**
     * @param  FaviconSource[]  $candidates
     * @return FaviconSource[]
     */
    private function rank(array $candidates): array
    {
        $priority = $this->config['source_priority'];
        $seen = [];
        $unique = [];

        foreach ($candidates as $candidate) {
            if (isset($seen[$candidate->url])) {
                continue;
            }

            $seen[$candidate->url] = true;
            $unique[] = $candidate;
        }

        usort($unique, function (FaviconSource $a, FaviconSource $b) use ($priority) {
            $rankA = $priority[$a->type] ?? 99;
            $rankB = $priority[$b->type] ?? 99;

            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            return ($b->sizeHint ?? 0) <=> ($a->sizeHint ?? 0);
        });

        return $unique;
    }

    private function normalizeBaseUrl(string $url): string
    {
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? $url;
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$host}{$port}/";
    }
}
