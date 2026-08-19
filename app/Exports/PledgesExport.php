<?php

namespace App\Exports;

use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PledgesExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly Event $event) {}

    public function collection()
    {
        $rows = $this->event->pledges->map(fn ($p) => [
            'Name' => $this->sanitizeCell($p->name),
            'Pledge amount' => (float) $p->amount,
            'Paid' => (float) $p->paid,
            'Remain' => $p->remaining(),
            'Phone' => $this->sanitizeCell($p->phone),
        ]);

        return $rows->push([
            'Name' => 'Total',
            'Pledge amount' => $rows->sum('Pledge amount'),
            'Paid' => $rows->sum('Paid'),
            'Remain' => $rows->sum('Remain'),
            'Phone' => '',
        ]);
    }

    public function headings(): array
    {
        return ['Name', 'Pledge amount', 'Paid', 'Remain', 'Phone'];
    }

    /**
     * Neutralizes CSV/Excel formula injection: a pledge or provider name is
     * free-text entered by an event admin, and Excel/Sheets/LibreOffice all treat
     * a cell starting with =, +, -, @, tab, or CR as a formula to evaluate —
     * e.g. a name of `=HYPERLINK("http://evil.example/steal","click")` would
     * render as a live, misleadingly-labeled link when the exported file is later
     * opened by anyone (not necessarily the admin who typed it). Prefixing such
     * values with a leading apostrophe forces spreadsheet apps to treat them as
     * plain text instead of evaluating them.
     */
    private function sanitizeCell(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
