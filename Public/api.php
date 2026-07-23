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

function swcs_normalize_space(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\x{00A0}/u', ' ', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

function swcs_get_attr(string $tag, string $attr): string
{
    if (preg_match('/\b' . preg_quote($attr, '/') . '\s*=\s*["\']([^"\']*)["\']/i', $tag, $match)) {
        return trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    return '';
}

function swcs_remove_invisible_html(string $html): string
{
    $patterns = [
        '/<script\b[^>]*>.*?<\/script>/is',
        '/<style\b[^>]*>.*?<\/style>/is',
        '/<noscript\b[^>]*>.*?<\/noscript>/is',
        '/<svg\b[^>]*>.*?<\/svg>/is',
        '/<!--.*?-->/s',
    ];

    foreach ($patterns as $pattern) {
        $html = preg_replace($pattern, ' ', $html) ?? $html;
    }

    return $html;
}

function swcs_plain_text(string $html): string
{
    $html = swcs_remove_invisible_html($html);

    if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $bodyMatch)) {
        $html = $bodyMatch[1];
    }

    $html = preg_replace('/<(br|hr)\b[^>]*>/i', "\n", $html) ?? $html;
    $html = preg_replace('/<\/(p|div|section|article|main|header|footer|nav|li|h1|h2|h3|h4|h5|h6)>/i', "\n", $html) ?? $html;

    $text = strip_tags($html);
    $text = swcs_normalize_space($text);

    return $text;
}

function swcs_extract_meta(string $html): array
{
    $meta = [
        'description' => '',
        'keywords' => '',
        'og_title' => '',
        'og_description' => '',
    ];

    if (preg_match('/<meta\b[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $match)) {
        $meta['description'] = swcs_normalize_space($match[1]);
    }

    if ($meta['description'] === '' && preg_match('/<meta\b[^>]*content=["\']([^"\']*)["\'][^>]*name=["\']description["\'][^>]*>/i', $html, $match)) {
        $meta['description'] = swcs_normalize_space($match[1]);
    }

    if (preg_match('/<meta\b[^>]*name=["\']keywords["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $match)) {
        $meta['keywords'] = swcs_normalize_space($match[1]);
    }

    if (preg_match('/<meta\b[^>]*property=["\']og:title["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $match)) {
        $meta['og_title'] = swcs_normalize_space($match[1]);
    }

    if (preg_match('/<meta\b[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $match)) {
        $meta['og_description'] = swcs_normalize_space($match[1]);
    }

    return $meta;
}

function swcs_extract_headings(string $html): array
{
    $headings = [];

    for ($i = 1; $i <= 6; $i++) {
        $headings['h' . $i] = [];

        if (preg_match_all('/<h' . $i . '\b[^>]*>(.*?)<\/h' . $i . '>/is', $html, $matches)) {
            foreach ($matches[1] as $headingHtml) {
                $headingText = swcs_normalize_space(strip_tags($headingHtml));

                if ($headingText !== '') {
                    $headings['h' . $i][] = $headingText;
                }
            }
        }
    }

    return $headings;
}

function swcs_extract_content_blocks(string $html): array
{
    $cleanHtml = swcs_remove_invisible_html($html);
    $blocks = [];

    $targets = [
        'main',
        'article',
        'section',
        'p',
        'li',
        'div',
    ];

    foreach ($targets as $tagName) {
        if (preg_match_all('/<' . $tagName . '\b[^>]*>(.*?)<\/' . $tagName . '>/is', $cleanHtml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $index => $match) {
                $text = swcs_normalize_space(strip_tags($match[1]));

                if ($text === '' || mb_strlen($text) < 12) {
                    continue;
                }

                $blocks[] = [
                    'type' => $tagName,
                    'index' => $index + 1,
                    'text' => mb_substr($text, 0, 500),
                    'text_length' => mb_strlen($text),
                ];
            }
        }
    }

    usort($blocks, function (array $a, array $b): int {
        return ($b['text_length'] ?? 0) <=> ($a['text_length'] ?? 0);
    });

    return array_slice($blocks, 0, 80);
}

function swcs_extract_sections(string $html, array $headings): array
{
    $sections = [];

    foreach ($headings as $level => $items) {
        foreach ($items as $index => $text) {
            $sections[] = [
                'type' => 'heading',
                'level' => $level,
                'index' => $index + 1,
                'title' => $text,
                'text_preview' => $text,
            ];
        }
    }

    if (preg_match_all('/<section\b[^>]*>(.*?)<\/section>/is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $index => $match) {
            $text = swcs_normalize_space(strip_tags(swcs_remove_invisible_html($match[1])));

            if ($text === '') {
                continue;
            }

            $sections[] = [
                'type' => 'section',
                'level' => 'section',
                'index' => $index + 1,
                'title' => mb_substr($text, 0, 60),
                'text_preview' => mb_substr($text, 0, 300),
                'text_length' => mb_strlen($text),
            ];
        }
    }

    if (preg_match_all('/<article\b[^>]*>(.*?)<\/article>/is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $index => $match) {
            $text = swcs_normalize_space(strip_tags(swcs_remove_invisible_html($match[1])));

            if ($text === '') {
                continue;
            }

            $sections[] = [
                'type' => 'article',
                'level' => 'article',
                'index' => $index + 1,
                'title' => mb_substr($text, 0, 60),
                'text_preview' => mb_substr($text, 0, 300),
                'text_length' => mb_strlen($text),
            ];
        }
    }

    return $sections;
}

function swcs_normalize_url(string $href, string $baseUrl): string
{
    $href = trim($href);

    if ($href === '') {
        return '';
    }

    if (preg_match('/^(https?:)?\/\//i', $href)) {
        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        return $href;
    }

    if (str_starts_with($href, '#')) {
        return $baseUrl . $href;
    }

    $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
    $host = parse_url($baseUrl, PHP_URL_HOST) ?: '';
    $path = parse_url($baseUrl, PHP_URL_PATH) ?: '/';

    if ($host === '') {
        return $href;
    }

    if (str_starts_with($href, '/')) {
        return $scheme . '://' . $host . $href;
    }

    $dir = rtrim(dirname($path), '/');
    $dir = $dir === '' ? '' : $dir;

    return $scheme . '://' . $host . $dir . '/' . $href;
}

function swcs_extract_links(string $html, string $baseUrl, string $baseDomain): array
{
    $all = [];
    $internal = [];
    $external = [];

    if (preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $tag = $match[0];
            $href = trim($match[1]);
            $text = swcs_normalize_space(strip_tags($match[2]));

            if ($href === '' || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:') || str_starts_with($href, 'javascript:')) {
                continue;
            }

            $normalizedUrl = swcs_normalize_url($href, $baseUrl);
            $linkDomain = parse_url($normalizedUrl, PHP_URL_HOST) ?: '';

            $item = [
                'url' => $href,
                'normalized_url' => $normalizedUrl,
                'text' => $text,
                'title' => swcs_get_attr($tag, 'title'),
                'aria_label' => swcs_get_attr($tag, 'aria-label'),
                'class' => swcs_get_attr($tag, 'class'),
                'id' => swcs_get_attr($tag, 'id'),
                'target' => swcs_get_attr($tag, 'target'),
                'rel' => swcs_get_attr($tag, 'rel'),
            ];

            $all[] = $item;

            if ($linkDomain === '' || $linkDomain === $baseDomain) {
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
            $src = swcs_get_attr($imgTag, 'src');
            $alt = swcs_get_attr($imgTag, 'alt');

            if ($alt === '') {
                $altMissing++;
            }

            $images[] = [
                'src' => $src,
                'alt' => $alt,
                'title' => swcs_get_attr($imgTag, 'title'),
                'class' => swcs_get_attr($imgTag, 'class'),
                'id' => swcs_get_attr($imgTag, 'id'),
                'loading' => swcs_get_attr($imgTag, 'loading'),
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

function swcs_cta_keywords(): array
{
    return [
        'お問い合わせ',
        '問合せ',
        'お問合せ',
        '相談',
        '無料相談',
        '予約',
        'ご予約',
        '申し込み',
        '申込み',
        '申込',
        '資料請求',
        '見積',
        '見積もり',
        '導入',
        '依頼',
        '購入',
        '注文',
        '登録',
        '体験',
        '診断',
        'チェック',
        '始める',
        '詳しく',
        'もっと見る',
        '続きを読む',
        'contact',
        'inquiry',
        'reserve',
        'reservation',
        'booking',
        'book',
        'apply',
        'start',
        'trial',
        'free',
        'quote',
        'estimate',
        'consult',
        'consultation',
        'download',
        'schedule',
        'order',
        'buy',
        'purchase',
        'register',
        'signup',
        'sign up',
        'learn more',
        'more',
        'detail',
        'details',
    ];
}

function swcs_is_cta_text(string $value, array $ctaWords): bool
{
    $target = mb_strtolower($value);

    foreach ($ctaWords as $word) {
        $needle = mb_strtolower($word);

        if ($needle !== '' && str_contains($target, $needle)) {
            return true;
        }
    }

    return false;
}

function swcs_extract_cta(string $html, array $links): array
{
    $ctaWords = swcs_cta_keywords();
    $items = [];
    $searchForms = [];
    $seen = [];

    $negativeWords = [
        'search',
        '検索',
        'site-search',
        'search-box',
        'input-box',
    ];

    $isNegative = function (string $value) use ($negativeWords): bool {
        $target = mb_strtolower($value);

        foreach ($negativeWords as $word) {
            if ($word !== '' && str_contains($target, mb_strtolower($word))) {
                return true;
            }
        }

        return false;
    };

    foreach ($links['all'] as $link) {
        $haystack = implode(' ', [
            $link['text'] ?? '',
            $link['url'] ?? '',
            $link['normalized_url'] ?? '',
            $link['title'] ?? '',
            $link['aria_label'] ?? '',
            $link['class'] ?? '',
            $link['id'] ?? '',
        ]);

        if ($isNegative($haystack)) {
            continue;
        }

        if (swcs_is_cta_text($haystack, $ctaWords)) {
            $key = 'link:' . ($link['normalized_url'] ?? $link['url'] ?? '') . ':' . ($link['text'] ?? '');

            if (!isset($seen[$key])) {
                $items[] = [
                    'type' => 'link',
                    'text' => $link['text'] ?? '',
                    'url' => $link['url'] ?? '',
                    'normalized_url' => $link['normalized_url'] ?? '',
                    'title' => $link['title'] ?? '',
                    'aria_label' => $link['aria_label'] ?? '',
                    'class' => $link['class'] ?? '',
                    'id' => $link['id'] ?? '',
                ];
                $seen[$key] = true;
            }
        }
    }

    if (preg_match_all('/<button\b[^>]*>(.*?)<\/button>/is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $index => $match) {
            $tag = $match[0];
            $text = swcs_normalize_space(strip_tags($match[1]));
            $haystack = implode(' ', [
                $text,
                swcs_get_attr($tag, 'title'),
                swcs_get_attr($tag, 'aria-label'),
                swcs_get_attr($tag, 'class'),
                swcs_get_attr($tag, 'id'),
                swcs_get_attr($tag, 'name'),
                swcs_get_attr($tag, 'value'),
            ]);

            if ($isNegative($haystack)) {
                continue;
            }

            if ($text !== '' || swcs_is_cta_text($haystack, $ctaWords)) {
                $items[] = [
                    'type' => 'button',
                    'index' => $index + 1,
                    'text' => $text,
                    'title' => swcs_get_attr($tag, 'title'),
                    'aria_label' => swcs_get_attr($tag, 'aria-label'),
                    'class' => swcs_get_attr($tag, 'class'),
                    'id' => swcs_get_attr($tag, 'id'),
                ];
            }
        }
    }

    if (preg_match_all('/<input\b[^>]*>/is', $html, $matches)) {
        foreach ($matches[0] as $index => $tag) {
            $type = mb_strtolower(swcs_get_attr($tag, 'type'));
            $value = swcs_get_attr($tag, 'value');
            $haystack = implode(' ', [
                $type,
                $value,
                swcs_get_attr($tag, 'title'),
                swcs_get_attr($tag, 'aria-label'),
                swcs_get_attr($tag, 'class'),
                swcs_get_attr($tag, 'id'),
                swcs_get_attr($tag, 'name'),
            ]);

            if ($isNegative($haystack)) {
                continue;
            }

            if (in_array($type, ['submit', 'button'], true) || swcs_is_cta_text($haystack, $ctaWords)) {
                $items[] = [
                    'type' => 'input',
                    'index' => $index + 1,
                    'input_type' => $type,
                    'value' => $value,
                    'aria_label' => swcs_get_attr($tag, 'aria-label'),
                    'class' => swcs_get_attr($tag, 'class'),
                    'id' => swcs_get_attr($tag, 'id'),
                    'name' => swcs_get_attr($tag, 'name'),
                ];
            }
        }
    }

    if (preg_match_all('/<form\b[^>]*>/is', $html, $matches)) {
        foreach ($matches[0] as $index => $tag) {
            $haystack = implode(' ', [
                swcs_get_attr($tag, 'action'),
                swcs_get_attr($tag, 'method'),
                swcs_get_attr($tag, 'title'),
                swcs_get_attr($tag, 'aria-label'),
                swcs_get_attr($tag, 'class'),
                swcs_get_attr($tag, 'id'),
                swcs_get_attr($tag, 'name'),
            ]);

            $formItem = [
                'type' => 'form',
                'index' => $index + 1,
                'action' => swcs_get_attr($tag, 'action'),
                'method' => swcs_get_attr($tag, 'method'),
                'aria_label' => swcs_get_attr($tag, 'aria-label'),
                'class' => swcs_get_attr($tag, 'class'),
                'id' => swcs_get_attr($tag, 'id'),
                'is_cta_like' => swcs_is_cta_text($haystack, $ctaWords) && !$isNegative($haystack),
                'is_search_like' => $isNegative($haystack),
            ];

            if ($formItem['is_cta_like']) {
                $items[] = $formItem;
            } elseif ($formItem['is_search_like']) {
                $searchForms[] = $formItem;
            }
        }
    }

    return [
        'count' => count($items),
        'items' => $items,
        'keywords' => $ctaWords,
        'link_count' => count(array_filter($items, fn (array $item): bool => ($item['type'] ?? '') === 'link')),
        'button_count' => count(array_filter($items, fn (array $item): bool => ($item['type'] ?? '') === 'button')),
        'input_count' => count(array_filter($items, fn (array $item): bool => ($item['type'] ?? '') === 'input')),
        'form_count' => count(array_filter($items, fn (array $item): bool => ($item['type'] ?? '') === 'form')),
        'search_form_count' => count($searchForms),
        'search_forms' => $searchForms,
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

    return array_slice($counts, 0, 30, true);
}

function swcs_word_count(string $text): int
{
    preg_match_all('/[A-Za-z][A-Za-z0-9\-]*|[一-龠ぁ-んァ-ンー]{2,}/u', $text, $matches);

    return count($matches[0] ?? []);
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
$metaExtra = swcs_extract_meta($html);
$title = $page['title'] ?? ($parsed['title'] ?? ($metaExtra['og_title'] ?? ''));
$headings = $page['headings'] ?? swcs_extract_headings($html);
$sections = swcs_extract_sections($html, $headings);
$links = swcs_extract_links($html, $url, $domain);
$media = swcs_extract_images($html);
$cta = swcs_extract_cta($html, $links);
$keywords = swcs_extract_keywords($text);
$contentBlockItems = swcs_extract_content_blocks($html);
$wordCount = swcs_word_count($text);

$contentBlocks = [
    'title' => $title,
    'plain_text' => mb_substr($text, 0, 10000),
    'text_length' => mb_strlen($text),
    'word_count' => $wordCount,
    'keyword_count' => count($keywords),
    'sections_count' => count($sections),
    'content_block_count' => count($contentBlockItems),
    'items' => $contentBlockItems,
];

$headingErrors = [];
$structureWarnings = [];

if (count($headings['h1'] ?? []) === 0) {
    $headingErrors[] = 'H1 heading is missing.';
}

if (count($headings['h1'] ?? []) > 1) {
    $headingErrors[] = 'Multiple H1 headings detected.';
}

if (mb_strlen($text) < 1000) {
    $structureWarnings[] = 'Extracted text length is under 1000 characters.';
}

if ($cta['count'] === 0) {
    $structureWarnings[] = 'CTA elements were not detected.';
}

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
            $metaExtra,
            [
                'title' => $title,
                'normalized_at' => date(DATE_ATOM),
                'updated_at' => null,
                'freshness' => [
                    'last_modified' => $result['access']['headers']['last-modified'] ?? null,
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
                    'content_block_count' => count($contentBlockItems),
                    'link_count' => $links['counts']['all'],
                    'cta_count' => $cta['count'],
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
            'word_count' => $wordCount,
            'keywords' => $keywords,
            'content_blocks' => $contentBlocks,
            'coverage' => [
                'has_title' => $title !== '',
                'has_description' => ($metaExtra['description'] ?? '') !== '',
                'has_h1' => count($headings['h1'] ?? []) > 0,
                'has_h2' => count($headings['h2'] ?? []) > 0,
                'has_sections' => count($sections) > 0,
                'has_content_blocks' => count($contentBlockItems) > 0,
                'has_internal_links' => $links['counts']['internal'] > 0,
                'has_external_links' => $links['counts']['external'] > 0,
                'has_images' => ($media['image_stats']['count'] ?? 0) > 0,
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
                'description' => $metaExtra['description'] ?? '',
                'headings' => $headings,
                'sections' => $sections,
                'links' => $links['all'],
                'content_blocks' => $contentBlockItems,
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
                'cta_count' => $cta['count'],
            ],
        ],
        'performance' => [
            'html_length' => $collection['length'] ?? 0,
            'text_length' => mb_strlen($text),
            'word_count' => $wordCount,
            'execution_time' => round(microtime(true) - SWCS_START_TIME, 5),
        ],
        'validation' => array_merge(
            $validation,
            [
                'heading_errors' => $headingErrors,
                'structure_warnings' => $structureWarnings,
            ]
        ),
    ],
    'metadata' => [
        'engine' => $result['engine'] ?? 'SWCS',
        'mode' => $result['mode'] ?? ($config['engine']['mode'] ?? 'unknown'),
        'generated_at' => date(DATE_ATOM),
        'api_revision' => 'SWCS Public API Ver.1.1',
    ],
];

echo json_encode(
    $output,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);