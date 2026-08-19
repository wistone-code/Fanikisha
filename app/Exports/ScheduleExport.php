<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesExcelCells;
use App\Models\Event;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ScheduleExport implements FromCollection, WithHeadings
{
    use SanitizesExcelCells;

    public function __construct(private readonly Event $event) {}

    public function collection()
    {
        return $this->event->scheduleItems->map(fn ($item) => [
            'Event' => $this->sanitizeCell($item->title),
            'Date' => $item->date->format('Y-m-d'),
            'Time' => $item->time ? \Carbon\Carbon::parse($item->time)->format('g:i A') : '',
        ]);
    }

    public function headings(): array
    {
        return ['Event', 'Date', 'Time'];
    }
}