<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

class PmsProjectDescription
{
    public static function render(?string $source): string
    {
        return Str::markdown(self::canonicalize($source), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    public static function canonicalize(?string $source): string
    {
        $source = str_replace(["\r\n", "\r"], "\n", trim((string) $source));
        if ($source === '' || ! preg_match('/<\s*(?:h[1-6]|p|div|ul|ol|li|br|blockquote|pre|table)\b/i', $source)) {
            return $source;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8"?><div id="pms-description-root">'.$source.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $document->getElementById('pms-description-root') ?: $document->documentElement;
        $markdown = $root ? self::children($root) : $source;

        return preg_replace("/\n{3,}/", "\n\n", trim($markdown)) ?: '';
    }

    private static function children(DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= self::node($child);
        }
        return $text;
    }

    private static function node(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return preg_replace('/\s+/u', ' ', $node->nodeValue ?? '') ?: '';
        }
        if (! $node instanceof DOMElement) return '';
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'form', 'input'], true)) return '';
        $inside = self::children($node);

        return match ($tag) {
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => "\n\n".str_repeat('#', max(2, min(4, (int) substr($tag, 1)))).' '.trim($inside)."\n\n",
            'p', 'div', 'section', 'article' => trim($inside) === '' ? '' : "\n\n".trim($inside)."\n\n",
            'br' => "  \n",
            'strong', 'b' => '**'.trim($inside).'**',
            'em', 'i' => '*'.trim($inside).'*',
            'code' => '`'.str_replace('`', '\\`', trim($node->textContent)).'`',
            'pre' => "\n\n```\n".trim($node->textContent, "\n\r")."\n```\n\n",
            'blockquote' => "\n\n".preg_replace('/^/m', '> ', trim($inside))."\n\n",
            'ul', 'ol' => "\n\n".self::listing($node, 0)."\n\n",
            'li' => trim($inside),
            'a' => self::link($node, trim($inside)),
            'table' => "\n\n".self::table($node)."\n\n",
            default => $inside,
        };
    }

    private static function listing(DOMElement $list, int $depth): string
    {
        $rows = [];
        $number = (int) ($list->getAttribute('start') ?: 1);
        foreach ($list->childNodes as $child) {
            if (! $child instanceof DOMElement || strtolower($child->tagName) !== 'li') continue;
            $text = '';
            $nested = '';
            foreach ($child->childNodes as $part) {
                if ($part instanceof DOMElement && in_array(strtolower($part->tagName), ['ul', 'ol'], true)) {
                    $nested .= "\n".self::listing($part, $depth + 1);
                } else {
                    $text .= self::node($part);
                }
            }
            $bullet = strtolower($list->tagName) === 'ol' ? ($number++).'. ' : '- ';
            $rows[] = str_repeat('  ', $depth).$bullet.trim($text).$nested;
        }
        return implode("\n", $rows);
    }

    private static function link(DOMElement $node, string $text): string
    {
        $href = trim($node->getAttribute('href'));
        if ($href === '' || ! preg_match('~^(?:https?://|mailto:|/|#)~i', $href)) return $text;
        return '['.str_replace([']', '['], '', $text).']('.str_replace(['(', ')', ' '], ['%28', '%29', '%20'], $href).')';
    }

    private static function table(DOMElement $table): string
    {
        $rows = [];
        foreach ($table->getElementsByTagName('tr') as $row) {
            $cells = [];
            foreach ($row->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['th', 'td'], true)) {
                    $cells[] = str_replace('|', '\\|', trim(preg_replace('/\s+/u', ' ', $cell->textContent) ?: ''));
                }
            }
            if ($cells !== []) $rows[] = $cells;
        }
        if ($rows === []) return '';
        $width = max(array_map('count', $rows));
        $head = array_pad(array_shift($rows), $width, '');
        $lines = ['| '.implode(' | ', $head).' |', '| '.implode(' | ', array_fill(0, $width, '---')).' |'];
        foreach ($rows as $row) $lines[] = '| '.implode(' | ', array_pad($row, $width, '')).' |';
        return implode("\n", $lines);
    }
}
