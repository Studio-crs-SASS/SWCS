<?php

namespace Engine\Access;

class AccessChecker
{
    public function check(?string $url): array
    {
        if (!$url) {
            return [
                'accessible' => false,
                'status_code' => null,
                'error' => 'No URL provided'
            ];
        }

        $headers = @get_headers($url);

        if (!$headers) {
            return [
                'accessible' => false,
                'status_code' => null,
                'error' => 'Unable to connect'
            ];
        }

        preg_match('/\d{3}/', $headers[0], $matches);

        return [
            'accessible' => true,
            'status_code' => $matches[0] ?? null,
            'headers' => $headers
        ];
    }
}