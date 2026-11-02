<?php

declare(strict_types=1);

namespace App\Support\Blog;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * HTML uit het CMS veilig maken voor de pagina: alleen opmaak die een
 * blogbericht nodig heeft, geen scripts, stijlen of event-attributen, en
 * links alleen naar http(s), mailto of een pad. Ook een vertrouwde bron
 * kan een gekaapt account hebben; dit is de laatste poort.
 */
final class SafeHtml
{
    /** @var array<string, list<string>> Toegestane tags met hun toegestane attributen. */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 'span' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'code' => [], 'pre' => [],
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'width', 'height'],
        'figure' => [], 'figcaption' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    ];

    /** @var list<string> Weg met inhoud en al. */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'noscript', 'template'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('root');

        if ($root === null) {
            return '';
        }

        self::walk($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }

            self::walk($child);

            if (! array_key_exists($tag, self::ALLOWED)) {
                // Onbekende tag: de inhoud blijft, de tag gaat.
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attribute) {
                $name = strtolower($attribute->name);

                if (! in_array($name, self::ALLOWED[$tag], true)) {
                    $child->removeAttribute($attribute->name);

                    continue;
                }

                if (in_array($name, ['href', 'src'], true) && ! self::safeUrl($attribute->value, $name === 'href')) {
                    $child->removeAttribute($attribute->name);
                }
            }

            if ($tag === 'a' && preg_match('#^https?://#i', $child->getAttribute('href')) === 1) {
                $child->setAttribute('rel', 'noopener nofollow');
            }

            if ($tag === 'img') {
                $child->setAttribute('loading', 'lazy');
            }
        }
    }

    private static function safeUrl(string $url, bool $allowMail): bool
    {
        $url = trim($url);

        if (preg_match('#^https?://#i', $url) === 1 || (str_starts_with($url, '/') && ! str_starts_with($url, '//'))) {
            return true;
        }

        return $allowMail && preg_match('#^mailto:#i', $url) === 1;
    }
}
