<?php

declare(strict_types=1);

namespace App\Lots\Import;

/**
 * Leest een csv zoals Nederlandse spreadsheets hem opslaan: puntkomma of
 * komma, met of zonder BOM. Geen bibliotheek nodig voor wat hier gevraagd
 * wordt: kopregel plus rijen.
 */
final class CsvReader
{
    public const MAX_ROWS = 500;

    /**
     * @return array{headers: list<string>, rows: list<list<string>>, delimiter: string}
     */
    public function read(string $path): array
    {
        $contents = (string) file_get_contents($path);
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        $delimiter = $this->detectDelimiter($contents);
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $headers = [];
        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if ($row === [null] || implode('', array_map('trim', array_map('strval', $row))) === '') {
                continue;
            }

            $row = array_map(fn (mixed $cell): string => trim((string) $cell), $row);

            if ($headers === []) {
                $headers = $row;

                continue;
            }

            $rows[] = $row;

            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }

        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows, 'delimiter' => $delimiter];
    }

    private function detectDelimiter(string $contents): string
    {
        $firstLine = strtok($contents, "\r\n") ?: '';
        $counts = [';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
