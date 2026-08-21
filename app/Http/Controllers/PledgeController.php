<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventOwnership;
use App\Models\Pledge;
use App\Services\BeemSmsService;
use App\Services\MessageTemplateService;
use App\Services\PhoneNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            // Always generated immediately (unlike invite_token, which only
            // appears once paid in full) so the "Pay now" link works right away.
            'pay_token' => Str::random(32),
        ]);

        $noun = $event->isFuneral() ? 'Condolence' : 'Pledge';

        return back()->with('status', "{$noun} added");
    }

    public function update(Request $request, Pledge $pledge, PhoneNumberService $phones, MessageTemplateService $messages, BeemSmsService $sms): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);

        $data = $this->validated($request);
        $previousPaid = (float) $pledge->paid;

        $pledge->update([
            ...$data,
            'phone' => $phones->normalize($data['phone'] ?? null),
            // Left blank on the Edit form means "don't change it" — without this,
            // an empty string gets saved into the decimal column and crashes.
            'paid' => ($data['paid'] ?? '') !== '' ? $data['paid'] : $pledge->paid,
        ]);

        $status = 'Updated';

        // Only fires when the paid amount actually went up (not on every save) and there's a phone to text.
        if ((float) $pledge->paid > $previousPaid) {
            $status = "Payment recorded for {$pledge->name} — Paid: ".number_format($pledge->paid).', Balance: '.number_format($pledge->remaining());

            if ($pledge->phone) {
                $event = app('currentEvent');
                $result = $sms->sendSingle($messages->forPledgePayment($event, $pledge), $pledge->phone);

                $status .= $result['successful'] ? ' (SMS sent)' : ' — but SMS failed: '.($result['error'] ?? 'unknown error');
            }
        }

        return back()->with('status', $status);
    }

    /**
     * Bulk-adds pledgers from either an uploaded CSV file or pasted text —
     * both use the same column order: Name, Phone, Amount. Rows missing a
     * name or a valid positive amount are skipped (this also naturally
     * skips a header row, since "Amount" isn't a valid number).
     */
    public function import(Request $request, PhoneNumberService $phones): RedirectResponse
    {
        $event = app('currentEvent');

        $request->validate([
            'import_file' => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
            'import_text' => ['nullable', 'string'],
        ]);

        $rows = collect();

        if ($request->hasFile('import_file')) {
            $handle = fopen($request->file('import_file')->getRealPath(), 'r');

            while (($line = fgetcsv($handle)) !== false) {
                $rows->push($line);
            }

            fclose($handle);
        } elseif ($request->filled('import_text')) {
            $rows = collect(explode("\n", trim($request->input('import_text'))))
                ->filter(fn ($line) => trim($line) !== '')
                ->map(fn ($line) => preg_split('/\t|,/', trim($line)));
        }

        if ($rows->isEmpty()) {
            return back()->withErrors(['import_file' => 'Upload a CSV file or paste some rows first.']);
        }

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $name = trim((string) ($row[0] ?? ''));
            $phone = trim((string) ($row[1] ?? ''));
            $amountRaw = str_replace([',', ' '], '', trim((string) ($row[2] ?? '')));
            $amount = is_numeric($amountRaw) ? (float) $amountRaw : null;

            if ($name === '' || $amount === null || $amount <= 0) {
                $skipped++;

                continue;
            }

            $event->pledges()->create([
                'name' => $name,
                'phone' => $phones->normalize($phone ?: null),
                'amount' => $amount,
                'paid' => 0,
                'pay_token' => Str::random(32),
            ]);

            $imported++;
        }

        $noun = $event->isFuneral() ? 'condolence(s)' : 'pledge(s)';

        return back()->with('status', "Imported {$imported} {$noun}".($skipped > 0 ? ", skipped {$skipped} invalid row(s)" : '').'.');
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

    /** Sends the reminder directly via Beem SMS instead of opening the phone's Messages app. */
    public function remindSms(Pledge $pledge, MessageTemplateService $messages, BeemSmsService $sms): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);

        $event = app('currentEvent');
        $result = $sms->sendSingle($messages->forReminder($event, $pledge), $pledge->phone);

        return back()->with('status', $result['successful']
            ? "Reminder sent to {$pledge->name}."
            : 'SMS send failed: '.($result['error'] ?? 'Unknown error'));
    }

    public function remindWhatsApp(Pledge $pledge, MessageTemplateService $messages, PhoneNumberService $phones): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);

        $event = app('currentEvent');
        $digits = $phones->digitsOnly($pledge->phone);
        $text = rawurlencode($messages->forReminder($event, $pledge));

        return redirect()->away("https://wa.me/{$digits}?text={$text}");
    }

    /** Sends the broadcast to every outstanding pledger directly via Beem SMS. */
    public function remindAllSms(MessageTemplateService $messages, BeemSmsService $sms): RedirectResponse
    {
        $event = app('currentEvent');
        $message = $messages->forBroadcast($event);

        if (trim($message) === '') {
            return back()->withErrors(['broadcast_message' => 'Write a broadcast message first, then save it before sending.']);
        }

        $outstanding = $event->pledges()->whereColumn('paid', '<', 'amount')->whereNotNull('phone')->get();

        if ($outstanding->isEmpty()) {
            return back()->withErrors(['broadcast_message' => 'No outstanding pledgers with a phone number to message.']);
        }

        $result = $sms->sendBulk($message, $outstanding);

        return back()->with('status', $result['successful']
            ? "Reminder sent via SMS to {$result['valid']} pledger(s)."
                .(($result['invalid'] ?? 0) > 0 ? " {$result['invalid']} number(s) were invalid." : '')
            : 'SMS send failed: '.($result['error'] ?? 'Unknown error'));
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