<?php

namespace Engine\Input;

class InputGateway
{
    public function handle(array $request): array
    {
        $input = $request['input'] ?? [];

        return [
            'target_url' => $input['url'] ?? null,
            'valid' => filter_var($input['url'] ?? '', FILTER_VALIDATE_URL) !== false
        ];
    }
}