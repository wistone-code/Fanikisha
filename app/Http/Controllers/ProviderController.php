<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventOwnership;
use App\Models\Provider;
use App\Services\BeemSmsService;
use App\Services\MessageTemplateService;
use App\Services\PhoneNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProviderController extends Controller
{
    use AuthorizesEventOwnership;

    public function index(Request $request): View
    {
        $event = app('currentEvent');

        return view('event.providers.index', [
            'event' => $event,
            'providers' => $event->providers()->latest()->get(),
            'total' => $event->providers()->sum('budget'),
            'isAdmin' => $request->user()->isAdminOn($event),
        ]);
    }

    public function store(Request $request, PhoneNumberService $phones): RedirectResponse
    {
        $data = $this->validated($request);

        app('currentEvent')->providers()->create([
            ...$data,
            'phone' => $phones->normalize($data['phone'] ?? null),
        ]);

        return back()->with('status', 'Provider added');
    }

    public function update(Request $request, Provider $provider, PhoneNumberService $phones, MessageTemplateService $messages, BeemSmsService $sms): RedirectResponse
    {
        $this->assertProviderInCurrentEvent($provider);

        $data = $this->validated($request);
        $previousPaid = (float) $provider->paid;

        $provider->update([
            ...$data,
            'phone' => $phones->normalize($data['phone'] ?? null),
            // Left blank on the Edit form means "don't change it" — without this,
            // a blank value gets saved into the decimal column and crashes.
            'paid' => ($data['paid'] ?? '') !== '' ? $data['paid'] : $provider->paid,
        ]);

        $status = 'Provider updated';

        // Only fires when the paid amount actually went up (not on every save) and there's a phone to text.
        if ((float) $provider->paid > $previousPaid) {
            $status = "Payment recorded for {$provider->service} — Paid: ".number_format($provider->paid).', Balance: '.number_format($provider->remaining());

            if ($provider->phone) {
                $event = app('currentEvent');
                $result = $sms->sendSingle($messages->forProviderPayment($event, $provider), $provider->phone);

                $status .= $result['successful'] ? ' (SMS sent)' : ' — but SMS failed: '.($result['error'] ?? 'unknown error');
            }
        }

        return back()->with('status', $status);
    }

    public function destroy(Provider $provider): RedirectResponse
    {
        $this->assertProviderInCurrentEvent($provider);

        $provider->delete();

        return back()->with('status', 'Provider deleted');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'service' => ['required', 'string', 'max:255'],
            'budget' => ['required', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);
    }

    public function updateMessage(Request $request): RedirectResponse
    {
        $data = $request->validate(['provider_message' => ['required', 'string', 'max:5000']]);
        app('currentEvent')->update(['provider_message' => $data['provider_message']]);

        return back()->with('status', 'Contact message saved');
    }

    /** Sends the message directly via Beem SMS instead of opening the phone's Messages app. */
    public function sendSms(Provider $provider, MessageTemplateService $messages, BeemSmsService $sms): RedirectResponse
    {
        $this->assertProviderInCurrentEvent($provider);

        $event = app('currentEvent');
        $result = $sms->sendSingle($messages->forProvider($event, $provider), $provider->phone);

        return back()->with('status', $result['successful']
            ? "Message sent to {$provider->name}."
            : 'SMS send failed: '.($result['error'] ?? 'Unknown error'));
    }

    public function sendWhatsApp(Provider $provider, MessageTemplateService $messages, PhoneNumberService $phones): RedirectResponse
    {
        $this->assertProviderInCurrentEvent($provider);

        $event = app('currentEvent');
        $digits = $phones->digitsOnly($provider->phone);
        $text = rawurlencode($messages->forProvider($event, $provider));

        return redirect()->away("https://wa.me/{$digits}?text={$text}");
    }

    /** Sends a payment-confirmation SMS with the current paid/budget/remaining figures. */
    public function confirmPaymentSms(Provider $provider, MessageTemplateService $messages, BeemSmsService $sms): RedirectResponse
    {
        $this->assertProviderInCurrentEvent($provider);

        $event = app('currentEvent');
        $result = $sms->sendSingle($messages->forProviderPayment($event, $provider), $provider->phone);

        return back()->with('status', $result['successful']
            ? "Payment confirmation sent to {$provider->name}."
            : 'SMS send failed: '.($result['error'] ?? 'Unknown error'));
    }

    // ---- Export ----------------------------------------------------------------------

    public function exportExcel()
    {
        $event = app('currentEvent');

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ProvidersExport($event),
            str($event->name)->slug().'-providers.xlsx'
        );
    }

    public function exportPdf()
    {
        $event = app('currentEvent');
        $providers = $event->providers;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.providers-pdf', [
            'event' => $event,
            'providers' => $providers,
            'totalBudget' => $providers->sum('budget'),
        ]);

        return $pdf->download(str($event->name)->slug().'-providers.pdf');
    }
}