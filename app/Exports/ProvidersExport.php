<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesExcelCells;
use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProvidersExport implements FromCollection, WithHeadings
{
    use SanitizesExcelCells;

    public function __construct(private readonly Event $event) {}

    public function collection()
    {
        $rows = $this->event->providers->map(fn ($p) => [
            'Name' => $this->sanitizeCell($p->name),
            'Service' => $this->sanitizeCell($p->service),
            'Budget' => (float) $p->budget,
            'Contact' => $this->sanitizeCell($p->phone),
        ]);

        return $rows->push([
            'Name' => 'Total',
            'Service' => '',
            'Budget' => $rows->sum('Budget'),
            'Contact' => '',
        ]);
    }

    public function headings(): array
    {
        return ['Name', 'Service', 'Budget', 'Contact'];
    }
}