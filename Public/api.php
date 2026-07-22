<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

define('SWCS_ROOT', dirname(__DIR__));
define('SWCS_START_TIME', microtime(true));

spl_autoload_register(function (string $class): void {
    $path = SWCS_ROOT . '/' . str_replace('\\', '/', $class) . '.php';

    if (file_exists($path)) {
        require_once $path;
        return;
    }

    $fallbacks = [
        SWCS_ROOT . '/Engine/core/Engine.php',
    ];

    foreach ($fallbacks as $fallback) {
        if (file_exists($fallback)) {
            require_once $fallback;
        }
    }
});

use Engine\Core\Engine;

function swcs_plain_text(string $html): string
{
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html) ?? $html;
    $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

function swcs_extract_headings(string $html): array
{
    $headings = [];

    for ($i = 1; $i <= 6; $i++) {
        $headings['h' . $i] = [];

        if (preg_match_all('/<h' . $i . '\b[^>]*>(.*?)<\/h' . $i . '>/is', $html, $matches)) {
            foreach ($matches[1] as $headingHtml) {
                $headingText = trim(html_entity_decode(strip_tags($headingHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($headingText !== '') {
                    $headings['h' . $i][] = $headingText;
                }
            }
        }
    }

    return $headings;
}

function swcs_extract_sections(string $html, array $headings): array
{
    $sections = [];

    foreach ($headings as $level => $items) {
        foreach ($items as $index => $text) {
            $sections[] = [
                'level' => $level,
                'index' => $index + 1,
                'title' => $text,
            ];
        }
    }

    if (empty($sections) && preg_match_all('/<section\b[^>]*>/is', $html, $matches)) {
        foreach ($matches[0] as $index => $section) {
            $sections[] = [
                'level' => 'section',
                'index' => $index + 1,
                'title' => 'section_' . ($index + 1),
            ];
        }
    }

    return $sections;
}

function swcs_extract_links(string $html, string $baseDomain): array
{
    $all = [];
    $internal = [];
    $external = [];

    if (preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $href = trim($match[1]);
            $text = trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($href === '' || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            $item = [
                'url' => $href,
                'text' => $text,
            ];

            $all[] = $item;

            $linkDomain = parse_url($href, PHP_URL_HOST);

            if ($linkDomain === null || $linkDomain === '' || $linkDomain === $baseDomain) {
                $internal[] = $item;
            } else {
                $external[] = $item;
            }
        }
    }

    return [
        'all' => $all,
        'internal' => $internal,
        'external' => $external,
        'counts' => [
            'all' => count($all),
            'internal' => count($internal),
            'external' => count($external),
        ],
    ];
}

function swcs_extract_images(string $html): array
{
    $images = [];
    $altMissing = 0;

    if (preg_match_all('/<img\b[^>]*>/is', $html, $matches)) {
        foreach ($matches[0] as $imgTag) {
            preg_match('/src=["\']([^"\']+)["\']/i', $imgTag, $srcMatch);
            preg_match('/alt=["\']([^"\']*)["\']/i', $imgTag, $altMatch);

            $src = $srcMatch[1] ?? '';
            $alt = $altMatch[1] ?? '';

            if ($alt === '') {
                $altMissing++;
            }

            $images[] = [
                'src' => $src,
                'alt' => $alt,
            ];
        }
    }

    return [
        'items' => $images,
        'image_stats' => [
            'count' => count($images),
            'alt_missing' => $altMissing,
            'alt_rate' => count($images) > 0 ? round((count($images) - $altMissing) / count($images), 3) : 0,
        ],
    ];
}

function swcs_extract_cta(string $html, array $links): array
{
    $ctaWords = [
        'お問い合わせ',
        '問合せ',
        '相談',
        '予約',
        '申し込み',
        '申込み',
        '資料請求',
        'contact',
        'reserve',
        'booking',
        'apply',
        'start',
        'more',
    ];

    $ctaLinks = [];

    foreach ($links['all'] as $link) {
        $text = mb_strtolower($link['text'] ?? '');
        $url = mb_strtolower($link['url'] ?? '');

        foreach ($ctaWords as $word) {
            $needle = mb_strtolower($word);
            if (str_contains($text, $needle) || str_contains($url, $needle)) {
                $ctaLinks[] = $link;
                break;
            }
        }
    }

    return [
        'count' => count($ctaLinks),
        'items' => $ctaLinks,
        'keywords' => $ctaWords,
    ];
}

function swcs_extract_keywords(string $text): array
{
    preg_match_all('/[A-Za-z][A-Za-z0-9\-]{2,}|[一-龠ぁ-んァ-ンー]{2,}/u', $text, $matches);

    $words = $matches[0] ?? [];
    $counts = [];

    foreach ($words as $word) {
        $word = mb_strtolower($word);

        if (mb_strlen($word) < 2) {
            continue;
        }

        $counts[$word] = ($counts[$word] ?? 0) + 1;
    }

    arsort($counts);

    return array_slice($counts, 0, 20, true);
}

$config = [];
$configPath = SWCS_ROOT . '/Config';

if (is_dir($configPath)) {
    foreach (glob($configPath . '/*.php') as $file) {
        $key = basename($file, '.php');
        $config[$key] = require $file;
    }
}

$url = $_GET['url'] ?? $_POST['url'] ?? null;

if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
    http_response_code(400);

    echo json_encode(
        [
            'status' => 'error',
            'system' => 'SWCS',
            'version' => $config['app']['version'] ?? '1.0',
            'message' => 'Valid url parameter is required.',
            'target' => [
                'url' => $url,
                'domain' => null,
                'checked_at' => date(DATE_ATOM),
            ],
            'data' => [],
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

$request = [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri' => $_SERVER['REQUEST_URI'] ?? '/api.php',
    'input' => [
        'url' => $url,
    ],
];

$engine = new Engine($config);
$result = $engine->run($request);

$collection = $result['collection'] ?? [];
$parsed = $result['parsed'] ?? [];
$structure = $result['structure'] ?? [];
$normalized = $result['normalized'] ?? [];
$validation = $result['validation'] ?? [];

$html = is_string($collection['html'] ?? null) ? $collection['html'] : '';
$text = swcs_plain_text($html);
$domain = parse_url($url, PHP_URL_HOST) ?: '';

$page = $structure['page'] ?? [];
$title = $page['title'] ?? ($parsed['title'] ?? '');
$headings = $page['headings'] ?? swcs_extract_headings($html);
$sections = swcs_extract_sections($html, $headings);
$links = swcs_extract_links($html, $domain);
$media = swcs_extract_images($html);
$cta = swcs_extract_cta($html, $links);
$keywords = swcs_extract_keywords($text);

$contentBlocks = [
    'title' => $title,
    'plain_text' => mb_substr($text, 0, 5000),
    'text_length' => mb_strlen($text),
    'word_count' => str_word_count($text),
    'keyword_count' => count($keywords),
    'sections_count' => count($sections),
];

$output = [
    'status' => $result['status'] ?? 'success',
    'system' => 'SWCS',
    'version' => $config['app']['version'] ?? '1.0',
    'target' => [
        'url' => $url,
        'domain' => $domain,
        'checked_at' => date(DATE_ATOM),
    ],
    'data' => [
        'access' => array_merge(
            $result['access'] ?? [],
            [
                'response' => [
                    'status_code' => $result['access']['status_code'] ?? null,
                    'accessible' => $result['access']['accessible'] ?? null,
                ],
            ]
        ),
        'meta' => array_merge(
            $parsed['meta'] ?? $normalized['meta'] ?? [],
            [
                'title' => $title,
                'normalized_at' => date(DATE_ATOM),
                'updated_at' => null,
                'freshness' => [
                    'last_modified' => null,
                    'checked_at' => date(DATE_ATOM),
                ],
            ]
        ),
        'structure' => array_merge(
            $structure,
            [
                'title' => $title,
                'headings' => $headings,
                'heading_tree' => [
                    'h1' => $headings['h1'] ?? [],
                    'h2' => $headings['h2'] ?? [],
                    'h3' => $headings['h3'] ?? [],
                    'h4' => $headings['h4'] ?? [],
                    'h5' => $headings['h5'] ?? [],
                    'h6' => $headings['h6'] ?? [],
                ],
                'sections' => $sections,
                'section_count' => count($sections),
                'page_structure' => [
                    'has_title' => $title !== '',
                    'h1_count' => count($headings['h1'] ?? []),
                    'h2_count' => count($headings['h2'] ?? []),
                    'h3_count' => count($headings['h3'] ?? []),
                    'section_count' => count($sections),
                    'link_count' => $links['counts']['all'],
                ],
                'content_blocks' => $contentBlocks,
                'navigation' => [
                    'internal_link_count' => $links['counts']['internal'],
                    'external_link_count' => $links['counts']['external'],
                    'has_navigation' => $links['counts']['internal'] > 0,
                ],
            ]
        ),
        'content' => [
            'success' => $collection['success'] ?? false,
            'length' => $collection['length'] ?? 0,
            'html_preview' => $collection['html_preview'] ?? null,
            'parsed' => $parsed,
            'text' => $text,
            'text_length' => mb_strlen($text),
            'word_count' => str_word_count($text),
            'keywords' => $keywords,
            'content_blocks' => $contentBlocks,
            'coverage' => [
                'has_title' => $title !== '',
                'has_h1' => count($headings['h1'] ?? []) > 0,
                'has_sections' => count($sections) > 0,
                'has_internal_links' => $links['counts']['internal'] > 0,
                'has_external_links' => $links['counts']['external'] > 0,
                'has_cta' => $cta['count'] > 0,
            ],
        ],
        'links' => [
            'all' => $links['all'],
            'internal' => $links['internal'],
            'external' => $links['external'],
            'counts' => $links['counts'],
        ],
        'media' => $media,
        'relationship' => [
            'internal_links' => $links['internal'],
            'external_links' => $links['external'],
            'keyword_consistency' => [
                'keywords' => $keywords,
                'top_keywords' => array_keys($keywords),
            ],
            'context_connection' => [
                'title' => $title,
                'headings' => $headings,
                'links' => $links['all'],
            ],
        ],
        'flow' => [
            'navigation' => [
                'has_navigation' => $links['counts']['internal'] > 0,
                'internal_link_count' => $links['counts']['internal'],
            ],
            'page_transition' => [
                'internal_links' => $links['internal'],
                'internal_link_count' => $links['counts']['internal'],
            ],
            'cta' => $cta,
            'user_flow' => [
                'has_title' => $title !== '',
                'has_h1' => count($headings['h1'] ?? []) > 0,
                'has_internal_links' => $links['counts']['internal'] > 0,
                'has_cta' => $cta['count'] > 0,
            ],
        ],
        'performance' => [
            'html_length' => $collection['length'] ?? 0,
            'text_length' => mb_strlen($text),
            'execution_time' => round(microtime(true) - SWCS_START_TIME, 5),
        ],
        'validation' => array_merge(
            $validation,
            [
                'heading_errors' => [],
                'structure_warnings' => [],
            ]
        ),
    ],
    'metadata' => [
        'engine' => $result['engine'] ?? 'SWCS',
        'mode' => $result['mode'] ?? ($config['engine']['mode'] ?? 'unknown'),
        'generated_at' => date(DATE_ATOM),
    ],
];

echo json_encode(
    $output,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);