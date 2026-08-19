<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesExcelCells;
use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PledgesExport implements FromCollection, WithHeadings
{
    use SanitizesExcelCells;

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
}