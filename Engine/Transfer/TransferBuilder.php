<?php

namespace Engine\Transfer;

class TransferBuilder
{
    public function build(array $normalized, array $validation): array
    {
        return [
            'status' => $validation['valid'] ? 'ready' : 'invalid',
            'data' => $normalized,
            'issues' => $validation['issues'] ?? [],
            'timestamp' => date('c')
        ];
    }
}