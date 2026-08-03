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

    return swcs_normalize_space($text);
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

function swcs_is_social_domain(string $domain): bool
{
    $domain = strtolower(trim($domain));
    $domain = preg_replace('/^www\./', '', $domain) ?? $domain;

    $socialDomains = [
        'instagram.com',
        'facebook.com',
        'fb.com',
        'x.com',
        'twitter.com',
        'youtube.com',
        'youtu.be',
        'linkedin.com',
        'tiktok.com',
        'threads.net',
        'pinterest.com',
        'line.me',
        'lin.ee',
        't.me',
    ];

    foreach ($socialDomains as $socialDomain) {
        if (
            $domain === $socialDomain ||
            str_ends_with($domain, '.' . $socialDomain)
        ) {
            return true;
        }
    }

    return false;
}

function swcs_extract_links(string $html, string $baseUrl, string $baseDomain): array
{
    $all = [];
    $internal = [];
    $external = [];
    $social = [];
    $email = [];

    if (preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $tag = $match[0];
            $href = trim($match[1]);
            $text = swcs_normalize_space(strip_tags($match[2]));

            if (
                $href === '' ||
                str_starts_with($href, 'tel:') ||
                str_starts_with($href, 'javascript:')
            ) {
                continue;
            }

            if (str_starts_with(strtolower($href), 'mailto:')) {
                $emailAddressPart = substr($href, 7);
                $emailAddress = rawurldecode(
                    explode('?', $emailAddressPart, 2)[0]
                );

                $item = [
                    'url' => $href,
                    'normalized_url' => $href,
                    'email_address' => trim($emailAddress),
                    'text' => $text,
                    'title' => swcs_get_attr($tag, 'title'),
                    'aria_label' => swcs_get_attr($tag, 'aria-label'),
                    'class' => swcs_get_attr($tag, 'class'),
                    'id' => swcs_get_attr($tag, 'id'),
                    'target' => swcs_get_attr($tag, 'target'),
                    'rel' => swcs_get_attr($tag, 'rel'),
                ];

                $all[] = $item;
                $email[] = $item;

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

                if (swcs_is_social_domain($linkDomain)) {
                    $social[] = $item;
                }
            }
        }
    }

    return [
        'all' => $all,
        'internal' => $internal,
        'external' => $external,
        'social' => $social,
        'email' => $email,
        'counts' => [
            'all' => count($all),
            'internal' => count($internal),
            'external' => count($external),
            'social' => count($social),
            'email' => count($email),
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

function swcs_extract_semantic_tags(string $html): array
{
    $tags = [
        'header' => preg_match_all('/<header\b[^>]*>/is', $html),
        'main' => preg_match_all('/<main\b[^>]*>/is', $html),
        'article' => preg_match_all('/<article\b[^>]*>/is', $html),
        'section' => preg_match_all('/<section\b[^>]*>/is', $html),
        'nav' => preg_match_all('/<nav\b[^>]*>/is', $html),
        'aside' => preg_match_all('/<aside\b[^>]*>/is', $html),
        'footer' => preg_match_all('/<footer\b[^>]*>/is', $html),
        'form' => preg_match_all('/<form\b[^>]*>/is', $html),
    ];

    return [
        'counts' => $tags,
        'has_header' => ($tags['header'] ?? 0) > 0,
        'has_main' => ($tags['main'] ?? 0) > 0,
        'has_article' => ($tags['article'] ?? 0) > 0,
        'has_section' => ($tags['section'] ?? 0) > 0,
        'has_nav' => ($tags['nav'] ?? 0) > 0,
        'has_footer' => ($tags['footer'] ?? 0) > 0,
        'semantic_tag_count' => array_sum($tags),
    ];
}

function swcs_build_dom_summary(string $html, array $headings, array $links, array $media, array $contentBlockItems): array
{
    return [
        'html_length' => mb_strlen($html),
        'heading_count' => array_sum(array_map('count', $headings)),
        'h1_count' => count($headings['h1'] ?? []),
        'h2_count' => count($headings['h2'] ?? []),
        'h3_count' => count($headings['h3'] ?? []),
        'link_count' => $links['counts']['all'] ?? 0,
        'internal_link_count' => $links['counts']['internal'] ?? 0,
        'external_link_count' => $links['counts']['external'] ?? 0,
        'image_count' => $media['image_stats']['count'] ?? 0,
        'content_block_count' => count($contentBlockItems),
        'estimated_dom_tags' => preg_match_all('/<([a-z0-9]+)\b[^>]*>/i', $html),
    ];
}

function swcs_build_page_patterns(array $semanticTags, array $headings, array $links, array $cta): array
{
    $patterns = [];

    if (($semanticTags['has_main'] ?? false) || ($semanticTags['has_article'] ?? false)) {
        $patterns[] = 'main_content_present';
    }

    if (count($headings['h1'] ?? []) > 0) {
        $patterns[] = 'headline_present';
    }

    if (($links['counts']['internal'] ?? 0) > 0) {
        $patterns[] = 'internal_navigation_present';
    }

    if (($cta['count'] ?? 0) > 0) {
        $patterns[] = 'cta_present';
    }

    if (($semanticTags['has_footer'] ?? false)) {
        $patterns[] = 'footer_present';
    }

    return [
        'patterns' => $patterns,
        'pattern_count' => count($patterns),
        'page_type_candidate' => count($headings['h1'] ?? []) > 0 ? 'landing_or_top_page' : 'unknown',
        'has_basic_page_pattern' => count($patterns) >= 3,
    ];
}

function swcs_build_template_consistency(array $headings, array $contentBlockItems, array $links): array
{
    return [
        'has_consistent_heading_base' => count($headings['h1'] ?? []) >= 1,
        'has_content_blocks' => count($contentBlockItems) > 0,
        'has_navigation_links' => ($links['counts']['internal'] ?? 0) > 0,
        'consistency_score_base' => round((
            (count($headings['h1'] ?? []) >= 1 ? 1 : 0) +
            (count($contentBlockItems) > 0 ? 1 : 0) +
            (($links['counts']['internal'] ?? 0) > 0 ? 1 : 0)
        ) / 3, 3),
    ];
}

function swcs_build_text_blocks(array $contentBlockItems): array
{
    return array_map(function (array $block): array {
        return [
            'type' => $block['type'] ?? 'unknown',
            'text' => $block['text'] ?? '',
            'text_length' => $block['text_length'] ?? 0,
        ];
    }, $contentBlockItems);
}

function swcs_extract_unique_terms(array $keywords): array
{
    return [
        'items' => array_keys($keywords),
        'count' => count($keywords),
        'weighted' => $keywords,
    ];
}

function swcs_extract_unique_phrases(array $contentBlockItems): array
{
    $phrases = [];

    foreach ($contentBlockItems as $block) {
        $text = $block['text'] ?? '';
        $parts = preg_split('/[。．.!！？\?]/u', $text) ?: [];

        foreach ($parts as $part) {
            $phrase = swcs_normalize_space($part);

            if ($phrase !== '' && mb_strlen($phrase) >= 8) {
                $phrases[$phrase] = ($phrases[$phrase] ?? 0) + 1;
            }
        }
    }

    return [
        'items' => array_slice(array_keys($phrases), 0, 30),
        'count' => count($phrases),
    ];
}

function swcs_build_originality_signals(array $uniqueTerms, array $uniquePhrases, int $wordCount): array
{
    $termCount = $uniqueTerms['count'] ?? 0;
    $phraseCount = $uniquePhrases['count'] ?? 0;

    return [
        'unique_term_count' => $termCount,
        'unique_phrase_count' => $phraseCount,
        'word_count' => $wordCount,
        'term_diversity_rate' => $wordCount > 0 ? round($termCount / $wordCount, 3) : 0,
        'has_originality_signals' => $termCount > 0 || $phraseCount > 0,
    ];
}

function swcs_extract_date_mentions(string $text): array
{
    $items = [];

    $patterns = [
        '/\b20\d{2}[.\-\/年]\s?\d{1,2}[.\-\/月]\s?\d{1,2}日?\b/u',
        '/\b20\d{2}年\b/u',
        '/\b20\d{2}[.\-\/]\d{1,2}[.\-\/]\d{1,2}\b/u',
        '/\b20\d{2}\.\d{2}\.\d{2}\b/u',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches)) {
            foreach ($matches[0] as $match) {
                $items[] = $match;
            }
        }
    }

    $items = array_values(array_unique($items));

    return [
        'items' => $items,
        'count' => count($items),
    ];
}

function swcs_build_related_terms(array $keywords, array $headings, string $title): array
{
    $headingText = [];

    foreach ($headings as $items) {
        foreach ($items as $item) {
            $headingText[] = $item;
        }
    }

    return [
        'title_terms' => swcs_extract_keywords($title),
        'heading_terms' => swcs_extract_keywords(implode(' ', $headingText)),
        'content_terms' => $keywords,
        'top_related_terms' => array_slice(array_keys($keywords), 0, 15),
    ];
}

function swcs_build_duplicate_blocks(array $contentBlockItems): array
{
    $seen = [];
    $duplicates = [];

    foreach ($contentBlockItems as $block) {
        $text = swcs_normalize_space($block['text'] ?? '');

        if ($text === '') {
            continue;
        }

        $hash = md5($text);

        if (isset($seen[$hash])) {
            $duplicates[] = [
                'text' => mb_substr($text, 0, 200),
                'text_length' => mb_strlen($text),
            ];
        }

        $seen[$hash] = true;
    }

    return [
        'items' => $duplicates,
        'count' => count($duplicates),
        'duplicate_rate' => count($contentBlockItems) > 0 ? round(count($duplicates) / count($contentBlockItems), 3) : 0,
    ];
}

function swcs_build_semantic_groups(array $keywords): array
{
    $groups = [
        'brand' => [],
        'service' => [],
        'action' => [],
        'location' => [],
        'other' => [],
    ];

    foreach ($keywords as $term => $count) {
        $lower = mb_strtolower((string)$term);

        if (
            str_contains($lower, 'studio') ||
            str_contains($lower, 'life') ||
            str_contains($lower, 'escortist') ||
            str_contains($lower, 'syuji')
        ) {
            $groups['brand'][$term] = $count;
        } elseif (
            str_contains($lower, 'design') ||
            str_contains($lower, 'personal') ||
            str_contains($lower, 'official')
        ) {
            $groups['service'][$term] = $count;
        } elseif (
            str_contains($lower, 'contact') ||
            str_contains($lower, '予約') ||
            str_contains($lower, '相談')
        ) {
            $groups['action'][$term] = $count;
        } elseif (
            str_contains($lower, 'osaka') ||
            str_contains($lower, '大阪') ||
            str_contains($lower, '岬')
        ) {
            $groups['location'][$term] = $count;
        } else {
            $groups['other'][$term] = $count;
        }
    }

    return [
        'groups' => $groups,
        'group_count' => count(array_filter($groups, fn (array $items): bool => count($items) > 0)),
    ];
}

function swcs_extract_videos(string $html): array
{
    $videos = [];

    if (preg_match_all('/<(video|iframe)\b[^>]*>/is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $index => $match) {
            $tag = $match[0];

            $videos[] = [
                'type' => strtolower($match[1]),
                'index' => $index + 1,
                'src' => swcs_get_attr($tag, 'src'),
                'title' => swcs_get_attr($tag, 'title'),
                'class' => swcs_get_attr($tag, 'class'),
                'id' => swcs_get_attr($tag, 'id'),
            ];
        }
    }

    return [
        'items' => $videos,
        'count' => count($videos),
    ];
}

function swcs_build_alt_texts(array $media): array
{
    $items = [];

    foreach (($media['items'] ?? []) as $image) {
        $items[] = [
            'src' => $image['src'] ?? '',
            'alt' => $image['alt'] ?? '',
            'has_alt' => ($image['alt'] ?? '') !== '',
        ];
    }

    return [
        'items' => $items,
        'count' => count($items),
        'missing_count' => $media['image_stats']['alt_missing'] ?? 0,
    ];
}

function swcs_build_cta_texts(array $cta): array
{
    $texts = [];

    foreach (($cta['items'] ?? []) as $item) {
        $candidate = $item['text'] ?? $item['value'] ?? $item['aria_label'] ?? '';

        if ($candidate !== '') {
            $texts[] = $candidate;
        }
    }

    $texts = array_values(array_unique($texts));

    return [
        'items' => $texts,
        'count' => count($texts),
    ];
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

$mode = $_GET['mode'] ?? $_POST['mode'] ?? '';

if ($mode === 'site_crawl') {
    $crawlConfig = $config['crawl'] ?? [];

    $configuredMaxPages = (int) ($crawlConfig['limits']['max_pages'] ?? 100);
    $requestedMaxPages = isset($_GET['crawl_max_pages']) ? (int) $_GET['crawl_max_pages'] : $configuredMaxPages;

    if ($requestedMaxPages > 0) {
        $crawlConfig['limits']['max_pages'] = min($requestedMaxPages, $configuredMaxPages);
    }

    if (isset($_GET['crawl_max_depth'])) {
        $configuredMaxDepth = (int) ($crawlConfig['limits']['max_depth'] ?? 6);
        $requestedMaxDepth = (int) $_GET['crawl_max_depth'];

        if ($requestedMaxDepth > 0) {
            $crawlConfig['limits']['max_depth'] = min($requestedMaxDepth, $configuredMaxDepth);
        }
    }

    $domain = parse_url($url, PHP_URL_HOST) ?: '';

    $crawler = new \Engine\Crawl\SiteCrawler($crawlConfig, $url);
    $crawlResult = $crawler->crawl($url);

    $analyzer = new \Engine\Page\PageAnalyzer($crawlConfig, $url);

    $pageResults = [];

    foreach (($crawlResult['visited'] ?? []) as $page) {
        $pageResults[] = $analyzer->analyze($page);
    }

    $aggregator = new \Engine\Aggregation\SiteAggregator($crawlConfig);
    $siteData = $aggregator->aggregate($crawlResult, $pageResults);

    $output = [
        'status' => 'success',
        'system' => 'SWCS',
        'version' => $config['app']['version'] ?? '1.0',
        'target' => [
            'url' => $url,
            'domain' => $domain,
            'checked_at' => date(DATE_ATOM),
        ],
        'data' => $siteData,
        'metadata' => [
            'engine' => 'SWCS',
            'mode' => 'site_crawl',
            'generated_at' => date(DATE_ATOM),
            'api_revision' => 'SWCS Public API Site Crawl Ver.1.0',
        ],
    ];

    echo json_encode(
        $output,
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

$semanticTags = swcs_extract_semantic_tags($html);
$domSummary = swcs_build_dom_summary($html, $headings, $links, $media, $contentBlockItems);
$pagePatterns = swcs_build_page_patterns($semanticTags, $headings, $links, $cta);
$templateConsistency = swcs_build_template_consistency($headings, $contentBlockItems, $links);
$textBlocks = swcs_build_text_blocks($contentBlockItems);
$uniqueTerms = swcs_extract_unique_terms($keywords);
$uniquePhrases = swcs_extract_unique_phrases($contentBlockItems);
$originalitySignals = swcs_build_originality_signals($uniqueTerms, $uniquePhrases, $wordCount);
$dateMentions = swcs_extract_date_mentions($text);
$relatedTerms = swcs_build_related_terms($keywords, $headings, $title);
$duplicateBlocks = swcs_build_duplicate_blocks($contentBlockItems);
$semanticGroups = swcs_build_semantic_groups($keywords);
$videos = swcs_extract_videos($html);
$altTexts = swcs_build_alt_texts($media);
$ctaTexts = swcs_build_cta_texts($cta);

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
$structureErrors = [];

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

if (($semanticTags['semantic_tag_count'] ?? 0) === 0) {
    $structureErrors[] = 'Semantic HTML tags were not detected.';
}

if (($domSummary['heading_count'] ?? 0) === 0) {
    $structureErrors[] = 'Heading tags were not detected.';
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
                'last_modified' => $result['access']['headers']['last-modified'] ?? null,
                'published_dates' => $dateMentions,
                'sitemap_lastmod' => null,
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
                'semantic_tags' => $semanticTags,
                'dom_summary' => $domSummary,
                'page_patterns' => $pagePatterns,
                'template_consistency' => $templateConsistency,
                'cta' => $cta,
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
            'text_blocks' => $textBlocks,
            'unique_terms' => $uniqueTerms,
            'unique_phrases' => $uniquePhrases,
            'originality_signals' => $originalitySignals,
            'date_mentions' => $dateMentions,
            'related_terms' => $relatedTerms,
            'duplicate_blocks' => $duplicateBlocks,
            'semantic_groups' => $semanticGroups,
            'cta_texts' => $ctaTexts,
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
                'has_social_links' => $links['counts']['social'] > 0,
                'has_email_links' => $links['counts']['email'] > 0,
                'has_images' => ($media['image_stats']['count'] ?? 0) > 0,
                'has_cta' => $cta['count'] > 0,
            ],
        ],
        'links' => [
            'all' => $links['all'],
            'internal' => $links['internal'],
            'external' => $links['external'],
            'social' => $links['social'],
            'email' => $links['email'],
            'counts' => $links['counts'],
        ],
        'media' => array_merge(
            $media,
            [
                'images' => $media['items'] ?? [],
                'videos' => $videos,
                'alt_texts' => $altTexts,
            ]
        ),
        'relationship' => [
            'internal_links' => $links['internal'],
            'external_links' => $links['external'],
            'social_links' => $links['social'],
            'email_links' => $links['email'],
            'related_terms' => $relatedTerms,
            'semantic_groups' => $semanticGroups,
            'duplicate_blocks' => $duplicateBlocks,
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
                'related_terms' => $relatedTerms,
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
            'cta_placement' => [
                'cta_count' => $cta['count'] ?? 0,
                'has_cta' => ($cta['count'] ?? 0) > 0,
                'cta_items' => $cta['items'] ?? [],
            ],
            'conversion_path' => [
                'has_navigation' => ($links['counts']['internal'] ?? 0) > 0,
                'has_cta' => ($cta['count'] ?? 0) > 0,
                'search_form_count' => $cta['search_form_count'] ?? 0,
            ],
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
                'structure_errors' => $structureErrors,
            ]
        ),
    ],
    'metadata' => [
        'engine' => $result['engine'] ?? 'SWCS',
        'mode' => $result['mode'] ?? ($config['engine']['mode'] ?? 'unknown'),
        'generated_at' => date(DATE_ATOM),
        'api_revision' => 'SWCS Public API Ver.1.2',
    ],
];

echo json_encode(
    $output,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);