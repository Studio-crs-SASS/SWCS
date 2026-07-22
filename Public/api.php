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

$output = [
    'status' => $result['status'] ?? 'success',
    'system' => 'SWCS',
    'version' => $config['app']['version'] ?? '1.0',
    'target' => [
        'url' => $url,
        'domain' => parse_url($url, PHP_URL_HOST),
        'checked_at' => date(DATE_ATOM),
    ],
    'data' => [
        'access' => $result['access'] ?? [],
        'meta' => $parsed['meta'] ?? $normalized['meta'] ?? [],
        'structure' => $structure,
        'content' => [
            'success' => $collection['success'] ?? false,
            'length' => $collection['length'] ?? 0,
            'html_preview' => $collection['html_preview'] ?? null,
            'parsed' => $parsed,
        ],
        'links' => $structure['links'] ?? $normalized['links'] ?? [],
        'media' => $structure['media'] ?? $normalized['media'] ?? [],
        'performance' => [
            'html_length' => $collection['length'] ?? 0,
            'execution_time' => round(microtime(true) - SWCS_START_TIME, 5),
        ],
        'validation' => $validation,
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