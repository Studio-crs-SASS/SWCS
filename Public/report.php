<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Tokyo');

/**
 * SWCS Client Report
 * Studio-crs / SEEN / SWCS
 */

$companyName = 'Studio-crs';
$systemName = 'SWCS - Syu Web Check System';
$reportTitle = 'SWCS Web Check Report';
$copyright = '© 2026 Studio-crs. All Rights Reserved.';

$clientName = normalizeClientName((string)($_GET['client'] ?? 'Client 様'));
$personInCharge = trim((string)($_GET['staff'] ?? 'Syuji Konishi'));

$requestedFile = (string)($_GET['file'] ?? '');
$selectedFileName = basename($requestedFile);

if ($selectedFileName === '' || !str_ends_with($selectedFileName, '.json')) {
    renderErrorPage(
        'Report file is not specified.',
        'URLに file=xxxxx.json を指定してください。'
    );
    exit;
}

$jsonPath = __DIR__ . '/../Data/output/' . $selectedFileName;

if (!is_file($jsonPath)) {
    renderErrorPage(
        'Report file was not found.',
        '指定されたJSONファイルが見つかりません。'
    );
    exit;
}

$json = file_get_contents($jsonPath);

if ($json === false) {
    renderErrorPage(
        'Report file could not be read.',
        'JSONファイルを読み込めませんでした。'
    );
    exit;
}

$data = json_decode($json, true);

if (!is_array($data)) {
    renderErrorPage(
        'Invalid JSON format.',
        'JSON形式が正しくありません。'
    );
    exit;
}

/**
 * Data extraction
 */

$access = pickArray($data, ['access', 'Access', 'response', 'Response', 'http']);
$meta = pickArray($data, ['meta', 'Meta', 'metadata', 'Metadata']);
$structure = pickArray($data, ['structure', 'Structure', 'page_structure']);
$content = pickArray($data, ['content', 'Content', 'collection', 'Collection']);
$links = pickArray($data, ['links', 'Links', 'link', 'Link']);
$media = pickArray($data, ['media', 'Media', 'images', 'Images']);
$relationship = pickArray($data, ['relationship', 'Relationship', 'relations', 'Relations']);
$flow = pickArray($data, ['flow', 'Flow', 'user_flow', 'journey']);
$performance = pickArray($data, ['performance', 'Performance', 'timing', 'Timing']);
$validation = pickArray($data, ['validation', 'Validation', 'check', 'Check']);
$crawl = pickArray($data, ['crawl', 'Crawl']);
$pages = findFirstValue($data, [
    ['pages'],
    ['site_pages'],
    ['crawl', 'pages'],
]);

if (!is_array($pages)) {
    $pages = [];
}

$isSiteCrawl = count($crawl) > 0 || count($pages) > 0;

$targetUrl = findFirstString($data, [
    ['target_url'],
    ['url'],
    ['request', 'url'],
    ['input', 'url'],
    ['diagnosis_url'],
    ['diagnostic_url'],
]);

if ($targetUrl === '') {
    $targetUrl = 'https://life-escortist.com';
}

$domain = parse_url($targetUrl, PHP_URL_HOST);
$domain = is_string($domain) && $domain !== '' ? $domain : '-';

$statusCode = findFirstValue($data, [
    ['access', 'status_code'],
    ['access', 'code'],
    ['response', 'status_code'],
    ['response', 'code'],
    ['status_code'],
    ['code'],
]);

$crawlStatus = findFirstString($data, [
    ['crawl', 'status'],
    ['crawl_status'],
]);

$crawlSuccessCount = findFirstValue($data, [
    ['crawl', 'success_count'],
    ['crawl', 'visited_count'],
]);

$isAccessOk = isSuccessfulStatus($statusCode)
    || boolValue(findFirstValue($data, [
        ['access', 'success'],
        ['access', 'ok'],
        ['response', 'success'],
        ['success'],
    ]))
    || (
        $isSiteCrawl
        && (
            strtolower($crawlStatus) === 'success'
            || (is_numeric($crawlSuccessCount) && (int)$crawlSuccessCount > 0)
        )
    );

$htmlLength = findFirstValue($data, [
    ['performance', 'total_html_length'],
    ['performance', 'html_length'],
    ['content', 'html_length'],
    ['collection', 'html_length'],
    ['collection', 'length'],
    ['html_length'],
    ['length'],
]);

$textLength = findFirstValue($data, [
    ['content', 'total_text_length'],
    ['content', 'text_length'],
    ['collection', 'text_length'],
    ['content', 'body_length'],
    ['text_length'],
    ['body_length'],
]);

$pageCount = findFirstValue($data, [
    ['content', 'page_count'],
    ['crawl', 'visited_count'],
    ['pages_count'],
]);

$averageTextLength = findFirstValue($data, [
    ['content', 'average_text_length'],
    ['average_text_length'],
]);

$checkedAtSource = findFirstString($data, [
    ['checked_at'],
    ['timestamp'],
    ['created_at'],
    ['meta', 'normalized_at'],
    ['normalized_at'],
]);

$checkedAt = formatDateTimeJst($checkedAtSource);
$reportGeneratedAt = (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format('Y年m月d日 H:i');

$keywordCandidates = findFirstValue($data, [
    ['content', 'keywords'],
    ['content', 'keyword_candidates'],
    ['content', 'keywordCandidates'],
    ['collection', 'keywords'],
    ['keywords'],
    ['keyword_candidates'],
    ['keywordCandidates'],
]);

if (!is_array($keywordCandidates)) {
    $keywordCandidates = [];
}

$externalLinks = findFirstValue($data, [
    ['links', 'external'],
    ['relationship', 'external_links'],
    ['external_links'],
]);

if (!is_array($externalLinks)) {
    $externalLinks = [];
}

$socialLinks = findFirstValue($data, [
    ['links', 'social'],
    ['relationship', 'social_links'],
    ['social_links'],
]);

if (!is_array($socialLinks)) {
    $socialLinks = [];
}

$emailLinks = findFirstValue($data, [
    ['links', 'email'],
    ['relationship', 'email_links'],
    ['email_links'],
]);

if (!is_array($emailLinks)) {
    $emailLinks = [];
}

$coverageMapItems = buildCoverageMapItems(
    $access,
    $meta,
    $structure,
    $content,
    $links,
    $media,
    $relationship,
    $flow,
    $performance,
    $validation
);

$standardCoverageMapItems = buildStandardCoverageMapItems();

$detailSections = [
    [
        'title' => 'Access / 接続確認',
        'rows' => [
            'アクセス状態' => $isAccessOk ? '確認済み' : '未確認',
            'ステータスコード' => $statusCode !== null ? (string)$statusCode : '未取得',
            '応答情報' => compactValue($access),
        ],
    ],
    [
        'title' => 'Meta / 基本情報',
        'rows' => [
            'ページタイトル' => findFirstString($data, [
                ['meta', 'title'],
                ['title'],
                ['page_title'],
            ]) ?: '未取得',
            '更新確認' => compactValue(findFirstValue($data, [
                ['meta', 'last_modified'],
                ['meta', 'updated_at'],
                ['last_modified'],
            ])),
            '鮮度情報' => compactValue(findFirstValue($data, [
                ['meta', 'freshness'],
                ['meta', 'freshness_info'],
            ])),
            '正規化日時' => formatDateTimeJstUtc(findFirstString($data, [
                ['meta', 'normalized_at'],
                ['normalized_at'],
                ['timestamp'],
            ])),
        ],
    ],
    [
        'title' => 'Structure / 構造確認',
        'rows' => [
            'ページ種別' => compactValue(findFirstValue($data, [
                ['structure', 'page_type'],
                ['structure', 'types'],
                ['page_type'],
            ])),
            '見出し数' => compactValue(findFirstValue($data, [
                ['structure', 'headings'],
                ['headings'],
            ])),
            'セクション数' => compactValue(findFirstValue($data, [
                ['structure', 'sections'],
                ['sections'],
            ])),
            'ナビゲーション' => compactValue(findFirstValue($data, [
                ['structure', 'navigation'],
                ['navigation'],
            ])),
        ],
    ],
    [
        'title' => 'Content / コンテンツ確認',
        'rows' => [
            '取得成功' => $isAccessOk ? '確認済み' : '未確認',
            '確認ページ数' => compactValue($pageCount),
            'HTML量' => compactValue($htmlLength),
            '本文量' => compactValue($textLength),
            '平均本文量' => compactValue($averageTextLength),
            '単語数' => compactValue(findFirstValue($data, [
                ['content', 'total_word_count'],
                ['content', 'word_count'],
                ['collection', 'word_count'],
                ['word_count'],
            ])),
            'カバレッジ' => compactValue(findFirstValue($data, [
                ['content', 'coverage'],
                ['collection', 'coverage'],
                ['coverage'],
            ])),
        ],
    ],
    [
        'title' => 'Links / リンク確認',
        'rows' => [
            '総リンク数' => compactValue(findFirstValue($data, [
                ['links', 'counts', 'all'],
                ['links', 'total'],
                ['links', 'total_count'],
                ['links', 'count'],
                ['total_links'],
            ])),
            '内部リンク数' => compactValue(findFirstValue($data, [
                ['links', 'counts', 'internal'],
                ['links', 'internal_count'],
                ['internal_links'],
            ])),
            '外部リンク数' => compactValue(findFirstValue($data, [
                ['links', 'counts', 'external'],
                ['links', 'external_count'],
                ['external_links'],
            ])),
            'SNSリンク数' => compactValue(findFirstValue($data, [
                ['links', 'counts', 'social'],
                ['social_links_count'],
            ])),
            'メールリンク数' => compactValue(findFirstValue($data, [
                ['links', 'counts', 'email'],
                ['email_links_count'],
            ])),
            '電話リンク数' => compactValue(findFirstValue($data, [
                ['links', 'counts', 'telephone'],
                ['telephone_links_count'],
            ])),
            'ファイルリンク数' => compactValue(findFirstValue($data, [
                ['links', 'counts', 'files'],
                ['file_links_count'],
            ])),
        ],
    ],
    [
        'title' => 'Media / メディア確認',
        'rows' => [
            '画像数' => compactValue(findFirstValue($data, [
                ['media', 'images'],
                ['media', 'image_count'],
                ['image_count'],
                ['images'],
            ])),
            'alt確認' => compactValue(findFirstValue($data, [
                ['media', 'alt'],
                ['media', 'alt_status'],
                ['media', 'alt_check'],
            ])),
            '画像情報' => compactValue($media),
        ],
    ],
    [
        'title' => 'Relationship / 情報接続',
        'rows' => [
            '内部接続' => compactValue(findFirstValue($data, [
                ['relationship', 'internal'],
                ['relationship', 'internal_links'],
                ['internal_links'],
            ])),
            '外部接続' => compactValue(findFirstValue($data, [
                ['relationship', 'external'],
                ['relationship', 'external_links'],
                ['external_links'],
            ])),
            'キーワード整合' => compactValue(findFirstValue($data, [
                ['relationship', 'keyword_alignment'],
                ['relationship', 'keywords'],
            ])),
            '文脈接続' => compactValue(findFirstValue($data, [
                ['relationship', 'context'],
                ['relationship', 'contextual'],
            ])),
        ],
    ],
    [
        'title' => 'Flow / 導線確認',
        'rows' => [
            'ナビゲーション' => compactValue(findFirstValue($data, [
                ['flow', 'navigation'],
                ['flow', 'nav'],
            ])),
            'ページ遷移' => compactValue(findFirstValue($data, [
                ['flow', 'page_transition'],
                ['flow', 'transitions'],
            ])),
            'CTA' => compactValue(findFirstValue($data, [
                ['flow', 'cta'],
                ['flow', 'ctas'],
            ])),
            'ユーザー導線' => compactValue(findFirstValue($data, [
                ['flow', 'user_flow'],
                ['flow', 'journey'],
            ])),
        ],
    ],
    [
        'title' => 'Crawl Summary / 巡回確認',
        'rows' => [
            '巡回モード' => $isSiteCrawl ? 'site_crawl' : 'single_page',
            '巡回ページ数' => compactValue(findFirstValue($data, [
                ['crawl', 'visited_count'],
            ])),
            '取得成功ページ数' => compactValue(findFirstValue($data, [
                ['crawl', 'success_count'],
            ])),
            '取得失敗ページ数' => compactValue(findFirstValue($data, [
                ['crawl', 'failed_count'],
            ])),
            '除外URL数' => compactValue(findFirstValue($data, [
                ['crawl', 'excluded_count'],
            ])),
            '上限到達' => compactValue(findFirstValue($data, [
                ['crawl', 'limit_reached'],
            ])),
        ],
    ],
    [
        'title' => 'Performance / 取得性能',
        'rows' => [
            '総HTML量' => compactValue($htmlLength),
            '総本文量' => compactValue($textLength),
            '平均本文量' => compactValue($averageTextLength),
            '処理時間' => compactValue(findFirstValue($data, [
                ['performance', 'duration'],
                ['performance', 'processing_time'],
                ['processing_time'],
                ['duration'],
            ])),
        ],
    ],
    [
        'title' => 'Validation / 確認結果',
        'rows' => [
            '確認結果' => compactValue(findFirstValue($data, [
                ['validation', 'status'],
                ['validation', 'result'],
                ['validation', 'valid'],
            ])) ?: '確認済み',
            '注意点数' => compactValue(findFirstValue($data, [
                ['validation', 'warnings'],
                ['warnings'],
            ])),
            '構造注意点数' => compactValue(findFirstValue($data, [
                ['validation', 'structure_warnings'],
                ['structure_warnings'],
            ])),
        ],
    ],
];

$checkCoverageItems = [
    ['number' => '01', 'name' => 'Access', 'desc' => 'サイト接続と応答を確認'],
    ['number' => '02', 'name' => 'Meta', 'desc' => 'タイトル・更新情報を確認'],
    ['number' => '03', 'name' => 'Structure', 'desc' => '見出し・ページ構成を確認'],
    ['number' => '04', 'name' => 'Content', 'desc' => '本文量・情報量を確認'],
    ['number' => '05', 'name' => 'Links', 'desc' => '内部リンク・外部リンクを確認'],
    ['number' => '06', 'name' => 'Media', 'desc' => '画像・メディア情報を確認'],
    ['number' => '07', 'name' => 'Relationship', 'desc' => '情報同士の接続を確認'],
    ['number' => '08', 'name' => 'Flow', 'desc' => 'CTA・ユーザー導線を確認'],
    ['number' => '09', 'name' => 'Performance', 'desc' => '取得量・処理時間を確認'],
    ['number' => '10', 'name' => 'Validation', 'desc' => '構造上の注意点を確認'],
];

/**
 * Functions
 */

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalizeClientName(string $name): string
{
    $name = trim($name);

    if ($name === '') {
        return 'Client 様';
    }

    if (str_ends_with($name, '様') || str_ends_with($name, '御中')) {
        return $name;
    }

    return $name . ' 様';
}

function pickArray(array $data, array $keys): array
{
    foreach ($keys as $key) {
        $found = findArrayByKeyRecursive($data, $key);

        if (is_array($found) && count($found) > 0) {
            return $found;
        }
    }

    return [];
}

function findArrayByKeyRecursive(array $data, string $targetKey): ?array
{
    foreach ($data as $key => $value) {
        if ((string)$key === $targetKey && is_array($value)) {
            return $value;
        }

        if (is_array($value)) {
            $found = findArrayByKeyRecursive($value, $targetKey);

            if (is_array($found)) {
                return $found;
            }
        }
    }

    return null;
}

function findFirstValue(array $data, array $paths): mixed
{
    foreach ($paths as $path) {
        $value = getValueByPath($data, $path);

        if ($value !== null && $value !== '') {
            return $value;
        }

        $value = findValueByPathRecursive($data, $path);

        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    return null;
}

function getValueByPath(array $data, array $path): mixed
{
    $current = $data;

    foreach ($path as $key) {
        if (!is_array($current) || !array_key_exists($key, $current)) {
            return null;
        }

        $current = $current[$key];
    }

    return $current;
}

function findValueByPathRecursive(array $data, array $path): mixed
{
    $directValue = getValueByPath($data, $path);

    if ($directValue !== null && $directValue !== '') {
        return $directValue;
    }

    foreach ($data as $value) {
        if (is_array($value)) {
            $found = findValueByPathRecursive($value, $path);

            if ($found !== null && $found !== '') {
                return $found;
            }
        }
    }

    return null;
}

function findFirstString(array $data, array $paths): string
{
    $value = findFirstValue($data, $paths);

    if (is_string($value)) {
        return trim($value);
    }

    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }

    return '';
}

function boolValue(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return $value > 0;
    }

    if (is_string($value)) {
        $value = strtolower(trim($value));
        return in_array($value, ['1', 'true', 'yes', 'ok', 'success', 'confirmed', '確認済み'], true);
    }

    if (is_array($value)) {
        return count($value) > 0;
    }

    return false;
}

function isSuccessfulStatus(mixed $statusCode): bool
{
    if ($statusCode === null || $statusCode === '') {
        return false;
    }

    $code = (int)$statusCode;
    return $code >= 200 && $code < 400;
}

function compactValue(mixed $value): string
{
    if ($value === null || $value === '') {
        return '未取得';
    }

    if (is_bool($value)) {
        return $value ? '確認済み' : '未確認';
    }

    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }

    if (is_string($value)) {
        return trim($value) !== '' ? trim($value) : '未取得';
    }

    if (is_array($value)) {
        $count = count($value);

        if ($count === 0) {
            return '未取得';
        }

        if (isListArray($value)) {
            $scalars = [];

            foreach ($value as $item) {
                if (is_string($item) || is_int($item) || is_float($item)) {
                    $scalars[] = (string)$item;
                }
            }

            if (count($scalars) > 0 && count($scalars) <= 6) {
                return implode(' / ', $scalars);
            }
        }

        return $count . '件';
    }

    return '確認済み';
}

function isListArray(array $array): bool
{
    return array_keys($array) === range(0, count($array) - 1);
}

function formatDateTimeJst(string $value): string
{
    if (trim($value) === '') {
        return '-';
    }

    try {
        $base = new DateTimeImmutable($value);
        return $base->setTimezone(new DateTimeZone('Asia/Tokyo'))->format('Y年m月d日 H:i');
    } catch (Throwable) {
        return $value;
    }
}

function formatDateTimeJstUtc(string $value): string
{
    if (trim($value) === '') {
        return '未取得';
    }

    try {
        $base = new DateTimeImmutable($value);
        $jst = $base->setTimezone(new DateTimeZone('Asia/Tokyo'));
        $utc = $base->setTimezone(new DateTimeZone('UTC'));

        return $jst->format('Y-m-d') . ' / JST ' . $jst->format('H:i:s') . ' / UTC ' . $utc->format('H:i:s');
    } catch (Throwable) {
        return $value;
    }
}

function buildCoverageMapItems(
    array $access,
    array $meta,
    array $structure,
    array $content,
    array $links,
    array $media,
    array $relationship,
    array $flow,
    array $performance,
    array $validation
): array {
    return [
        [
            'label' => 'Access',
            'short' => 'Access',
            'value' => count($access) > 0 ? 100 : 0,
            'status' => count($access) > 0 ? '確認済み' : '未取得',
        ],
        [
            'label' => 'Meta',
            'short' => 'Meta',
            'value' => count($meta) > 0 ? 100 : 0,
            'status' => count($meta) > 0 ? '確認済み' : '未取得',
        ],
        [
            'label' => 'Structure',
            'short' => 'Structure',
            'value' => count($structure) > 0 ? 100 : 0,
            'status' => count($structure) > 0 ? '確認済み' : '未取得',
        ],
        [
            'label' => 'Content',
            'short' => 'Content',
            'value' => count($content) > 0 ? 100 : 0,
            'status' => count($content) > 0 ? '確認済み' : '未取得',
        ],
        [
            'label' => 'Links',
            'short' => 'Links',
            'value' => count($links) > 0 ? 100 : 0,
            'status' => count($links) > 0 ? '確認済み' : '未取得',
        ],
        [
            'label' => 'Media',
            'short' => 'Media',
            'value' => count($media) > 0 ? 60 : 0,
            'status' => count($media) > 0 ? '一部確認' : '未取得',
        ],
        [
            'label' => 'Relationship',
            'short' => 'Relation',
            'value' => count($relationship) > 0 ? 100 : 0,
            'status' => count($relationship) > 0 ? '確認済み' : '未取得',
        ],
        [
            'label' => 'Flow',
            'short' => 'Flow',
            'value' => count($flow) > 0 ? 100 : 0,
            'status' => count($flow) > 0 ? '確認済み' : '未取得',
        ],
        [
            'label' => 'Performance',
            'short' => 'Speed',
            'value' => count($performance) > 0 ? 100 : 0,
            'status' => count($performance) > 0 ? '確認済み' : '未取得',
        ],
        [
            'label' => 'Validation',
            'short' => 'Check',
            'value' => count($validation) > 0 ? 100 : 0,
            'status' => count($validation) > 0 ? '確認済み' : '未取得',
        ],
    ];
}

function buildStandardCoverageMapItems(): array
{
    return [
        ['label' => 'Access', 'short' => 'Access', 'value' => 100, 'status' => '標準領域'],
        ['label' => 'Meta', 'short' => 'Meta', 'value' => 100, 'status' => '標準領域'],
        ['label' => 'Structure', 'short' => 'Structure', 'value' => 100, 'status' => '標準領域'],
        ['label' => 'Content', 'short' => 'Content', 'value' => 100, 'status' => '標準領域'],
        ['label' => 'Links', 'short' => 'Links', 'value' => 100, 'status' => '標準領域'],
        ['label' => 'Media', 'short' => 'Media', 'value' => 100, 'status' => '標準領域'],
        ['label' => 'Relationship', 'short' => 'Relation', 'value' => 100, 'status' => '標準領域'],
        ['label' => 'Flow', 'short' => 'Flow', 'value' => 100, 'status' => '標準領域'],
        ['label' => 'Performance', 'short' => 'Speed', 'value' => 100, 'status' => '標準領域'],
        ['label' => 'Validation', 'short' => 'Check', 'value' => 100, 'status' => '標準領域'],
    ];
}

function renderCoverageMap(array $items): string
{
    $count = count($items);

    if ($count === 0) {
        return '';
    }

    $centerX = 140;
    $centerY = 140;
    $maxRadius = 84;
    $levels = [20, 40, 60, 80, 100];

    $gridPolygons = [];

    foreach ($levels as $level) {
        $radius = $maxRadius * ($level / 100);
        $points = [];

        foreach ($items as $index => $item) {
            $angle = (-90 + (360 / $count) * $index) * M_PI / 180;
            $x = $centerX + cos($angle) * $radius;
            $y = $centerY + sin($angle) * $radius;
            $points[] = round($x, 2) . ',' . round($y, 2);
        }

        $gridPolygons[] = '<polygon points="' . implode(' ', $points) . '" fill="none" stroke="#d8dde7" stroke-width="1" />';
    }

    $axisLines = [];
    $labels = [];
    $dataPoints = [];

    foreach ($items as $index => $item) {
        $angle = (-90 + (360 / $count) * $index) * M_PI / 180;

        $axisX = $centerX + cos($angle) * $maxRadius;
        $axisY = $centerY + sin($angle) * $maxRadius;

        $axisLines[] = '<line x1="' . $centerX . '" y1="' . $centerY . '" x2="' . round($axisX, 2) . '" y2="' . round($axisY, 2) . '" stroke="#e4e7ec" stroke-width="1" />';

        $value = max(0, min(100, (int)($item['value'] ?? 0)));
        $dataRadius = $maxRadius * ($value / 100);

        $dataX = $centerX + cos($angle) * $dataRadius;
        $dataY = $centerY + sin($angle) * $dataRadius;

        $dataPoints[] = round($dataX, 2) . ',' . round($dataY, 2);

        $labelRadius = $maxRadius + 27;
        $labelX = $centerX + cos($angle) * $labelRadius;
        $labelY = $centerY + sin($angle) * $labelRadius;

        $label = h((string)($item['short'] ?? $item['label'] ?? ''));

        $labels[] = '<text x="' . round($labelX, 2) . '" y="' . round($labelY, 2) . '" text-anchor="middle" dominant-baseline="middle" font-size="8.2" fill="#344054">' . $label . '</text>';
    }

    return '
        <svg class="coverage-radar" viewBox="0 0 280 280" role="img" aria-label="SWCS Web確認領域マップ">
            ' . implode("\n", $gridPolygons) . '
            ' . implode("\n", $axisLines) . '
            <polygon points="' . implode(' ', $dataPoints) . '" fill="rgba(29, 38, 54, 0.16)" stroke="#1d2636" stroke-width="2" />
            ' . implode("\n", $labels) . '
            <circle cx="' . $centerX . '" cy="' . $centerY . '" r="3" fill="#1d2636" />
        </svg>
    ';
}

function renderCoveragePanel(string $title, string $subtitle, array $items, bool $showBars = true): void
{
    echo '<section class="coverage-panel">';
    echo '<h3 class="coverage-panel-title">' . h($title) . '</h3>';
    echo '<div class="coverage-panel-subtitle">' . h($subtitle) . '</div>';
    echo '<div class="coverage-panel-radar">' . renderCoverageMap($items) . '</div>';

    if ($showBars) {
        echo '<div class="coverage-mini-list">';

        foreach ($items as $item) {
            echo '<div class="coverage-mini-row">';
            echo '<div class="coverage-mini-label">' . h($item['label']) . '</div>';
            echo '<div class="coverage-mini-status">' . h($item['status']) . '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    echo '</section>';
}

function renderRows(array $rows): void
{
    foreach ($rows as $label => $value) {
        echo '<div class="detail-row">';
        echo '<div class="detail-label">' . h($label) . '</div>';
        echo '<div class="detail-value">' . h(compactValue($value)) . '</div>';
        echo '</div>';
    }
}

function renderLinkListCard(string $title, array $links, int $limit = 10): void
{
    if (count($links) === 0) {
        return;
    }

    echo '<section class="detail-card link-list-card">';
    echo '<h3 class="detail-title">' . h($title) . '</h3>';

    $shown = 0;

    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        if ($shown >= $limit) {
            break;
        }

        $url = $link['normalized_url'] ?? $link['url'] ?? $link['href'] ?? '未取得';
        $sourcePage = $link['source_page'] ?? $link['source'] ?? $link['page_url'] ?? '未取得';
        $linkText = $link['text'] ?? $link['label'] ?? '';
        $domain = $link['domain'] ?? $link['host'] ?? '';

        echo '<div class="detail-row">';
        echo '<div class="detail-label">' . h('リンクURL') . '</div>';
        echo '<div class="detail-value">' . h($url) . '</div>';
        echo '</div>';

        if (trim((string)$domain) !== '') {
            echo '<div class="detail-row">';
            echo '<div class="detail-label">' . h('ドメイン') . '</div>';
            echo '<div class="detail-value">' . h($domain) . '</div>';
            echo '</div>';
        }

        echo '<div class="detail-row">';
        echo '<div class="detail-label">' . h('取得元ページ') . '</div>';
        echo '<div class="detail-value">' . h($sourcePage) . '</div>';
        echo '</div>';

        if (trim((string)$linkText) !== '') {
            echo '<div class="detail-row">';
            echo '<div class="detail-label">' . h('リンクテキスト') . '</div>';
            echo '<div class="detail-value">' . h($linkText) . '</div>';
            echo '</div>';
        }

        $shown++;

        if ($shown < count($links) && $shown < $limit) {
            echo '<div class="detail-separator"></div>';
        }
    }

    echo '</section>';
}

function renderEmailLinks(array $emailLinks, int $limit = 10): void
{
    if (count($emailLinks) === 0) {
        return;
    }

    echo '<section class="detail-card email-links-card">';
    echo '<h3 class="detail-title">Email Links / メールリンク確認</h3>';

    $shown = 0;

    foreach ($emailLinks as $link) {
        if (!is_array($link)) {
            continue;
        }

        if ($shown >= $limit) {
            break;
        }

        $email = $link['email_address'] ?? $link['email'] ?? $link['url'] ?? '未取得';
        $sourcePage = $link['source_page'] ?? $link['source'] ?? $link['page_url'] ?? '未取得';
        $linkText = $link['text'] ?? $link['label'] ?? '';

        echo '<div class="detail-row">';
        echo '<div class="detail-label">' . h('メール') . '</div>';
        echo '<div class="detail-value">' . h($email) . '</div>';
        echo '</div>';

        echo '<div class="detail-row">';
        echo '<div class="detail-label">' . h('取得元ページ') . '</div>';
        echo '<div class="detail-value">' . h($sourcePage) . '</div>';
        echo '</div>';

        if (trim((string)$linkText) !== '') {
            echo '<div class="detail-row">';
            echo '<div class="detail-label">' . h('リンクテキスト') . '</div>';
            echo '<div class="detail-value">' . h($linkText) . '</div>';
            echo '</div>';
        }

        $shown++;

        if ($shown < count($emailLinks) && $shown < $limit) {
            echo '<div class="detail-separator"></div>';
        }
    }

    echo '</section>';
}

function renderKeywordCandidates(array $keywords, int $limit = 10): void
{
    $normalizedKeywords = normalizeKeywordCandidates($keywords);

    if (count($normalizedKeywords) === 0) {
        return;
    }

    $displayKeywords = array_slice($normalizedKeywords, 0, $limit);
    $remaining = count($normalizedKeywords) - count($displayKeywords);

    echo '<section class="keyword-candidates page-break-before">';
    echo '<h3 class="keyword-candidates-title">Keyword Candidates</h3>';
    echo '<div class="keyword-candidates-subtitle">キーワード候補一覧</div>';
    echo '<ul class="keyword-list">';

    foreach ($displayKeywords as $index => $keywordText) {
        echo '<li class="keyword-item">';
        echo '<span class="keyword-number">' . h((string)($index + 1)) . '</span>';
        echo '<span class="keyword-text">' . h($keywordText) . '</span>';
        echo '</li>';
    }

    echo '</ul>';

    if ($remaining > 0) {
        echo '<div class="keyword-more">ほか' . h((string)$remaining) . '件の候補を取得しています。</div>';
    }

    echo '</section>';
}

function normalizeKeywordCandidates(array $keywords): array
{
    $result = [];

    foreach ($keywords as $key => $value) {
        $keywordText = '';

        if (is_string($key) && !is_numeric($key)) {
            $keywordText = $key;
        }

        if (is_string($value)) {
            $keywordText = $value;
        }

        if (is_int($value) || is_float($value)) {
            if (is_string($key) && !is_numeric($key)) {
                $keywordText = $key;
            } else {
                $keywordText = (string)$value;
            }
        }

        if (is_array($value)) {
            $keywordText = findKeywordText($value);
        }

        $keywordText = trim($keywordText);

        if ($keywordText !== '') {
            $result[] = $keywordText;
        }
    }

    return array_values(array_unique($result));
}

function findKeywordText(array $keyword): string
{
    foreach (['keyword', 'word', 'term', 'label', 'name', 'text'] as $key) {
        if (isset($keyword[$key]) && (is_string($keyword[$key]) || is_int($keyword[$key]) || is_float($keyword[$key]))) {
            return (string)$keyword[$key];
        }
    }

    foreach ($keyword as $key => $value) {
        if (is_string($key) && !is_numeric($key)) {
            return $key;
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string)$value;
        }
    }

    return '';
}

function renderErrorPage(string $title, string $message): void
{
    http_response_code(400);

    echo '<!doctype html>';
    echo '<html lang="ja">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>SWCS Report Error</title>';
    echo '<style>';
    echo 'body{font-family:Arial,sans-serif;background:#f4f6f8;color:#1d2636;padding:40px;}';
    echo '.box{max-width:720px;margin:0 auto;background:#fff;border:1px solid #d8dde7;border-radius:18px;padding:28px;}';
    echo 'h1{font-size:22px;margin:0 0 12px;}';
    echo 'p{line-height:1.8;}';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<div class="box">';
    echo '<h1>' . h($title) . '</h1>';
    echo '<p>' . h($message) . '</p>';
    echo '</div>';
    echo '</body>';
    echo '</html>';
}

?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= h($reportTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        :root {
            --ink: #1d2636;
            --muted: #667085;
            --line: #d8dde7;
            --soft-line: #eaecf0;
            --paper: #ffffff;
            --soft: #f8fafc;
            --chip: #eef2f7;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef2f6;
            color: var(--ink);
            font-family: -apple-system, BlinkMacSystemFont, "Hiragino Sans", "Yu Gothic", "YuGothic", "Noto Sans JP", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.7;
        }

        .page {
            max-width: 980px;
            margin: 32px auto;
            padding: 44px;
            background: var(--paper);
            border-radius: 24px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.10);
        }

        .top-label {
            letter-spacing: 0.28em;
            text-transform: uppercase;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
        }

        .report-title {
            margin: 12px 0 8px;
            font-size: 34px;
            line-height: 1.25;
            letter-spacing: -0.02em;
        }

        .lead {
            margin: 0;
            color: #344054;
            font-size: 14px;
            line-height: 1.8;
        }

        .client-lines {
            margin-top: 24px;
            display: grid;
            gap: 6px;
            color: #344054;
            font-size: 13px;
        }

        .target-box {
            margin-top: 28px;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--soft);
        }

        .target-label {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .target-url {
            font-size: 16px;
            font-weight: 700;
            word-break: break-all;
        }

        .target-meta {
            margin-top: 10px;
            display: grid;
            gap: 4px;
            font-size: 12px;
            color: #475467;
        }

        .summary-grid {
            margin-top: 24px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .summary-card {
            padding: 16px;
            border: 1px solid var(--soft-line);
            border-radius: 16px;
            background: #ffffff;
        }

        .summary-label {
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .summary-value {
            font-size: 18px;
            font-weight: 800;
            color: var(--ink);
            line-height: 1.4;
        }

        .message {
            margin-top: 18px;
            padding: 14px 16px;
            border-left: 4px solid var(--ink);
            background: var(--soft);
            border-radius: 14px;
            font-weight: 700;
            color: #344054;
            font-size: 12.5px;
            line-height: 1.7;
        }

        .executive-view,
        .coverage-map,
        .closing-note,
        .connection-note,
        .section-note,
        .reading-guide,
        .keyword-candidates,
        .next-step-flow {
            border: 1px solid var(--line);
            border-radius: 18px;
            background: #ffffff;
        }

        .executive-view {
            margin-top: 28px;
            padding: 22px;
        }

        .executive-view-title,
        .coverage-map-title,
        .closing-note-title,
        .connection-note-title,
        .section-note-title,
        .reading-guide-title,
        .keyword-candidates-title,
        .next-step-flow-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--ink);
            margin: 0;
            line-height: 1.4;
        }

        .executive-view-subtitle,
        .coverage-map-subtitle,
        .closing-note-subtitle,
        .connection-note-subtitle,
        .section-note-subtitle,
        .reading-guide-subtitle,
        .keyword-candidates-subtitle,
        .next-step-flow-subtitle {
            margin-top: 5px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.6;
        }

        .executive-view-text,
        .closing-note-text,
        .connection-note-text,
        .section-note-text,
        .reading-guide-text {
            margin-top: 14px;
            font-size: 13px;
            line-height: 1.85;
            color: #344054;
        }

        .executive-view-cards {
            display: grid;
            gap: 12px;
            margin-top: 18px;
        }

        .executive-view-card {
            display: grid;
            grid-template-columns: 170px 1fr;
            gap: 16px;
            align-items: start;
            padding: 14px 16px;
            border: 1px solid var(--soft-line);
            border-radius: 14px;
            background: var(--soft);
        }

        .executive-view-card-label {
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 6px;
            font-weight: 800;
        }

        .executive-view-card-value {
            font-size: 14px;
            font-weight: 800;
            color: var(--ink);
            line-height: 1.5;
        }

        .executive-view-card-text {
            font-size: 12.5px;
            line-height: 1.75;
            color: #344054;
            font-weight: 600;
        }

        .coverage-map {
            padding: 24px;
        }

        .coverage-panels {
            margin-top: 18px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            align-items: stretch;
        }

        .coverage-panel {
            padding: 16px;
            border: 1px solid var(--soft-line);
            border-radius: 16px;
            background: var(--soft);
        }

        .coverage-panel-title {
            margin: 0;
            font-size: 14px;
            font-weight: 800;
            color: var(--ink);
            line-height: 1.4;
        }

        .coverage-panel-subtitle {
            margin-top: 4px;
            font-size: 11px;
            color: var(--muted);
            line-height: 1.5;
        }

        .coverage-panel-radar {
            margin-top: 8px;
            display: grid;
            place-items: center;
        }

        .coverage-radar {
            width: 100%;
            max-width: 230px;
            height: auto;
            display: block;
        }

        .coverage-mini-list {
            margin-top: 8px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 10px;
        }

        .coverage-mini-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 6px;
            align-items: center;
            font-size: 10.5px;
            line-height: 1.35;
        }

        .coverage-mini-label {
            font-weight: 800;
            color: #344054;
        }

        .coverage-mini-status {
            color: var(--muted);
            white-space: nowrap;
        }

        .coverage-map-note {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid var(--soft-line);
            font-size: 11px;
            line-height: 1.7;
            color: var(--muted);
        }

        .section {
            margin-top: 0;
            padding-top: 0;
        }

        .section-title {
            margin: 0 0 20px;
            font-size: 24px;
            line-height: 1.35;
            letter-spacing: -0.02em;
        }

        .coverage-list-vertical {
            display: grid;
            gap: 10px;
        }

        .coverage-item-vertical {
            display: grid;
            grid-template-columns: 46px 155px 1fr;
            gap: 14px;
            align-items: center;
            padding: 13px 16px;
            border: 1px solid var(--soft-line);
            border-radius: 16px;
            background: var(--soft);
            min-height: 52px;
        }

        .coverage-number {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: var(--ink);
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
        }

        .coverage-name {
            font-size: 15px;
            font-weight: 800;
            color: var(--ink);
        }

        .coverage-desc {
            font-size: 12.5px;
            color: var(--muted);
            line-height: 1.5;
            font-weight: 700;
        }

        .section-note,
        .reading-guide {
            margin-top: 20px;
            padding: 18px;
            background: var(--soft);
            break-inside: avoid;
        }

        .detail-grid {
            display: grid;
            gap: 12px;
        }

        .detail-card {
            padding: 15px 16px;
            border: 1px solid var(--soft-line);
            border-radius: 16px;
            background: #ffffff;
            break-inside: avoid;
        }

        .detail-title {
            margin: 0 0 10px;
            font-size: 15px;
            font-weight: 800;
        }

        .detail-row {
            display: grid;
            grid-template-columns: 170px 1fr;
            gap: 14px;
            padding: 7px 0;
            border-top: 1px solid #f1f3f6;
            font-size: 12.5px;
            line-height: 1.5;
        }

        .detail-row:first-of-type {
            border-top: 0;
        }

        .detail-separator {
            height: 8px;
            border-top: 1px dashed #d0d5dd;
            margin: 8px 0;
        }

        .detail-label {
            color: var(--muted);
            font-weight: 700;
        }

        .detail-value {
            color: #263244;
            font-weight: 600;
            word-break: break-word;
        }

        .keyword-candidates {
            padding: 18px;
            background: #ffffff;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .keyword-list {
            margin: 14px 0 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 7px;
        }

        .keyword-item {
            display: grid;
            grid-template-columns: 34px 1fr;
            gap: 10px;
            align-items: start;
            padding: 7px 10px;
            border: 1px solid #f1f3f6;
            border-radius: 12px;
            background: var(--soft);
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .keyword-number {
            width: 24px;
            height: 24px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: var(--ink);
            color: #ffffff;
            font-size: 10px;
            font-weight: 800;
            line-height: 1;
        }

        .keyword-text {
            font-size: 12px;
            line-height: 1.55;
            color: #344054;
            font-weight: 700;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .keyword-more {
            margin-top: 10px;
            font-size: 11px;
            color: var(--muted);
            line-height: 1.6;
        }

        .connection-note {
            margin-top: 16px;
            padding: 16px;
            background: var(--soft);
            break-inside: avoid;
        }

        .closing-note {
            margin-top: 18px;
            padding: 18px;
            background: var(--soft);
            break-inside: avoid;
        }

        .comment {
            padding: 20px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: var(--soft);
            font-size: 13px;
            line-height: 1.85;
            color: #344054;
        }

        .next-step-flow {
            margin-top: 20px;
            padding: 20px;
            background: #ffffff;
        }

        .next-step-list {
            margin-top: 16px;
            display: grid;
            gap: 12px;
        }

        .next-step-item {
            display: grid;
            grid-template-columns: 42px 1fr;
            gap: 14px;
            align-items: start;
            padding: 14px 16px;
            border: 1px solid var(--soft-line);
            border-radius: 16px;
            background: var(--soft);
            break-inside: avoid;
        }

        .next-step-number {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: var(--ink);
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
        }

        .next-step-name {
            font-size: 14px;
            font-weight: 800;
            color: var(--ink);
            line-height: 1.4;
        }

        .next-step-desc {
            margin-top: 4px;
            font-size: 12.5px;
            line-height: 1.7;
            color: #344054;
            font-weight: 600;
        }

        .footer {
            margin-top: 26px;
            padding-top: 14px;
            border-top: 1px solid var(--soft-line);
            color: var(--muted);
            font-size: 11.5px;
            line-height: 1.65;
            break-inside: avoid;
        }

        .page-break-before {
            page-break-before: always;
            break-before: page;
        }

        .avoid-break {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        @media print {
            @page {
                margin: 12mm;
            }

            body {
                background: #ffffff;
                font-size: 12.5px;
                line-height: 1.55;
            }

            .page {
                max-width: none;
                margin: 0;
                padding: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .report-title {
                font-size: 30px;
            }

            .lead {
                font-size: 12.8px;
                line-height: 1.65;
            }

            .target-box {
                margin-top: 22px;
                padding: 15px;
            }

            .summary-grid {
                margin-top: 18px;
                gap: 9px;
            }

            .summary-card {
                padding: 12px;
            }

            .summary-label {
                font-size: 10.5px;
            }

            .summary-value {
                font-size: 15px;
            }

            .executive-view {
                margin-top: 22px;
                padding: 17px;
                break-inside: avoid;
            }

            .executive-view-text {
                font-size: 11.5px;
                line-height: 1.6;
            }

            .executive-view-cards {
                margin-top: 12px;
                gap: 9px;
            }

            .executive-view-card {
                grid-template-columns: 145px 1fr;
                gap: 12px;
                padding: 10px 12px;
            }

            .executive-view-card-value {
                font-size: 12.5px;
            }

            .executive-view-card-text {
                font-size: 11px;
                line-height: 1.55;
            }

            .coverage-map {
                padding: 18px;
                break-inside: avoid;
            }

            .coverage-map-title {
                font-size: 17px;
            }

            .coverage-map-subtitle {
                font-size: 11px;
                line-height: 1.45;
            }

            .coverage-panels {
                margin-top: 14px;
                gap: 12px;
            }

            .coverage-panel {
                padding: 12px;
            }

            .coverage-panel-title {
                font-size: 12.5px;
            }

            .coverage-panel-subtitle {
                font-size: 10px;
            }

            .coverage-radar {
                max-width: 190px;
            }

            .coverage-mini-list {
                grid-template-columns: 1fr 1fr;
                gap: 3px 8px;
            }

            .coverage-mini-row {
                font-size: 9.5px;
            }

            .coverage-map-note {
                margin-top: 12px;
                padding-top: 10px;
                font-size: 10.5px;
                line-height: 1.55;
            }

            .message {
                margin-top: 12px;
                padding: 11px 13px;
                font-size: 11px;
                line-height: 1.55;
            }

            .section-title {
                margin-bottom: 16px;
                font-size: 22px;
            }

            .coverage-list-vertical {
                gap: 8px;
            }

            .coverage-item-vertical {
                grid-template-columns: 42px 145px 1fr;
                gap: 10px;
                padding: 9px 13px;
                min-height: 45px;
            }

            .coverage-number {
                width: 30px;
                height: 30px;
                font-size: 10.5px;
            }

            .coverage-name {
                font-size: 13px;
            }

            .coverage-desc {
                font-size: 11.2px;
                line-height: 1.35;
            }

            .section-note,
            .reading-guide {
                margin-top: 16px;
                padding: 13px 14px;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .section-note-title,
            .reading-guide-title,
            .connection-note-title,
            .closing-note-title,
            .keyword-candidates-title,
            .next-step-flow-title {
                font-size: 15px;
            }

            .section-note-text,
            .reading-guide-text,
            .connection-note-text,
            .closing-note-text {
                margin-top: 8px;
                font-size: 11.2px;
                line-height: 1.5;
            }

            .detail-grid {
                gap: 9px;
            }

            .detail-card {
                padding: 11px 13px;
            }

            .detail-title {
                margin-bottom: 7px;
                font-size: 13px;
            }

            .detail-row {
                grid-template-columns: 145px 1fr;
                gap: 10px;
                padding: 4.8px 0;
                font-size: 11px;
                line-height: 1.35;
            }

            .keyword-candidates {
                padding: 14px;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .keyword-list {
                gap: 5px;
                margin-top: 10px;
            }

            .keyword-item {
                padding: 5px 8px;
            }

            .keyword-number {
                width: 22px;
                height: 22px;
                font-size: 9.5px;
            }

            .keyword-text {
                font-size: 10.8px;
                line-height: 1.4;
            }

            .connection-note,
            .closing-note {
                margin-top: 12px;
                padding: 13px 14px;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .comment {
                padding: 16px;
                font-size: 11.8px;
                line-height: 1.65;
            }

            .next-step-flow {
                margin-top: 18px;
                padding: 17px;
                break-inside: avoid;
            }

            .next-step-list {
                gap: 9px;
                margin-top: 12px;
            }

            .next-step-item {
                grid-template-columns: 38px 1fr;
                gap: 11px;
                padding: 10px 12px;
            }

            .next-step-number {
                width: 30px;
                height: 30px;
                font-size: 10.5px;
            }

            .next-step-name {
                font-size: 12.8px;
            }

            .next-step-desc {
                font-size: 11.2px;
                line-height: 1.5;
            }

            .footer {
                margin-top: 18px;
                padding-top: 12px;
                font-size: 10.5px;
                line-height: 1.5;
            }

            .page-break-before {
                page-break-before: always;
                break-before: page;
            }

            .avoid-break {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }

                /*
         * Final Micro Adjustments
         * SWCS Client Report
         */

        .coverage-panel {
            min-height: 0;
        }

        .coverage-panel-radar {
            margin-top: 6px;
        }

        .coverage-mini-list {
            margin-top: 6px;
            gap: 3px 8px;
        }

        .coverage-mini-row {
            grid-template-columns: 1fr auto;
            line-height: 1.25;
        }

        .keyword-item {
            grid-template-columns: 42px 1fr;
            align-items: center;
        }

        .keyword-number {
            justify-self: center;
        }

        .keyword-text {
            align-self: center;
        }

        .connection-note {
            margin-top: 14px;
        }

        .next-step-item {
            grid-template-columns: 46px 1fr;
            align-items: center;
        }

        .next-step-number {
            justify-self: center;
        }

        .next-step-name,
        .next-step-desc {
            word-break: keep-all;
            overflow-wrap: anywhere;
        }

        @media print {
            .coverage-panels {
                gap: 10px;
            }

            .coverage-panel {
                padding: 10px;
            }

            .coverage-panel-radar {
                margin-top: 4px;
            }

            .coverage-radar {
                max-width: 176px;
            }

            .coverage-mini-list {
                margin-top: 4px;
                gap: 2px 7px;
            }

            .coverage-mini-row {
                font-size: 9.2px;
                line-height: 1.22;
            }

            .coverage-map-note {
                margin-top: 10px;
                padding-top: 8px;
                font-size: 10px;
                line-height: 1.45;
            }

            .message {
                margin-top: 10px;
                padding: 9px 12px;
                font-size: 10.5px;
                line-height: 1.45;
            }

            .keyword-item {
                grid-template-columns: 36px 1fr;
                align-items: center;
                padding: 5px 8px;
            }

            .keyword-number {
                justify-self: center;
                width: 22px;
                height: 22px;
            }

            .keyword-text {
                align-self: center;
                line-height: 1.35;
            }

            .connection-note {
                margin-top: 10px;
                padding: 12px 13px;
            }

            .next-step-item {
                grid-template-columns: 40px 1fr;
                align-items: center;
                padding: 10px 12px;
            }

            .next-step-number {
                justify-self: center;
                width: 30px;
                height: 30px;
            }

            .next-step-name {
                line-height: 1.35;
            }

            .next-step-desc {
                margin-top: 3px;
                line-height: 1.45;
            }
        }
    </style>
</head>

<body>
    <main class="page">
        <section class="cover-section avoid-break">
            <div class="top-label">Studio-crs / <?= h($systemName) ?></div>

            <h1 class="report-title"><?= h($reportTitle) ?></h1>

            <p class="lead">
                <?= h($companyName) ?> が、SADS（AI診断）へ進む前に対象Webサイトの取得状態・構造・情報量・導線を確認するレポートです。
            </p>

            <div class="client-lines">
                <div>Client：<strong><?= h($clientName) ?></strong></div>
                <div>Studio-crs 担当：<strong><?= h($personInCharge) ?></strong></div>
            </div>

            <div class="target-box">
                <div class="target-label">診断対象</div>
                <div class="target-url"><?= h($targetUrl) ?></div>
                <div class="target-meta">
                    <div>Domain：<?= h($domain) ?></div>
                    <div>Web確認日時：<?= h($checkedAt) ?></div>
                    <div>Report生成日時：<?= h($reportGeneratedAt) ?></div>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-label">アクセス確認</div>
                    <div class="summary-value"><?= $isAccessOk ? '確認済み' : '未確認' ?></div>
                </div>

                <div class="summary-card">
                    <div class="summary-label">応答コード</div>
                    <div class="summary-value"><?= h($statusCode !== null ? $statusCode : '-') ?></div>
                </div>

                <div class="summary-card">
                    <div class="summary-label">取得本文量</div>
                    <div class="summary-value"><?= h($textLength !== null ? $textLength : '-') ?></div>
                </div>

                <div class="summary-card">
                    <div class="summary-label">構造確認</div>
                    <div class="summary-value"><?= count($structure) > 0 ? '確認済み' : '未取得' ?></div>
                </div>
            </div>

            <section class="executive-view">
                <div class="executive-view-header">
                    <h2 class="executive-view-title">Executive View</h2>
                    <div class="executive-view-subtitle">初回確認サマリー</div>
                </div>

                <div class="executive-view-text">
                    <?= h($companyName) ?>によるSWCS確認では、対象Webサイトへの接続、基本情報、構造、本文量、リンク、画像、情報接続、導線、取得性能の確認が完了しています。
                    本レポートは評価・診断ではなく、次工程のSADS（AI診断）へ進むための現状可視化レポートです。
                </div>

                <div class="executive-view-cards">
                    <div class="executive-view-card">
                        <div>
                            <div class="executive-view-card-label">Current Status</div>
                            <div class="executive-view-card-value">接続・取得確認済み</div>
                        </div>
                        <div class="executive-view-card-text">
                            対象Webサイトへの接続、ページ情報、本文量、リンク、画像、導線など、SADSへ進む前に必要な基本情報をSWCSで取得・確認しています。
                        </div>
                    </div>

                    <div class="executive-view-card">
                        <div>
                            <div class="executive-view-card-label">Report Role</div>
                            <div class="executive-view-card-value">評価ではなく現状可視化</div>
                        </div>
                        <div class="executive-view-card-text">
                            本レポートは、Webサイトの良し悪しを判定するものではありません。現時点で取得できた情報を整理し、次工程のAI診断に渡すための可視化レポートです。
                        </div>
                    </div>

                    <div class="executive-view-card">
                        <div>
                            <div class="executive-view-card-label">Next Step</div>
                            <div class="executive-view-card-value">SADS AI診断へ進行可能</div>
                        </div>
                        <div class="executive-view-card-text">
                            SWCSで取得した情報をもとに、次工程のSADSでAI診断・スコアリング・改善方向の整理へ進みます。
                        </div>
                    </div>
                </div>
            </section>
        </section>

        <section class="coverage-map page-break-before">
            <div class="coverage-map-header">
                <h2 class="coverage-map-title">SWCS Check Coverage Map</h2>
                <div class="coverage-map-subtitle">
                    Web確認領域マップ。これは評価・診断ではなく、SWCSが対象Webサイトについて確認できた領域を視覚化したものです。
                </div>
            </div>

            <div class="coverage-panels">
                <?php
                    renderCoveragePanel(
                        'Current Site Coverage',
                        '対象Webサイトの確認領域',
                        $coverageMapItems,
                        true
                    );
                ?>

                <?php
                    renderCoveragePanel(
                        'SWCS Standard Coverage Model',
                        '一般的なWeb確認モデル',
                        $standardCoverageMapItems,
                        true
                    );
                ?>
            </div>

            <div class="coverage-map-note">
                ※ この図は点数・評価・診断結果ではありません。SWCSが確認する領域を視覚化したものであり、AI診断・スコアリングは次工程のSADSで行います。
            </div>

            <div class="message">
                SWCSでWeb情報の取得と基本確認ができています。次のSADS（AI診断）へ進める状態です。
            </div>
        </section>

        <section class="section page-break-before">
            <h2 class="section-title">01. Check Coverage</h2>

            <div class="coverage-list-vertical">
                <?php foreach ($checkCoverageItems as $item): ?>
                    <div class="coverage-item-vertical">
                        <div class="coverage-number"><?= h($item['number']) ?></div>
                        <div class="coverage-name"><?= h($item['name']) ?></div>
                        <div class="coverage-desc"><?= h($item['desc']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <section class="section-note">
                <h3 class="section-note-title">Section Note</h3>
                <div class="section-note-subtitle">確認領域の説明</div>
                <div class="section-note-text">
                    SWCSでは、対象Webサイトを10の確認領域に分けて整理します。
                    この一覧は、SADS（AI診断）へ進む前に確認する範囲を示したものです。
                </div>
            </section>
        </section>

        <section class="section page-break-before">
            <h2 class="section-title">02. Detailed Findings</h2>

            <section class="reading-guide">
                <h3 class="reading-guide-title">Reading Guide</h3>
                <div class="reading-guide-subtitle">詳細確認の見方</div>
                <div class="reading-guide-text">
                    Detailed Findingsでは、SWCSが取得できたWebサイト情報を領域ごとに整理しています。
                    各項目は、現時点で確認できた取得状態を示すものであり、良し悪しの判定ではありません。
                    評価、スコアリング、改善方向の提示は、次工程のSADSで行います。
                </div>
            </section>

            <div class="detail-grid">
                <?php foreach ($detailSections as $detailSection): ?>
                    <div class="detail-card">
                        <h3 class="detail-title"><?= h($detailSection['title']) ?></h3>
                        <?php renderRows($detailSection['rows']); ?>
                    </div>

                    <?php if ($detailSection['title'] === 'Content / コンテンツ確認'): ?>
                        <?php renderKeywordCandidates($keywordCandidates, 10); ?>
                    <?php endif; ?>

                    <?php if ($detailSection['title'] === 'Links / リンク確認'): ?>
                        <?php renderLinkListCard('External Links / 外部リンク確認', $externalLinks, 10); ?>
                        <?php renderLinkListCard('Social Links / SNSリンク確認', $socialLinks, 10); ?>
                        <?php renderEmailLinks($emailLinks, 10); ?>
                    <?php endif; ?>

                    <?php if ($detailSection['title'] === 'Relationship / 情報接続'): ?>
                        <section class="connection-note">
                            <h2 class="connection-note-title">Connection to Flow</h2>
                            <div class="connection-note-subtitle">導線確認への接続</div>

                            <div class="connection-note-text">
                                リンク、画像、情報接続の確認後、次ページでは、ユーザーがWebサイト内でどのように移動し、問い合わせや行動へ進める状態にあるかを確認します。<br>
                                SWCSでは、この流れを現状可視化として整理し、次工程のSADS（AI診断）へつなげます。
                            </div>
                        </section>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <section class="closing-note">
                <h2 class="closing-note-title">Detailed Findings Closing Note</h2>
                <div class="closing-note-subtitle">確認項目のまとめ</div>

                <div class="closing-note-text">
                    ここまでのDetailed Findingsでは、SWCSが対象Webサイトから取得できた接続情報、基本情報、構造、本文量、リンク、画像、情報接続、導線、取得性能、確認結果を整理しています。<br>
                    本ページまでの内容は、次工程であるSADS（AI診断）へ進むための確認材料として使用します。
                </div>
            </section>
        </section>

        <section class="section page-break-before">
            <h2 class="section-title">03. Studio-crs Comment</h2>

            <div class="comment">
                <?= h($companyName) ?>では、<?= h($clientName) ?>のWebサイトについて、接続状態、基本情報、構造、本文量、リンク、画像、情報接続、導線、取得性能、確認結果を確認しました。
                この確認結果をもとに、次のSADSでAI診断・スコアリングへ進みます。
            </div>

            <section class="next-step-flow">
                <h3 class="next-step-flow-title">Next Step Flow</h3>
                <div class="next-step-flow-subtitle">次工程の流れ</div>

                <div class="next-step-list">
                    <div class="next-step-item">
                        <div class="next-step-number">01</div>
                        <div>
                            <div class="next-step-name">SWCS Web Check</div>
                            <div class="next-step-desc">
                                対象Webサイトの取得状態、構造、情報量、導線を確認し、SADS（AI診断）へ進む前の現状を可視化します。
                            </div>
                        </div>
                    </div>

                    <div class="next-step-item">
                        <div class="next-step-number">02</div>
                        <div>
                            <div class="next-step-name">Client Confirmation</div>
                            <div class="next-step-desc">
                                本レポートをもとにクライアントと確認を行い、現状認識の共有と次工程への進行確認を行います。
                            </div>
                        </div>
                    </div>

                    <div class="next-step-item">
                        <div class="next-step-number">03</div>
                        <div>
                            <div class="next-step-name">SADS AI Diagnosis</div>
                            <div class="next-step-desc">
                                SWCSで取得した情報をもとに、AI診断・スコアリング・改善方向の整理へ進みます。
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <footer class="footer">
                <?= h($companyName) ?> / 担当：<?= h($personInCharge) ?><br>
                Client：<?= h($clientName) ?><br>
                Web確認日時：<?= h($checkedAt) ?> / Report生成日時：<?= h($reportGeneratedAt) ?><br>
                <?= h($copyright) ?>
            </footer>
        </section>
    </main>
</body>
</html>