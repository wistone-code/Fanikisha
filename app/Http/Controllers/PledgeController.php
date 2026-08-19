<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventOwnership;
use App\Models\Pledge;
use App\Services\MessageTemplateService;
use App\Services\PhoneNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PledgeController extends Controller
{
    use AuthorizesEventOwnership;

    public function index(Request $request): View
    {
        $event = app('currentEvent');
        $isAdmin = $request->user()->isAdminOn($event);
        $tab = ($isAdmin && ! $event->isFuneral()) ? $request->get('tab', 'list') : 'list';

        if ($tab === 'remind') {
            return view('event.pledges.remind', [
                'event' => $event,
                'outstanding' => $event->pledges()->whereColumn('paid', '<', 'amount')->get(),
            ]);
        }

        return view('event.pledges.index', [
            'event' => $event,
            'pledges' => $event->pledges()->latest()->get(),
            'isAdmin' => $isAdmin,
        ]);
    }

    public function store(Request $request, PhoneNumberService $phones): RedirectResponse
    {
        $event = app('currentEvent');
        $data = $this->validated($request);

        $event->pledges()->create([
            ...$data,
            'phone' => $phones->normalize($data['phone'] ?? null),
            // New pledges/condolences always start at 0 paid — the "Add" form no
            // longer asks for this (only "Edit" does), so don't assume the key is
            // present in $data at all.
            'paid' => $data['paid'] ?? 0,
        ]);

        $noun = $event->isFuneral() ? 'Condolence' : 'Pledge';

        return back()->with('status', "{$noun} added");
    }

    public function update(Request $request, Pledge $pledge, PhoneNumberService $phones): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);

        $data = $this->validated($request);

        $pledge->update([
            ...$data,
            'phone' => $phones->normalize($data['phone'] ?? null),
        ]);

        return back()->with('status', 'Updated');
    }

    public function destroy(Pledge $pledge): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);

        $pledge->delete();

        return back()->with('status', 'Deleted');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    // ---- Reminder: individual + broadcast messaging --------------------------------

    public function updateReminderMessage(Request $request): RedirectResponse
    {
        $data = $request->validate(['reminder_message' => ['required', 'string', 'max:5000']]);
        app('currentEvent')->update(['reminder_message' => $data['reminder_message']]);

        return back()->with('status', 'Individual message saved');
    }

    public function updateBroadcastMessage(Request $request): RedirectResponse
    {
        $data = $request->validate(['broadcast_message' => ['required', 'string', 'max:5000']]);
        app('currentEvent')->update(['broadcast_message' => $data['broadcast_message']]);

        return back()->with('status', 'Broadcast message saved');
    }

    /** Opens the phone's own SMS app with the message pre-filled, via a redirect to an sms: URI. */
    public function remindSms(Pledge $pledge, MessageTemplateService $messages): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);

        $event = app('currentEvent');
        $body = rawurlencode($messages->forReminder($event, $pledge));

        return redirect()->away('sms:'.rawurlencode($pledge->phone ?? '')."?body={$body}");
    }

    public function remindWhatsApp(Pledge $pledge, MessageTemplateService $messages, PhoneNumberService $phones): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);

        $event = app('currentEvent');
        $digits = $phones->digitsOnly($pledge->phone);
        $text = rawurlencode($messages->forReminder($event, $pledge));

        return redirect()->away("https://wa.me/{$digits}?text={$text}");
    }

    /** SMS supports a comma-joined multi-recipient group text; WhatsApp does not (see GuestController for that limitation handled via a queue). */
    public function remindAllSms(MessageTemplateService $messages, PhoneNumberService $phones): RedirectResponse
    {
        $event = app('currentEvent');
        $outstanding = $event->pledges()->whereColumn('paid', '<', 'amount')->whereNotNull('phone')->get();
        $numbers = implode(',', $outstanding->pluck('phone')->filter()->all());
        $body = rawurlencode($messages->forBroadcast($event));

        return redirect()->away('sms:'.rawurlencode($numbers)."?body={$body}");
    }

    // ---- Export ----------------------------------------------------------------------

    public function exportExcel()
    {
        $event = app('currentEvent');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\PledgesExport($event),
            str($event->name)->slug().'-pledges.xlsx'
        );
    }

    public function exportPdf()
    {
        $event = app('currentEvent');
        $pledges = $event->pledges;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.pledges-pdf', [
            'event' => $event,
            'pledges' => $pledges,
            'totalPledged' => $pledges->sum('amount'),
            'totalPaid' => $pledges->sum('paid'),
        ]);

        return $pdf->download(str($event->name)->slug().'-pledges.pdf');
    }
}
