<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | SWCS Site Crawl Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines how SWCS crawls a target website.
    | SWCS is responsible for collection, classification, organization,
    | integration, and visualization.
    |
    | Diagnosis, scoring, and recommendations are handled by SADS.
    |
    */

    'enabled' => true,

    'limits' => [
        'max_pages' => 100,
        'max_depth' => 6,
        'request_timeout' => 15,
        'redirect_limit' => 5,
        'request_delay_ms' => 300,
        'retry_count' => 1,
    ],

    'scope' => [
        'same_domain_only' => true,
        'include_subdomains' => false,
        'treat_www_as_same_domain' => true,
        'include_query_strings' => false,
        'html_only' => true,
    ],

    'respect' => [
        'robots_txt' => true,
        'noindex' => true,
        'nofollow' => true,
    ],

    'query' => [
        'remove_parameters' => [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'gclid',
            'fbclid',
        ],

        'exclude_patterns' => [
            's',
            'search',
            'filter',
            'sort',
        ],
    ],

    'exclude' => [
        'paths' => [
            '/wp-admin',
            '/wp-login.php',
            '/wp-json',
            '/admin',
            '/login',
            '/account',
            '/search',
            '/feed',
            '/trackback',
            '/comments/feed',
            '/cart',
            '/checkout',
            '/my-account',
        ],

        'extensions' => [
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'svg',
            'pdf',
            'zip',
            'mp3',
            'wav',
            'mp4',
            'mov',
            'avi',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
        ],
    ],

    'link_classification' => [
        'types' => [
            'internal',
            'external',
            'social',
            'email',
            'telephone',
            'files',
        ],

        'social_domains' => [
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
        ],
    ],

    'language' => [
        'detect_from_path' => true,

        'known_paths' => [
            'jp',
            'ja',
            'en',
            'fr',
            'de',
            'es',
            'it',
            'zh',
            'ko',
        ],
    ],

    'user_agent' => 'SWCS/1.0 SiteCrawler',
];
