<?php

namespace App\Support;

/** Helpers for pasted/uploaded guest and pledge lists (Excel exports, phone keyboards, thousands separators). */
class ImportRows
{
    /** Remove a UTF-8 byte-order mark that Excel puts in front of the first cell of a CSV. */
    public static function stripBom(array $row): array
    {
        if (isset($row[0]) && is_string($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
        }

        return $row;
    }

    /**
     * Split one pasted line: tab first (a copy from Excel), then semicolon (European Excel CSV), else comma.
     * With $amountLast, extra comma pieces are glued onto the last column, so "John,0712345678,1,000,000" keeps
     * the amount 1,000,000 instead of 1.
     */
    public static function splitLine(string $line, int $columns = 3, bool $amountLast = false): array
    {
        $line = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line));

        if (str_contains($line, "\t")) {
            return array_map('trim', explode("\t", $line));
        }

        if (str_contains($line, ';')) {
            return array_map('trim', explode(';', $line));
        }

        $parts = array_map('trim', explode(',', $line));

        if ($amountLast && count($parts) > $columns) {
            $parts = array_merge(array_slice($parts, 0, $columns - 1), [implode('', array_slice($parts, $columns - 1))]);
        }

        return $parts;
    }
}
