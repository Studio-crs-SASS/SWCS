<?php

declare(strict_types=1);

namespace Engine\Crawl;

class UrlNormalizer
{
    protected array $config;

    protected string $baseUrl;

    protected string $baseDomain;

    public function __construct(array $config, string $baseUrl)
    {
        $this->config = $config;
        $this->baseUrl = $baseUrl;
        $this->baseDomain = $this->normalizeDomain((string) parse_url($baseUrl, PHP_URL_HOST));
    }

    public function normalize(string $url, ?string $currentUrl = null): ?array
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if ($this->isNonCrawlScheme($url)) {
            return $this->normalizeSpecialScheme($url);
        }

        $absoluteUrl = $this->toAbsoluteUrl($url, $currentUrl ?? $this->baseUrl);

        if ($absoluteUrl === null) {
            return null;
        }

        $parts = parse_url($absoluteUrl);

        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $this->normalizeDomain($parts['host']);
        $path = $this->normalizePath($parts['path'] ?? '/');
        $query = $this->normalizeQuery($parts['query'] ?? '');

        $normalizedUrl = $scheme . '://' . $host . $path;

        if ($query !== '') {
            $normalizedUrl .= '?' . $query;
        }

        $extension = $this->detectExtension($path);

        return [
            'original_url' => $url,
            'normalized_url' => $normalizedUrl,
            'scheme' => $scheme,
            'host' => $host,
            'path' => $path,
            'query' => $query,
            'extension' => $extension,
            'is_same_domain' => $this->isSameDomain($host),
            'is_file' => $this->isFileExtension($extension),
            'is_excluded' => $this->isFileExtension($extension) || $this->isExcludedPath($path) || $this->isExcludedQuery($query),
            'exclude_reason' => $this->getExcludeReason($path, $query, $extension),
        ];
    }

    public function isSameDomain(string $host): bool
    {
        return $this->normalizeDomain($host) === $this->baseDomain;
    }

    protected function toAbsoluteUrl(string $url, string $currentUrl): ?string
    {
        if (preg_match('#^https?://#i', $url) === 1) {
            return strtok($url, '#') ?: $url;
        }

        if (str_starts_with($url, '//')) {
            $scheme = parse_url($currentUrl, PHP_URL_SCHEME) ?: 'https';
            return $scheme . ':' . strtok($url, '#');
        }

        $currentParts = parse_url($currentUrl);

        if (!is_array($currentParts) || empty($currentParts['host'])) {
            return null;
        }

        $scheme = $currentParts['scheme'] ?? 'https';
        $host = $currentParts['host'];
        $basePath = $currentParts['path'] ?? '/';

        if (str_starts_with($url, '/')) {
            return $scheme . '://' . $host . strtok($url, '#');
        }

        $directory = rtrim(dirname($basePath), '/');

        if ($directory === '' || $directory === '.') {
            $directory = '';
        }

        return $scheme . '://' . $host . $directory . '/' . strtok($url, '#');
    }

    protected function normalizeDomain(string $host): string
    {
        $host = strtolower(trim($host));

        if (($this->config['scope']['treat_www_as_same_domain'] ?? true) === true) {
            $host = preg_replace('/^www\./', '', $host);
        }

        return $host;
    }

    protected function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    protected function normalizeQuery(string $query): string
    {
        if (($this->config['scope']['include_query_strings'] ?? false) === false) {
            return '';
        }

        if ($query === '') {
            return '';
        }

        parse_str($query, $params);

        foreach (($this->config['query']['remove_parameters'] ?? []) as $removeKey) {
            unset($params[$removeKey]);
        }

        return http_build_query($params);
    }

    protected function isNonCrawlScheme(string $url): bool
    {
        return str_starts_with(strtolower($url), 'mailto:')
            || str_starts_with(strtolower($url), 'tel:')
            || str_starts_with(strtolower($url), 'javascript:');
    }

    protected function normalizeSpecialScheme(string $url): ?array
    {
        $lower = strtolower($url);

        if (str_starts_with($lower, 'mailto:')) {
            return [
                'original_url' => $url,
                'normalized_url' => $url,
                'scheme' => 'mailto',
                'host' => '',
                'path' => $url,
                'query' => '',
                'extension' => '',
                'is_same_domain' => false,
                'is_file' => false,
                'is_excluded' => false,
                'exclude_reason' => '',
            ];
        }

        if (str_starts_with($lower, 'tel:')) {
            return [
                'original_url' => $url,
                'normalized_url' => $url,
                'scheme' => 'tel',
                'host' => '',
                'path' => $url,
                'query' => '',
                'extension' => '',
                'is_same_domain' => false,
                'is_file' => false,
                'is_excluded' => false,
                'exclude_reason' => '',
            ];
        }

        return null;
    }

    protected function detectExtension(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return strtolower($extension);
    }

    protected function isFileExtension(string $extension): bool
    {
        if ($extension === '') {
            return false;
        }

        return in_array($extension, $this->config['exclude']['extensions'] ?? [], true);
    }

    protected function isExcludedPath(string $path): bool
    {
        foreach (($this->config['exclude']['paths'] ?? []) as $excludedPath) {
            if (str_starts_with($path, $excludedPath)) {
                return true;
            }
        }

        return false;
    }

    protected function isExcludedQuery(string $query): bool
    {
        if ($query === '') {
            return false;
        }

        parse_str($query, $params);

        foreach (($this->config['query']['exclude_patterns'] ?? []) as $pattern) {
            if (array_key_exists($pattern, $params)) {
                return true;
            }
        }

        return false;
    }

    protected function getExcludeReason(string $path, string $query, string $extension): string
    {
        if ($this->isFileExtension($extension)) {
            return 'file_extension';
        }

        if ($this->isExcludedPath($path)) {
            return 'excluded_path';
        }

        if ($this->isExcludedQuery($query)) {
            return 'excluded_query';
        }

        return '';
    }
}
