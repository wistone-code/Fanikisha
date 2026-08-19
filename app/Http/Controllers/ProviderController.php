<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventOwnership;
use App\Models\Provider;
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

    public function update(Request $request, Provider $provider, PhoneNumberService $phones): RedirectResponse
    {
        $this->assertProviderInCurrentEvent($provider);

        $data = $this->validated($request);

        $provider->update([
            ...$data,
            'phone' => $phones->normalize($data['phone'] ?? null),
        ]);

        return back()->with('status', 'Provider updated');
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
            'phone' => ['nullable', 'string', 'max:32'],
        ]);
    }

    public function updateMessage(Request $request): RedirectResponse
    {
        $data = $request->validate(['provider_message' => ['required', 'string', 'max:5000']]);
        app('currentEvent')->update(['provider_message' => $data['provider_message']]);

        return back()->with('status', 'Contact message saved');
    }

    public function sendSms(Provider $provider, MessageTemplateService $messages): RedirectResponse
    {
        $this->assertProviderInCurrentEvent($provider);

        $event = app('currentEvent');
        $body = rawurlencode($messages->forProvider($event, $provider));

        return redirect()->away('sms:'.rawurlencode($provider->phone ?? '')."?body={$body}");
    }

    public function sendWhatsApp(Provider $provider, MessageTemplateService $messages, PhoneNumberService $phones): RedirectResponse
    {
        $this->assertProviderInCurrentEvent($provider);

        $event = app('currentEvent');
        $digits = $phones->digitsOnly($provider->phone);
        $text = rawurlencode($messages->forProvider($event, $provider));

        return redirect()->away("https://wa.me/{$digits}?text={$text}");
    }
}
