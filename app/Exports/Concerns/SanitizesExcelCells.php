<?php

namespace App\Exports\Concerns;

trait SanitizesExcelCells
{
    /**
     * Neutralizes CSV/Excel formula injection: free-text fields are entered by an
     * event admin, and Excel/Sheets/LibreOffice all treat a cell starting with
     * =, +, -, @, tab, or CR as a formula to evaluate — e.g. a name of
     * `=HYPERLINK("http://evil.example/steal","click")` would render as a live,
     * misleadingly-labeled link when the exported file is later opened by anyone
     * (not necessarily the admin who typed it). Prefixing such values with a
     * leading apostrophe forces spreadsheet apps to treat them as plain text.
     */
    private function sanitizeCell(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}