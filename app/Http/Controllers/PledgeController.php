<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventOwnership;
use App\Models\Pledge;
use App\Services\BeemSmsService;
use App\Services\DocxTextExtractor;
use App\Services\MessageTemplateService;
use App\Services\PhoneNumberService;
use App\Services\PledgeImportService;
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
            // Set once here, at save time — not re-evaluated if the threshold
            // changes later, so existing pledges keep whatever type they got.
            'card_type' => $this->cardTypeFor($event, (float) $data['amount']),
        ]);

        $noun = $event->isFuneral() ? 'Condolence' : 'Pledge';

        return back()->with('status', "{$noun} added");
    }

    public function update(Request $request, Pledge $pledge, PhoneNumberService $phones, MessageTemplateService $messages, BeemSmsService $sms): RedirectResponse
    {
        $this->assertPledgeInCurrentEvent($pledge);

        $data = $this->validated($request);
        $previousPaid = (float) $pledge->paid;

        // "Add payment" is the normal path — the field is blank on the Edit
        // form and whatever's typed there is added on top of what's already
        // paid, so the admin never has to re-type or remember the running
        // total. "Correct total" is a separate, deliberate override for
        // fixing a mistaken entry, and wins if both somehow arrive at once.
        $newPaid = $previousPaid;
        if (($data['paid_correction'] ?? '') !== '') {
            $newPaid = (float) $data['paid_correction'];
        } elseif (($data['add_payment'] ?? '') !== '') {
            $newPaid = $previousPaid + (float) $data['add_payment'];
        }

        $pledge->update([
            'name' => $data['name'],
            'amount' => $data['amount'],
            'phone' => $phones->normalize($data['phone'] ?? null),
            'paid' => $newPaid,
            // Re-evaluated against the new amount every time this is saved —
            // but only using the CURRENT threshold, not retroactively applied
            // if the threshold itself changes without this pledge being re-saved.
            'card_type' => $this->cardTypeFor($pledge->event, (float) $data['amount']),
        ]);

        $status = 'Updated';
        $reLocked = false;

        // A "Paid" correction (e.g. an amount entered by mistake, then fixed)
        // can bring a pledge back below fully paid. If their invitation was
        // already activated based on that mistaken figure, it should no
        // longer be reachable — otherwise a guest could keep an invite link
        // that was only ever valid because of a data-entry error.
        if ($pledge->invite_token && ! $pledge->isPaidInFull()) {
            $pledge->update(['invite_token' => null]);
            $reLocked = true;
        }

        // Only fires when the paid amount actually went up (not on every save) and there's a phone to text.
        if ((float) $pledge->paid > $previousPaid) {
            $status = "Payment recorded for {$pledge->name} — Paid: ".number_format($pledge->paid).', Balance: '.number_format($pledge->remaining());

            if ($pledge->phone) {
                $event = app('currentEvent');
                $result = $sms->sendSingle($messages->forPledgePayment($event, $pledge), $pledge->phone);

                $status .= $result['successful'] ? ' (SMS sent)' : ' — but SMS failed: '.($result['error'] ?? 'unknown error');
            }
        }

        if ($reLocked) {
            $status .= " — invitation link removed (no longer paid in full).";
        }

        return back()->with('status', $status);
    }

    /**
     * Bulk-adds pledgers from an uploaded CSV/text/Word file, or pasted text —
     * all use the same column order: Name, Phone, Amount. Rows missing a
     * name or a valid positive amount are skipped (this also naturally
     * skips a header row, since "Amount" isn't a valid number).
     */
    public function import(Request $request, PhoneNumberService $phones, DocxTextExtractor $docx): RedirectResponse
    {
        $event = app('currentEvent');

        $request->validate([
            'import_file' => ['nullable', 'file', 'mimes:csv,txt,docx', 'max:5120'],
            'import_text' => ['nullable', 'string'],
        ]);

        $rows = collect();

        if ($request->hasFile('import_file')) {
            $file = $request->file('import_file');
            $extension = strtolower($file->getClientOriginalExtension());

            if ($extension === 'docx') {
                $rows = collect(explode("\n", trim($docx->extract($file->getRealPath()))))
                    ->map(fn ($line) => trim($line))
                    ->filter(fn ($line) => $line !== '')
                    ->map(fn ($line) => preg_split('/\t|,/', $line));
            } else {
                // A real CSV reader (rather than a plain comma-split) correctly
                // handles a quoted field that itself contains a comma, e.g. a
                // name written "Doe, Jr.".
                $handle = fopen($file->getRealPath(), 'r');

                while (($line = fgetcsv($handle)) !== false) {
                    $rows->push($line);
                }

                fclose($handle);
            }
        } elseif ($request->filled('import_text')) {
            $rows = collect(explode("\n", trim($request->input('import_text'))))
                ->filter(fn ($line) => trim($line) !== '')
                ->map(fn ($line) => preg_split('/\t|,/', trim($line)));
        }

        if ($rows->isEmpty()) {
            return back()->withErrors(['import_file' => 'Upload a file or paste some rows first.']);
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
                'card_type' => $this->cardTypeFor($event, $amount),
            ]);

            $imported++;
        }

        $noun = $event->isFuneral() ? 'condolence(s)' : 'pledge(s)';

        return back()->with('status', "Imported {$imported} {$noun}".($skipped > 0 ? ", skipped {$skipped} invalid row(s)" : '').'.');
    }

    /**
     * Extract candidate pledgers (name/phone/amount) from one or more photos of
     * a written or printed pledge list — using Gemini's vision capability, the
     * same as the Schedule page's photo import. Nothing is saved here; this
     * only returns candidates for the review screen to confirm or edit.
     */
    public function importPhoto(Request $request, PledgeImportService $importer): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:4'],
            'photos.*' => ['required', 'image', 'max:10240'], // 10MB each
        ]);

        $result = $importer->extract($request->file('photos'));

        if (! $result['successful']) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json(['items' => $result['items']]);
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
            'add_payment' => ['nullable', 'numeric', 'min:0'],
            'paid_correction' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /** Null threshold means the feature is off — every pledge is "single". */
    private function cardTypeFor(\App\Models\Event $event, float $amount): string
    {
        $threshold = $event->couple_threshold_amount;

        return ($threshold !== null && $amount >= (float) $threshold) ? 'double' : 'single';
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