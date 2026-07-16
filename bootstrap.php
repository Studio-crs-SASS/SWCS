<?php

/**
 * SWCS Bootstrap
 * Syu Web Check System
 */

declare(strict_types=1);

// --------------------------------------------------
// 01 Environment
// --------------------------------------------------

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('SWCS_ROOT', __DIR__);
define('SWCS_START_TIME', microtime(true));

// --------------------------------------------------
// 02 Autoload（シンプル版）
// --------------------------------------------------

spl_autoload_register(function ($class) {
    $path = SWCS_ROOT . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

// --------------------------------------------------
// 03 Config Loader
// --------------------------------------------------

$config = [];

$configPath = SWCS_ROOT . '/Config';

if (is_dir($configPath)) {
    foreach (glob($configPath . '/*.php') as $file) {
        $key = basename($file, '.php');
        $config[$key] = require $file;
    }
}

// --------------------------------------------------
// 04 Request Handling
// --------------------------------------------------

$request = [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
    'uri'    => $_SERVER['REQUEST_URI'] ?? '',
    'input'  => $_REQUEST ?? [],
];

// --------------------------------------------------
// 05 Router（最小）
// --------------------------------------------------

$path = parse_url($request['uri'], PHP_URL_PATH);

// API Entry
if (str_starts_with($path, '/api')) {
    require SWCS_ROOT . '/Public/api.php';
    exit;
}
use Engine\Core\Engine;

// Engine起動
$engine = new Engine($config);
$response = $engine->run($request);


// Default Entry
require SWCS_ROOT . '/Public/index.php';