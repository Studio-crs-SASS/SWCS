<?php

namespace Engine\Collection;

class Collector
{
    public function fetch(?string $url): array
    {
        if (!$url) {
            return [
                'success' => false,
                'html' => null,
                'error' => 'No URL provided'
            ];
        }

        $html = @file_get_contents($url);

        if ($html === false) {
            return [
                'success' => false,
                'html' => null,
                'error' => 'Failed to fetch content'
            ];
        }

        return [
    'success' => true,
    'length' => strlen($html),

    // 処理用（絶対にそのまま）
    'html' => $html,

    // 表示用（エスケープ）
    'html_preview' => htmlspecialchars(substr($html, 0, 500))
];
    }
}