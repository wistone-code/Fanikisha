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

    /** Words that mark a couple's card, and words that mark a one-person card (English and Swahili). */
    private const DOUBLE_WORDS = ['double', 'couple', 'wawili', 'mume na mke'];

    private const SINGLE_WORDS = ['single', 'mmoja'];

    /**
     * Read one guest from an everyday list line, in whatever order people type it:
     *   "Asha Said 0712 345 678"            -> ['Asha Said', '0712 345 678', '']
     *   "1. Neema & Baraka 0765432198 double" -> ['Neema & Baraka', '0765432198', 'double']
     *   "0713000111 Rehema Ally"            -> ['Rehema Ally', '0713000111', '']
     *   "Peter Kimaro, 0712345678, single"  -> ['Peter Kimaro', '0712345678', 'single']
     * Returns [name, phone as written (or ''), 'single' | 'double' | ''].
     */
    public static function guestLine(string $line): array
    {
        $line = trim(preg_replace('/^\xEF\xBB\xBF/', '', $line));

        // List numbering or bullets at the start: "1.", "2)", "-", "*", "•".
        $line = preg_replace('/^\s*(?:\d{1,4}\s*[.)]\s+|[-*•–]\s*)/u', '', $line);

        // A copy from Excel or a CSV-style line ("Name, Phone, Card"): read it column by column.
        if (preg_match('/[\t,;]/', $line)) {
            return self::guestColumns(self::splitLine($line));
        }

        $line = str_replace("\t", ' ', $line);

        // Card type: a standalone word, also in brackets, e.g. "(double)".
        $card = '';
        foreach ([['double', self::DOUBLE_WORDS], ['single', self::SINGLE_WORDS]] as [$type, $words]) {
            foreach ($words as $word) {
                $pattern = '/(?<![\p{L}])[(\[]?'.preg_quote($word, '/').'[)\]]?(?![\p{L}])/iu';
                if (preg_match($pattern, $line)) {
                    $card = $type;
                    $line = preg_replace($pattern, ' ', $line, 1);
                    break 2;
                }
            }
        }

        // Phone: an optional +, then digits with spaces, dashes, dots or brackets, 9 to 13 digits in total.
        $phone = '';
        if (preg_match_all('/\+?\d[\d\s\-.()]{6,}\d/', $line, $matches)) {
            foreach ($matches[0] as $candidate) {
                $digits = preg_replace('/\D+/', '', $candidate);
                if (strlen($digits) >= 9 && strlen($digits) <= 13) {
                    $phone = trim($candidate);
                    $line = str_replace($candidate, ' ', $line);
                    break;
                }
            }
        }

        // What is left is the name, without separators around it.
        $name = preg_replace('/\s+/u', ' ', $line);
        $name = trim($name, " \t,;:|-–—/");
        $name = trim(preg_replace('/\s*[,;|]\s*$/', '', $name));

        return [$name, $phone, $card];
    }

    /** Column-by-column version of guestLine(): each cell is a card word, a phone number, or part of the name. */
    private static function guestColumns(array $cells): array
    {
        $card = '';
        $phone = '';
        $other = [];

        foreach ($cells as $cell) {
            $cell = trim((string) $cell);
            $lower = mb_strtolower(trim($cell, '()[] '));
            if ($cell === '') {
                $other[] = '';

                continue;
            }
            if ($card === '' && in_array($lower, self::DOUBLE_WORDS, true)) {
                $card = 'double';
            } elseif ($card === '' && in_array($lower, self::SINGLE_WORDS, true)) {
                $card = 'single';
            } elseif ($phone === '' && ! preg_match('/\p{L}/u', $cell) && ($n = strlen(preg_replace('/\D+/', '', $cell))) >= 9 && $n <= 13) {
                $phone = $cell;
            } else {
                $other[] = $cell;
            }
        }

        // Old-style rows "Name, Phone" where the phone cell is not a valid number still keep the first cell as the name.
        $name = $other[0] ?? '';
        if ($phone === '' && isset($other[1])) {
            $phone = $other[1];
        }

        return [trim($name, " \t-–—"), $phone, $card];
    }
}
