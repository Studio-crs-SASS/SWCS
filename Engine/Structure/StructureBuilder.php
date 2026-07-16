<?php

namespace Engine\Structure;

class StructureBuilder
{
    public function build(array $parsed, string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($html);

        // リンク抽出
        $links = [];
        $a_tags = $dom->getElementsByTagName('a');

        foreach ($a_tags as $a) {
            $href = $a->getAttribute('href');
            if ($href) {
                $links[] = $href;
            }
        }

        return [
            'page' => [
                'title' => $parsed['title'] ?? '',
                'headings' => [
                    'h1' => $parsed['h1'] ?? []
                ],
                'links' => $links
            ]
        ];
    }
}