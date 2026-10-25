<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

final class StorefrontHtml
{
    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'h4', 'h5', 'ul', 'ol', 'li', 'blockquote', 'a', 'img', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'pre', 'code', 'hr', 'div', 'span'];
    private const REMOVE = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math'];

    public static function clean(?string $html): string
    {
        if (!$html) {
            return '';
        }
        if (!class_exists(DOMDocument::class)) {
            return nl2br(e(strip_tags($html)));
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<meta charset="utf-8"><div id="storefront-content">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('storefront-content');
        if (!$root) {
            return nl2br(e(strip_tags($html)));
        }
        self::sanitizeChildren($root);
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }
        return $output;
    }

    private static function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (!$node instanceof DOMElement) {
                if ($node->nodeType === XML_COMMENT_NODE) {
                    $parent->removeChild($node);
                }
                continue;
            }
            $tag = strtolower($node->tagName);
            if (in_array($tag, self::REMOVE, true)) {
                $parent->removeChild($node);
                continue;
            }
            self::sanitizeChildren($node);
            if (!in_array($tag, self::TAGS, true)) {
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim($attribute->value);
                $allowed = ($tag === 'a' && $name === 'href' && self::safeUrl($value))
                    || ($tag === 'img' && $name === 'src' && self::safeUrl($value))
                    || ($tag === 'img' && $name === 'alt');
                if (!$allowed) {
                    $node->removeAttributeNode($attribute);
                }
            }
            if ($tag === 'a') {
                $node->setAttribute('rel', 'nofollow noopener noreferrer');
            }
        }
    }

    private static function safeUrl(string $url): bool
    {
        return $url !== '' && !preg_match('/[\x00-\x20]/', $url)
            && (str_starts_with($url, '/') || str_starts_with($url, '#')
                || preg_match('~^https?://~i', $url) || preg_match('~^mailto:~i', $url));
    }
}
