<?php

declare(strict_types=1);

namespace Cora\Services\Import;

use Cora\Core\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;

/**
 * Reads an uploaded CSV / XLSX into rows of strings (first sheet only).
 * CSV needs nothing extra; XLSX / XLS need PhpSpreadsheet (composer install).
 */
final class ImportFileReader
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const MAX_ROWS = 20000;

    /**
     * @return list<list<string>> rows including the header row
     */
    public static function read(string $path, string $originalName): array
    {
        if (!is_file($path) || filesize($path) === 0) {
            throw ValidationException::single('file', 'The uploaded file is empty.');
        }
        if (filesize($path) > self::MAX_BYTES) {
            throw ValidationException::single('file', 'The file is larger than 10 MB.');
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($ext) {
            'csv', 'txt' => self::csv($path),
            'xlsx', 'xls' => self::spreadsheet($path),
            default => throw ValidationException::single('file', 'Upload a .csv or .xlsx file.'),
        };
    }

    /** @return list<list<string>> */
    private static function csv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::single('file', 'The file could not be read.');
        }
        $first = (string) fgets($handle);
        rewind($handle);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $rows = [];
        while (($cols = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);
                throw ValidationException::single('file', 'The file has more than ' . self::MAX_ROWS . ' rows. Split it into smaller files.');
            }
            $rows[] = array_map(static fn ($v): string => trim((string) $v), $cols);
        }
        fclose($handle);

        if (isset($rows[0][0])) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]) ?? $rows[0][0];
        }

        return self::dropBlank($rows);
    }

    /** @return list<list<string>> */
    private static function spreadsheet(string $path): array
    {
        if (!class_exists(IOFactory::class)) {
            throw ValidationException::single('file', 'XLSX upload needs PhpSpreadsheet on the server (run composer install in cora/). Save the file as CSV and upload that instead.');
        }
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getSheet(0);
        } catch (SpreadsheetReaderException $e) {
            throw ValidationException::single('file', 'The spreadsheet could not be read: ' . $e->getMessage());
        }
        if ($sheet->getHighestDataRow() > self::MAX_ROWS + 1) {
            throw ValidationException::single('file', 'The file has more than ' . self::MAX_ROWS . ' rows. Split it into smaller files.');
        }

        $rows = [];
        foreach ($sheet->toArray(null, true, false, false) as $cols) {
            $rows[] = array_map(static fn ($v): string => trim((string) $v), $cols);
        }

        return self::dropBlank($rows);
    }

    /**
     * @param list<list<string>> $rows
     * @return list<list<string>>
     */
    private static function dropBlank(array $rows): array
    {
        return array_values(array_filter($rows, static fn (array $r): bool => implode('', $r) !== ''));
    }
}
