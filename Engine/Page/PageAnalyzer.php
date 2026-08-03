<?php

declare(strict_types=1);

namespace Engine\Page;

use Engine\Crawl\UrlNormalizer;

class PageAnalyzer
{
    protected array $config;

    protected UrlNormalizer $normalizer;

    public function __construct(array $config, string $baseUrl)
    {
        $this->config = $config;
        $this->normalizer = new UrlNormalizer($config, $baseUrl);
    }

    public function analyze(array $page): array
    {
        $url = (string) ($page['url'] ?? '');
        $depth = (int) ($page['depth'] ?? 0);
        $html = (string) ($page['html'] ?? '');

        $dom = $this->loadDom($html);

        $title = $this->extractTitle($dom);
        $description = $this->extractMeta($dom, 'description');
        $keywords = $this->extractMeta($dom, 'keywords');
        $headings = $this->extractHeadings($dom);
        $text = $this->extractText($dom);
        $links = $this->extractLinks($dom, $url);
        $images = $this->extractImages($dom);

        return [
            'url' => $url,
            'depth' => $depth,
            'status' => 'success',

            'meta' => [
                'title' => $title,
                'description' => $description,
                'keywords' => $keywords,
            ],

            'structure' => [
                'headings' => $headings,
                'heading_count' => count($headings),
            ],

            'content' => [
                'text_length' => mb_strlen($text),
                'word_count' => str_word_count($text),
                'text_preview' => mb_substr($text, 0, 300),
            ],

            'links' => $links,

            'media' => [
                'images' => $images,
                'image_count' => count($images),
            ],

            'performance' => [
                'html_length' => strlen($html),
            ],
        ];
    }

    protected function loadDom(string $html): \DOMDocument
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();

        @$dom->loadHTML($html);

        libxml_clear_errors();

        return $dom;
    }

    protected function extractTitle(\DOMDocument $dom): string
    {
        $titles = $dom->getElementsByTagName('title');

        if ($titles->length === 0) {
            return '';
        }

        return trim((string) $titles->item(0)?->textContent);
    }

    protected function extractMeta(\DOMDocument $dom, string $name): string
    {
        $metas = $dom->getElementsByTagName('meta');

        foreach ($metas as $meta) {
            $metaName = strtolower((string) $meta->getAttribute('name'));

            if ($metaName === strtolower($name)) {
                return trim((string) $meta->getAttribute('content'));
            }
        }

        return '';
    }

    protected function extractHeadings(\DOMDocument $dom): array
    {
        $headings = [];

        for ($level = 1; $level <= 6; $level++) {
            $nodes = $dom->getElementsByTagName('h' . $level);

            foreach ($nodes as $node) {
                $text = trim((string) $node->textContent);

                if ($text === '') {
                    continue;
                }

                $headings[] = [
                    'level' => $level,
                    'text' => $text,
                ];
            }
        }

        return $headings;
    }

    protected function extractText(\DOMDocument $dom): string
    {
        $body = $dom->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return '';
        }

        $text = preg_replace('/\s+/u', ' ', (string) $body->textContent);

        return trim((string) $text);
    }

    protected function extractLinks(\DOMDocument $dom, string $currentUrl): array
    {
        $result = [
            'all' => [],
            'internal' => [],
            'external' => [],
            'social' => [],
            'email' => [],
            'telephone' => [],
            'files' => [],
            'counts' => [
                'all' => 0,
                'internal' => 0,
                'external' => 0,
                'social' => 0,
                'email' => 0,
                'telephone' => 0,
                'files' => 0,
            ],
        ];

        $anchors = $dom->getElementsByTagName('a');

        foreach ($anchors as $anchor) {
            $href = trim((string) $anchor->getAttribute('href'));

            if ($href === '') {
                continue;
            }

            $normalized = $this->normalizer->normalize($href, $currentUrl);

            if ($normalized === null) {
                continue;
            }

            $item = [
                'url' => $href,
                'normalized_url' => $normalized['normalized_url'],
                'text' => trim((string) $anchor->textContent),
                'title' => (string) $anchor->getAttribute('title'),
                'target' => (string) $anchor->getAttribute('target'),
                'rel' => (string) $anchor->getAttribute('rel'),
            ];

            $scheme = $normalized['scheme'];

            $result['all'][] = $item;

            if ($scheme === 'mailto') {
                $item['email_address'] = $this->extractEmailAddress($href);
                $result['email'][] = $item;
                continue;
            }

            if ($scheme === 'tel') {
                $item['telephone_number'] = $this->extractTelephoneNumber($href);
                $result['telephone'][] = $item;
                continue;
            }

            if ($normalized['is_file']) {
                $item['extension'] = $normalized['extension'];
                $result['files'][] = $item;
                continue;
            }

            if ($normalized['is_same_domain']) {
                $result['internal'][] = $item;
                continue;
            }

            $result['external'][] = $item;

            if ($this->isSocialDomain((string) $normalized['host'])) {
                $result['social'][] = $item;
            }
        }

        foreach ($result['counts'] as $key => $_) {
            $result['counts'][$key] = count($result[$key]);
        }

        return $result;
    }

    protected function extractImages(\DOMDocument $dom): array
    {
        $images = [];

        $nodes = $dom->getElementsByTagName('img');

        foreach ($nodes as $node) {
            $src = trim((string) $node->getAttribute('src'));

            if ($src === '') {
                continue;
            }

            $images[] = [
                'src' => $src,
                'alt' => (string) $node->getAttribute('alt'),
                'title' => (string) $node->getAttribute('title'),
            ];
        }

        return $images;
    }

    protected function isSocialDomain(string $host): bool
    {
        $host = strtolower(preg_replace('/^www\./', '', $host));

        foreach (($this->config['link_classification']['social_domains'] ?? []) as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    protected function extractEmailAddress(string $href): string
    {
        $value = substr($href, 7);
        $value = explode('?', $value, 2)[0];

        return trim(rawurldecode($value));
    }

    protected function extractTelephoneNumber(string $href): string
    {
        $value = substr($href, 4);
        $value = explode('?', $value, 2)[0];

        return trim(rawurldecode($value));
    }
}
