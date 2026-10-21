<?php

declare(strict_types=1);

namespace App\Lots\Import;

/**
 * Eén ingang voor csv en xlsx: op extensie naar de juiste lezer, met
 * hetzelfde resultaat (kopregel plus rijen van tekst).
 */
final class SpreadsheetReader
{
    public function __construct(
        private readonly CsvReader $csv,
        private readonly XlsxReader $xlsx,
    ) {}

    /**
     * @return array{headers: list<string>, rows: list<list<string>>, delimiter: string}
     */
    public function read(string $path): array
    {
        return self::isXlsx($path) ? $this->xlsx->read($path) : $this->csv->read($path);
    }

    /**
     * Op inhoud, niet op extensie: een xlsx is een zip en begint met "PK".
     */
    public static function isXlsx(string $path): bool
    {
        return is_file($path) && (string) file_get_contents($path, false, null, 0, 4) === "PK\x03\x04";
    }
}
