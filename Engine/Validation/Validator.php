<?php

namespace Engine\Validation;

class Validator
{
    public function validate(array $normalized): array
    {
        $issues = [];

        // titleチェック
        if (empty($normalized['page']['title'])) {
            $issues[] = 'Missing title';
        }

        // h1チェック
        if (empty($normalized['page']['h1'])) {
            $issues[] = 'Missing H1';
        }

        // linkチェック
        if (empty($normalized['page']['links'])) {
            $issues[] = 'No links found';
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }
}