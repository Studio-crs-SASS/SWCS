<?php

declare(strict_types=1);

namespace Engine\Aggregation;

class SiteAggregator
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function aggregate(array $crawlResult, array $pageResults): array
    {
        $links = $this->aggregateLinks($pageResults);
        $media = $this->aggregateMedia($pageResults);
        $structure = $this->aggregateStructure($pageResults);
        $content = $this->aggregateContent($pageResults);
        $performance = $this->aggregatePerformance($pageResults);

        return [
            'access' => [
                'success_count' => $crawlResult['visited_count'] ?? count($pageResults),
                'failed_count' => $crawlResult['failed_count'] ?? 0,
                'excluded_count' => $crawlResult['excluded_count'] ?? 0,
            ],

            'meta' => [
                'site_title' => $pageResults[0]['meta']['title'] ?? '',
                'site_description' => $pageResults[0]['meta']['description'] ?? '',
                'page_titles' => array_values(array_filter(array_map(
                    fn ($page) => $page['meta']['title'] ?? '',
                    $pageResults
                ))),
            ],

            'structure' => $structure,
            'content' => $content,
            'links' => $links,
            'media' => $media,

            'relationship' => [
                'internal_links' => $links['internal'],
                'external_links' => $links['external'],
                'social_links' => $links['social'],
                'email_links' => $links['email'],
                'telephone_links' => $links['telephone'],
                'file_links' => $links['files'],
            ],

            'flow' => [
                'page_transition' => [
                    'visited_pages' => array_map(
                        fn ($page) => [
                            'url' => $page['url'] ?? '',
                            'depth' => $page['depth'] ?? 0,
                            'internal_link_count' => $page['links']['counts']['internal'] ?? 0,
                        ],
                        $pageResults
                    ),
                ],
            ],

            'performance' => $performance,

            'validation' => [
                'valid' => true,
                'issues' => [],
                'crawl_failed_count' => $crawlResult['failed_count'] ?? 0,
                'crawl_excluded_count' => $crawlResult['excluded_count'] ?? 0,
            ],

            'pages' => $pageResults,

            'crawl' => [
                'status' => $crawlResult['status'] ?? 'unknown',
                'started_at' => $crawlResult['started_at'] ?? '',
                'completed_at' => $crawlResult['completed_at'] ?? '',
                'max_pages' => $crawlResult['max_pages'] ?? null,
                'max_depth' => $crawlResult['max_depth'] ?? null,
                'limit_reached' => $crawlResult['limit_reached'] ?? false,
                'discovered_count' => $crawlResult['discovered_count'] ?? 0,
                'visited_count' => $crawlResult['visited_count'] ?? count($pageResults),
                'success_count' => count($pageResults),
                'failed_count' => $crawlResult['failed_count'] ?? 0,
                'excluded_count' => $crawlResult['excluded_count'] ?? 0,
                'failed' => $crawlResult['failed'] ?? [],
                'excluded' => $crawlResult['excluded'] ?? [],
            ],
        ];
    }

    protected function aggregateLinks(array $pageResults): array
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

        foreach ($pageResults as $page) {
            foreach (['all', 'internal', 'external', 'social', 'email', 'telephone', 'files'] as $type) {
                foreach (($page['links'][$type] ?? []) as $link) {
                    $link['source_page'] = $page['url'] ?? '';
                    $result[$type][] = $link;
                }
            }
        }

        foreach (['all', 'internal', 'external', 'social', 'email', 'telephone', 'files'] as $type) {
            $result[$type] = $this->uniqueLinks($result[$type]);
            $result['counts'][$type] = count($result[$type]);
        }

        return $result;
    }

    protected function aggregateMedia(array $pageResults): array
    {
        $images = [];

        foreach ($pageResults as $page) {
            foreach (($page['media']['images'] ?? []) as $image) {
                $image['source_page'] = $page['url'] ?? '';
                $images[] = $image;
            }
        }

        return [
            'images' => $images,
            'image_count' => count($images),
        ];
    }

    protected function aggregateStructure(array $pageResults): array
    {
        $headings = [];

        foreach ($pageResults as $page) {
            foreach (($page['structure']['headings'] ?? []) as $heading) {
                $heading['source_page'] = $page['url'] ?? '';
                $headings[] = $heading;
            }
        }

        return [
            'headings' => $headings,
            'heading_count' => count($headings),
            'page_count' => count($pageResults),
        ];
    }

    protected function aggregateContent(array $pageResults): array
    {
        $totalTextLength = 0;
        $totalWordCount = 0;

        foreach ($pageResults as $page) {
            $totalTextLength += (int) ($page['content']['text_length'] ?? 0);
            $totalWordCount += (int) ($page['content']['word_count'] ?? 0);
        }

        return [
            'page_count' => count($pageResults),
            'total_text_length' => $totalTextLength,
            'total_word_count' => $totalWordCount,
            'average_text_length' => count($pageResults) > 0 ? round($totalTextLength / count($pageResults), 1) : 0,
        ];
    }

    protected function aggregatePerformance(array $pageResults): array
    {
        $totalHtmlLength = 0;

        foreach ($pageResults as $page) {
            $totalHtmlLength += (int) ($page['performance']['html_length'] ?? 0);
        }

        return [
            'total_html_length' => $totalHtmlLength,
            'average_html_length' => count($pageResults) > 0 ? round($totalHtmlLength / count($pageResults), 1) : 0,
        ];
    }

    protected function uniqueLinks(array $links): array
    {
        $seen = [];
        $unique = [];

        foreach ($links as $link) {
            $key = $link['normalized_url'] ?? $link['url'] ?? '';

            if ($key === '') {
                continue;
            }

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $link;
        }

        return $unique;
    }
}
