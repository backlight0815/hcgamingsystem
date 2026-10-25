<?php

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class XlsxRowsReader
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const PACKAGE_RELS_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const OFFICE_RELS_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    public function sheets(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to open the Excel workbook.');
        }

        try {
            $sharedStrings = $this->sharedStrings($zip);
            $sheetPaths = $this->sheetPaths($zip);
            $sheets = [];

            foreach ($sheetPaths as $sheetName => $sheetPath) {
                $sheetXml = $zip->getFromName($sheetPath);

                if ($sheetXml === false) {
                    continue;
                }

                $sheets[$sheetName] = $this->sheetRows($this->normalizeXml($sheetXml), $sharedStrings);
            }

            return $sheets;
        } finally {
            $zip->close();
        }
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $xml = $this->normalizeXml($xml);
        $strings = [];

        if (preg_match_all('/<si\b[^>]*>(.*?)<\/si>/s', $xml, $matches)) {
            foreach ($matches[1] as $sharedStringXml) {
                $strings[] = $this->textFromSharedStringXml($sharedStringXml);
            }
        }

        return $strings;
    }

    private function textFromSharedStringXml(string $xml): string
    {
        if (! preg_match_all('/<[^:>]*:?t\b[^>]*>(.*?)<\/[^:>]*:?t>/s', $xml, $matches)) {
            return '';
        }

        return collect($matches[1])
            ->map(fn (string $text): string => html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'))
            ->implode('');
    }

    private function sheetPaths(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            throw new RuntimeException('The Excel workbook structure is invalid.');
        }

        $workbook = simplexml_load_string($this->normalizeXml($workbookXml));
        $rels = simplexml_load_string($this->normalizeXml($relsXml));

        if (! $workbook instanceof SimpleXMLElement || ! $rels instanceof SimpleXMLElement) {
            throw new RuntimeException('The Excel workbook structure is invalid.');
        }

        $relationshipTargets = [];

        foreach ($rels->children(self::PACKAGE_RELS_NS)->Relationship as $relationship) {
            $attributes = $relationship->attributes();
            $relationshipTargets[(string) $attributes['Id']] = (string) $attributes['Target'];
        }

        $paths = [];
        $workbook->registerXPathNamespace('xlsx', self::MAIN_NS);

        foreach ($workbook->xpath('//xlsx:sheets/xlsx:sheet') ?: [] as $sheet) {
            $attributes = $sheet->attributes();
            $relationshipAttributes = $sheet->attributes(self::OFFICE_RELS_NS);
            $relationshipId = (string) ($relationshipAttributes['id'] ?? '');
            $target = $relationshipTargets[$relationshipId] ?? null;

            if (! $target) {
                continue;
            }

            $paths[(string) $attributes['name']] = $this->normalizeSheetPath($target);
        }

        return $paths;
    }

    private function normalizeSheetPath(string $target): string
    {
        $target = str_replace('\\', '/', $target);

        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        if (str_starts_with($target, 'xl/')) {
            return $target;
        }

        return 'xl/'.ltrim($target, '/');
    }

    private function normalizeXml(string $xml): string
    {
        if (str_starts_with($xml, "\xEF\xBB\xBF")) {
            return substr($xml, 3);
        }

        if (str_starts_with($xml, "\xFF\xFE")) {
            return $this->convertXmlEncoding(substr($xml, 2), 'UTF-16LE');
        }

        if (str_starts_with($xml, "\xFE\xFF")) {
            return $this->convertXmlEncoding(substr($xml, 2), 'UTF-16BE');
        }

        if (str_contains(substr($xml, 0, 200), "\0")) {
            return $this->convertXmlEncoding($xml, 'UTF-16');
        }

        return $xml;
    }

    private function convertXmlEncoding(string $xml, string $fromEncoding): string
    {
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($xml, 'UTF-8', $fromEncoding);
        }

        $converted = function_exists('iconv') ? @iconv($fromEncoding, 'UTF-8//IGNORE', $xml) : false;

        return $converted !== false ? $converted : str_replace("\0", '', $xml);
    }

    private function sheetRows(string $xml, array $sharedStrings): array
    {
        $rows = [];

        if (! preg_match_all('/<row\b[^>]*>(.*?)<\/row>/s', $xml, $matches)) {
            return [];
        }

        foreach ($matches[1] as $rowXml) {
            $row = $this->parseRowXml($rowXml, $sharedStrings);
            if ($row !== []) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function parseRowXml(string $xml, array $sharedStrings): array
    {
        $values = [];
        $maxIndex = -1;

        if (! preg_match_all('/<c\b([^>]*?)(?:\/>|>(.*?)<\/c>)/s', $xml, $cells, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($cells as $cell) {
            $attributes = $cell[1] ?? '';
            $body = $cell[2] ?? '';
            $cellRef = $this->cellAttribute($attributes, 'r');
            $index = $this->columnIndex((string) $cellRef);

            if ($index === null) {
                continue;
            }

            $values[$index] = $this->cellValue($attributes, $body, $sharedStrings);
            $maxIndex = max($maxIndex, $index);
        }

        if ($maxIndex < 0) {
            return [];
        }

        $rowValues = [];

        for ($index = 0; $index <= $maxIndex; $index++) {
            $rowValues[] = $values[$index] ?? null;
        }

        return $rowValues;
    }

    private function cellValue(string $attributes, string $body, array $sharedStrings)
    {
        $type = (string) ($this->cellAttribute($attributes, 't') ?? '');

        if (preg_match('/<v>(.*?)<\/v>/s', $body, $matches)) {
            $raw = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));

            return $type === 's' && is_numeric($raw)
                ? ($sharedStrings[(int) $raw] ?? $raw)
                : $raw;
        }

        if ($type === 'inlineStr' && preg_match_all('/<t\b[^>]*>(.*?)<\/t>/s', $body, $matches)) {
            return collect($matches[1])
                ->map(fn (string $text): string => html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'))
                ->implode('');
        }

        return null;
    }

    private function cellAttribute(string $attributes, string $name): ?string
    {
        return preg_match('/\b'.preg_quote($name, '/').'="([^"]*)"/', $attributes, $matches)
            ? html_entity_decode($matches[1], ENT_QUOTES | ENT_XML1, 'UTF-8')
            : null;
    }

    private function columnIndex(string $cellRef): ?int
    {
        if (! preg_match('/^([A-Z]+)/i', $cellRef, $matches)) {
            return null;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }
}
