<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventOwnership;
use App\Models\ScheduleItem;
use App\Services\MessageTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    use AuthorizesEventOwnership;

    public function index(Request $request): View
    {
        $event = app('currentEvent');

        return view('event.schedule.index', [
            'event' => $event,
            'items' => $event->scheduleItems,
            'isAdmin' => $request->user()->isAdminOn($event),
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

    // ---- Broadcast SMS ----------------------------------------------------------------

    public function updateMessage(Request $request): RedirectResponse
    {
        $data = $request->validate(['schedule_message' => ['required', 'string', 'max:5000']]);
        app('currentEvent')->update(['schedule_message' => $data['schedule_message']]);

        return back()->with('status', 'Broadcast message saved');
    }

    public function broadcastSms(MessageTemplateService $messages): RedirectResponse
    {
        $event = app('currentEvent');
        $message = $messages->forSchedule($event);

        if (trim($message) === '') {
            return back()->withErrors(['schedule_message' => 'Write a broadcast message first, then save it before sending.']);
        }

        $numbers = $event->pledges()->whereNotNull('phone')->pluck('phone');

        if ($numbers->isEmpty()) {
            return back()->withErrors(['schedule_message' => 'No contacts with a phone number to message.']);
        }

        $body = rawurlencode($message);

        return redirect()->away('sms:'.rawurlencode($numbers->implode(','))."?body={$body}");
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