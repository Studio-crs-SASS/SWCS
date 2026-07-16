<?php

namespace Engine\Processing;

class Normalizer
{
    public function normalize(array $structure): array
    {
        return [
            'page' => [
                'title' => trim($structure['page']['title'] ?? ''),
                'h1' => $structure['page']['headings']['h1'] ?? [],
                'links' => array_values(array_unique($structure['page']['links'] ?? []))
            ],
            'meta' => [
                'normalized_at' => date('c')
            ]
        ];
    }
}