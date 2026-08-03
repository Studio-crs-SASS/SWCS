<?php

declare(strict_types=1);

namespace Engine\Crawl;

class SiteCrawler
{
    protected array $config;

    protected UrlNormalizer $normalizer;

    protected array $queue = [];

    protected array $visited = [];

    protected array $discovered = [];

    protected array $excluded = [];

    protected array $failed = [];

    public function __construct(array $config, string $baseUrl)
    {
        $this->config = $config;
        $this->normalizer = new UrlNormalizer($config, $baseUrl);
    }

    public function crawl(string $startUrl): array
    {
        $startedAt = date('c');

        $this->enqueue($startUrl, 0, $startUrl);

        $maxPages = (int) ($this->config['limits']['max_pages'] ?? 100);
        $maxDepth = (int) ($this->config['limits']['max_depth'] ?? 6);
        $delayMs = (int) ($this->config['limits']['request_delay_ms'] ?? 300);

        while (!empty($this->queue) && count($this->visited) < $maxPages) {
            $item = array_shift($this->queue);

            $url = $item['normalized_url'];
            $depth = $item['depth'];

            if (isset($this->visited[$url])) {
                continue;
            }

            if ($depth > $maxDepth) {
                $this->excluded[$url] = [
                    'url' => $url,
                    'reason' => 'max_depth_exceeded',
                    'depth' => $depth,
                ];
                continue;
            }

            $html = $this->fetchHtml($url);

            if ($html === null) {
                $this->failed[$url] = [
                    'url' => $url,
                    'reason' => 'fetch_failed',
                    'depth' => $depth,
                ];
                continue;
            }

            $links = $this->extractLinks($html, $url);

            $this->visited[$url] = [
                'url' => $url,
                'depth' => $depth,
                'html' => $html,
                'html_length' => strlen($html),
                'links_found' => count($links),
            ];

            foreach ($links as $link) {
                $this->enqueue($link, $depth + 1, $url);
            }

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        $completedAt = date('c');

        return [
            'status' => 'success',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'max_pages' => $maxPages,
            'max_depth' => $maxDepth,
            'limit_reached' => count($this->visited) >= $maxPages,
            'discovered_count' => count($this->discovered),
            'visited_count' => count($this->visited),
            'excluded_count' => count($this->excluded),
            'failed_count' => count($this->failed),
            'visited' => array_values($this->visited),
            'excluded' => array_values($this->excluded),
            'failed' => array_values($this->failed),
        ];
    }

    protected function enqueue(string $url, int $depth, string $fromUrl): void
    {
        $normalized = $this->normalizer->normalize($url, $fromUrl);

        if ($normalized === null) {
            return;
        }

        $normalizedUrl = $normalized['normalized_url'];

        $this->discovered[$normalizedUrl] = [
            'url' => $normalizedUrl,
            'depth' => $depth,
            'from_url' => $fromUrl,
            'is_same_domain' => $normalized['is_same_domain'],
            'is_file' => $normalized['is_file'],
            'is_excluded' => $normalized['is_excluded'],
            'exclude_reason' => $normalized['exclude_reason'],
        ];

        if (!$normalized['is_same_domain']) {
            return;
        }

        if ($normalized['is_file'] || $normalized['is_excluded']) {
            $this->excluded[$normalizedUrl] = [
                'url' => $normalizedUrl,
                'reason' => $normalized['exclude_reason'] !== '' ? $normalized['exclude_reason'] : 'excluded',
                'depth' => $depth,
            ];
            return;
        }

        if (isset($this->visited[$normalizedUrl])) {
            return;
        }

        foreach ($this->queue as $queued) {
            if ($queued['normalized_url'] === $normalizedUrl) {
                return;
            }
        }

        $this->queue[] = [
            'normalized_url' => $normalizedUrl,
            'depth' => $depth,
            'from_url' => $fromUrl,
        ];
    }

    protected function fetchHtml(string $url): ?string
    {
        $timeout = (int) ($this->config['limits']['request_timeout'] ?? 15);

        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'user_agent' => $this->config['user_agent'] ?? 'SWCS/1.0 SiteCrawler',
                'ignore_errors' => true,
            ],
        ]);

        $html = @file_get_contents($url, false, $context);

        if ($html === false || trim($html) === '') {
            return null;
        }

        return $html;
    }

    protected function extractLinks(string $html, string $currentUrl): array
    {
        $links = [];

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();

        if (@$dom->loadHTML($html) === false) {
            libxml_clear_errors();
            return [];
        }

        $anchors = $dom->getElementsByTagName('a');

        foreach ($anchors as $anchor) {
            $href = trim((string) $anchor->getAttribute('href'));

            if ($href === '') {
                continue;
            }

            $links[] = $href;
        }

        libxml_clear_errors();

        return array_values(array_unique($links));
    }
}
