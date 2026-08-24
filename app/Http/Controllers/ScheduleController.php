<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventOwnership;
use App\Models\ScheduleItem;
use App\Services\BeemSmsService;
use App\Services\MessageTemplateService;
use App\Services\ScheduleImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    use AuthorizesEventOwnership;

    public function index(Request $request, MessageTemplateService $messages): View
    {
        $event = app('currentEvent');

        return view('event.schedule.index', [
            'event' => $event,
            'items' => $event->scheduleItems,
            'isAdmin' => $request->user()->isAdminOn($event),
            'pledgers' => $event->pledges()->whereNotNull('phone')->get(),
            'broadcastMessage' => $messages->forSchedule($event),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        app('currentEvent')->scheduleItems()->create($data);

        return back()->with('status', 'Schedule item added');
    }

    public function update(Request $request, ScheduleItem $item): RedirectResponse
    {
        $this->assertScheduleItemInCurrentEvent($item);

        $item->update($this->validated($request));

        return back()->with('status', 'Schedule item updated');
    }

    public function destroy(ScheduleItem $item): RedirectResponse
    {
        $this->assertScheduleItemInCurrentEvent($item);

        $item->delete();

        return back()->with('status', 'Schedule item deleted');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
        ]);
    }

    // ---- Import from photo -------------------------------------------------------------

    public function importPhoto(Request $request, ScheduleImportService $importer): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:4'],
            'photos.*' => ['required', 'image', 'max:10240'], // 10MB each
        ]);

        $result = $importer->extract($request->file('photos'), app('currentEvent'));

        if (! $result['successful']) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json(['items' => $result['items']]);
    }

    // ---- Import from text/CSV/Word file -----------------------------------------------

    /**
     * Bulk-add schedule items from a CSV/text/Word file, or pasted text — no AI, no API
     * cost. Mirrors PledgeController::import()'s established format: one item per line/row,
     * as Title, Date, Time (time optional). Invalid rows (no title, or a date that can't be
     * parsed) are skipped rather than blocking the whole import, and the result is reported
     * back the same way pledge imports are.
     */
    public function importText(Request $request): RedirectResponse
    {
        $event = app('currentEvent');

        $request->validate([
            'import_file' => ['nullable', 'file', 'mimes:csv,txt,docx', 'max:5120'],
            'import_text' => ['nullable', 'string'],
        ]);

        $rawText = '';

        if ($request->hasFile('import_file')) {
            $file = $request->file('import_file');

            $rawText = strtolower($file->getClientOriginalExtension()) === 'docx'
                ? $this->extractDocxText($file->getRealPath())
                : file_get_contents($file->getRealPath());
        } elseif ($request->filled('import_text')) {
            $rawText = $request->input('import_text');
        }

        $rows = collect(explode("\n", trim((string) $rawText)))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => $line !== '')
            ->map(fn ($line) => preg_split('/\t|,/', $line));

        if ($rows->isEmpty()) {
            return back()->withErrors(['import_file' => 'Upload a file or paste some rows first.']);
        }

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $title = trim((string) ($row[0] ?? ''));
            $dateRaw = trim((string) ($row[1] ?? ''));
            $timeRaw = trim((string) ($row[2] ?? ''));

            if ($title === '' || $dateRaw === '') {
                $skipped++;

                continue;
            }

            try {
                $date = \Carbon\Carbon::parse($dateRaw)->toDateString();
            } catch (\Throwable $e) {
                $skipped++;

                continue;
            }

            $time = null;
            if ($timeRaw !== '') {
                try {
                    $time = \Carbon\Carbon::parse($timeRaw)->format('H:i');
                } catch (\Throwable $e) {
                    // A bad time doesn't need to sink an otherwise-good row.
                }
            }

            $event->scheduleItems()->create(['title' => $title, 'date' => $date, 'time' => $time]);
            $imported++;
        }

        return back()->with('status', "Imported {$imported} schedule item(s)"
            .($skipped > 0 ? ", skipped {$skipped} invalid row(s)" : '').'.');
    }

    /**
     * Word .docx files are a zip archive of XML — this reads word/document.xml directly via
     * PHP's built-in ZipArchive (already available; the Dockerfile installs the zip
     * extension) rather than pulling in a new composer dependency just for this. Paragraph
     * breaks become newlines before stripping tags, so each line of the original document
     * comes through as one line of text.
     */
    private function extractDocxText(string $path): string
    {
        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return '';
        }

        $xml = str_replace(['</w:p>', '<w:br/>', '<w:br />'], "\n", $xml);
        $text = strip_tags($xml);

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    // ---- Broadcast SMS ----------------------------------------------------------------

    public function broadcast(Request $request, MessageTemplateService $messages, BeemSmsService $sms): RedirectResponse
    {
        $event = app('currentEvent');
        $broadcastMessage = $messages->forSchedule($event);
        $pledgers = $event->pledges()->whereNotNull('phone')->get();

        if (trim($broadcastMessage) === '') {
            return back()->with('status', 'Add at least one schedule item first, then try again.');
        }

        if ($pledgers->isEmpty()) {
            return back()->with('status', 'No pledgers with a phone number to message.');
        }

        $result = $sms->sendBulk($broadcastMessage, $pledgers);

        if ($result['successful']) {
            return back()->with('status', "Schedule sent via SMS to {$result['valid']} pledger(s)."
                .(($result['invalid'] ?? 0) > 0 ? " {$result['invalid']} number(s) were invalid." : ''));
        }

        return back()->with('status', 'SMS send failed: '.($result['error'] ?? 'Unknown error'));
    }

    // ---- Export ----------------------------------------------------------------------

    public function exportExcel()
    {
        $event = app('currentEvent');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ScheduleExport($event),
            str($event->name)->slug().'-schedule.xlsx'
        );
    }

    public function exportPdf()
    {
        $event = app('currentEvent');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.schedule-pdf', [
            'event' => $event,
            'items' => $event->scheduleItems,
        ]);

        return $pdf->download(str($event->name)->slug().'-schedule.pdf');
    }
}