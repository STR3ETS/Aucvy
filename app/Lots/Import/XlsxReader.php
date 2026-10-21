<?php

declare(strict_types=1);

namespace App\Lots\Import;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Leest het eerste werkblad van een xlsx zonder bibliotheek: een xlsx is
 * een zip met XML erin. Genoeg voor een kavellijst (tekst, getallen,
 * ja/nee); formules leveren hun laatst berekende waarde.
 */
final class XlsxReader
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * @return array{headers: list<string>, rows: list<list<string>>, delimiter: string}
     */
    public function read(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Het bestand is geen geldig xlsx-bestand.');
        }

        try {
            $strings = $this->sharedStrings($zip);
            $sheetPath = $this->firstSheetPath($zip);
            $stat = $zip->statName($sheetPath);

            if ($stat !== false && (int) $stat['size'] > 60_000_000) {
                throw new RuntimeException('Het werkblad is te groot om te lezen; splits het bestand.');
            }

            $sheet = $zip->getFromName($sheetPath);

            if ($sheet === false) {
                throw new RuntimeException('Het werkblad is niet gevonden in het bestand.');
            }

            $rows = $this->rows($this->xml($sheet), $strings);
        } finally {
            $zip->close();
        }

        $headers = [];
        $data = [];

        foreach ($rows as $row) {
            if (implode('', $row) === '') {
                continue;
            }

            if ($headers === []) {
                $headers = $row;

                continue;
            }

            $data[] = array_pad(array_slice($row, 0, count($headers)), count($headers), '');

            if (count($data) >= CsvReader::MAX_ROWS) {
                break;
            }
        }

        return ['headers' => $headers, 'rows' => $data, 'delimiter' => 'xlsx'];
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $raw = $zip->getFromName('xl/sharedStrings.xml');

        if ($raw === false) {
            return [];
        }

        $strings = [];

        foreach ($this->xml($raw)->children(self::MAIN_NS)->si as $item) {
            $strings[] = $this->text($item);
        }

        return $strings;
    }

    /**
     * De tekst van een <si> of <is>: gewone tekst plus de "rich runs".
     */
    private function text(SimpleXMLElement $node): string
    {
        $children = $node->children(self::MAIN_NS);
        $text = '';

        foreach ($children->t as $part) {
            $text .= (string) $part;
        }

        foreach ($children->r as $run) {
            foreach ($run->children(self::MAIN_NS)->t as $part) {
                $text .= (string) $part;
            }
        }

        return $text;
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook === false || $rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $sheets = $this->xml($workbook)->children(self::MAIN_NS)->sheets->sheet ?? null;
        $first = $sheets !== null && isset($sheets[0]) ? $sheets[0] : null;

        if ($first === null) {
            return 'xl/worksheets/sheet1.xml';
        }

        $relationId = (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;

        foreach ($this->xml($rels)->children(self::REL_NS)->Relationship as $relationship) {
            if ((string) ($relationship->attributes()['Id'] ?? '') === $relationId) {
                $target = ltrim((string) ($relationship->attributes()['Target'] ?? ''), '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param  list<string>  $strings
     * @return list<list<string>>
     */
    private function rows(SimpleXMLElement $sheet, array $strings): array
    {
        $rows = [];

        foreach ($sheet->children(self::MAIN_NS)->sheetData->row ?? [] as $row) {
            $cells = [];

            foreach ($row->children(self::MAIN_NS)->c as $cell) {
                $index = $this->columnIndex((string) ($cell->attributes()['r'] ?? ''));
                $cells[$index] = $this->cellValue($cell, $strings);
            }

            if ($cells === []) {
                $rows[] = [];

                continue;
            }

            $width = max(array_keys($cells)) + 1;
            $line = array_fill(0, $width, '');

            foreach ($cells as $index => $value) {
                $line[$index] = $value;
            }

            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * @param  list<string>  $strings
     */
    private function cellValue(SimpleXMLElement $cell, array $strings): string
    {
        $type = (string) ($cell->attributes()['t'] ?? '');
        $children = $cell->children(self::MAIN_NS);

        if ($type === 'inlineStr') {
            return isset($children->is) ? trim($this->text($children->is)) : '';
        }

        $value = isset($children->v) ? (string) $children->v : '';

        return trim(match ($type) {
            's' => $strings[(int) $value] ?? '',
            'b' => $value === '1' ? 'ja' : 'nee',
            default => $this->number($value),
        });
    }

    /**
     * Excel schrijft getallen als "1250.5" of "1.2505E3"; terug naar gewoon.
     */
    private function number(string $value): string
    {
        if ($value === '' || ! is_numeric($value)) {
            return $value;
        }

        $float = (float) $value;

        return floor($float) === $float && abs($float) < 1e15 ? (string) (int) $float : rtrim(rtrim(number_format($float, 6, '.', ''), '0'), '.');
    }

    private function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?? '';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    private function xml(string $raw): SimpleXMLElement
    {
        $xml = @simplexml_load_string($raw);

        if ($xml === false) {
            throw new RuntimeException('Het bestand bevat beschadigde XML.');
        }

        return $xml;
    }
}
