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

    // ---- Broadcast SMS ----------------------------------------------------------------

    public function updateMessage(Request $request): RedirectResponse
    {
        $data = $request->validate(['schedule_message' => ['required', 'string', 'max:5000']]);
        app('currentEvent')->update(['schedule_message' => $data['schedule_message']]);

        return back()->with('status', 'Broadcast message saved');
    }

    public function broadcast(Request $request, MessageTemplateService $messages, BeemSmsService $sms): RedirectResponse
    {
        $event = app('currentEvent');
        $broadcastMessage = $messages->forSchedule($event);
        $pledgers = $event->pledges()->whereNotNull('phone')->get();

        if (trim($broadcastMessage) === '') {
            return back()->with('status', 'Save a broadcast message first, then try again.');
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