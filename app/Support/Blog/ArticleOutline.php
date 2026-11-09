<?php

declare(strict_types=1);

namespace App\Support\Blog;

/**
 * "In dit artikel": de tussenkoppen van een blogbericht als inhoudsopgave.
 *
 * De HTML komt al schoon uit SafeHtml. Vóór elke h2 komt een leeg anker te
 * staan; de kop zelf blijft onaangeroerd.
 */
final class ArticleOutline
{
    /**
     * @return array{html: string, items: list<array{id: string, title: string}>}
     */
    public static function of(?string $html): array
    {
        $items = [];

        $html = (string) preg_replace_callback(
            '/<h2\b[^>]*>(.*?)<\/h2>/is',
            function (array $match) use (&$items): string {
                $title = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5));

                if ($title === '') {
                    return $match[0];
                }

                $id = 'deel-'.(count($items) + 1);
                $items[] = ['id' => $id, 'title' => $title];

                return '<span class="d-anchor" id="'.$id.'"></span>'.$match[0];
            },
            (string) $html,
        );

        return ['html' => $html, 'items' => $items];
    }
}
