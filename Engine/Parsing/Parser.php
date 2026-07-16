<?php

namespace Engine\Parsing;

class Parser
{
    public function parse(string $html): array
    {
        if (empty($html)) {
            return [
                'success' => false,
                'error' => 'Empty HTML'
            ];
        }

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $dom->loadHTML($html);

        $title = '';
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            $title = $titles->item(0)->textContent;
        }

        $h1_list = [];
        $h1_tags = $dom->getElementsByTagName('h1');
        foreach ($h1_tags as $h1) {
            $h1_list[] = $h1->textContent;
        }

        return [
            'success' => true,
            'title' => $title,
            'h1' => $h1_list
        ];
    }
}