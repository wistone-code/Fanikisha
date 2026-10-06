<?php

namespace App\Http\Controllers;

use App\Models\AccountRequest;
use App\Models\DataRequest;
use App\Services\OptOutService;
use App\Services\PhoneNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class PublicPageController extends Controller
{
    public function home(Request $request)
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return view('public.home', ['lang' => $this->lang($request)]);
    }

    public function privacy(Request $request)
    {
        return $this->legal($request, 'privacy', 'Privacy Policy', 'Sera ya Faragha', 'How Fanikisha collects, uses and protects personal data of organisers and event guests.');
    }

    public function terms(Request $request)
    {
        return $this->legal($request, 'terms', 'Terms of Service', 'Vigezo na Masharti', 'The terms for using the Fanikisha event invitation and contribution manager.');
    }

    public function acceptableUse(Request $request)
    {
        return $this->legal($request, 'acceptable-use', 'Acceptable Use and Messaging Policy', 'Sera ya Matumizi na Ujumbe', 'Rules for who organisers may message on Fanikisha and how, including consent and opting out.');
    }

    public function dataRequestForm(Request $request)
    {
        return view('public.data-request', ['lang' => $this->lang($request)]);
    }

    public function dataRequestStore(Request $request, OptOutService $optOuts): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', DataRequest::TYPES)],
            'name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'details' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'max:0'], // honeypot — humans leave it empty
        ]);

        if (blank($data['phone'] ?? null) && blank($data['email'] ?? null)) {
            return back()->withInput()->withErrors(['phone' => 'Please give a phone number or an email address so we can find your data and reply.']);
        }

        unset($data['website']);

        // A stop request from this open form is NOT applied automatically: anyone could type someone
        // else's number. The System Admin reviews it and approves the block (Privacy & opt-outs screen).
        $dataRequest = DataRequest::create($data + ['status' => 'new']);

        $this->notifyDpo($dataRequest);

        return redirect()->route('data-request', ['lang' => $request->query('lang')])
            ->with('sent', 'received');
    }

    public function accountRequestForm(Request $request)
    {
        return view('public.account-request', ['lang' => $this->lang($request)]);
    }

    public function accountRequestStore(Request $request, PhoneNumberService $phones): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'event_type' => ['required', 'in:'.implode(',', AccountRequest::EVENT_TYPES)],
            'event_date' => ['nullable', 'date', 'after_or_equal:today'],
            'location' => ['nullable', 'string', 'max:150'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'needs' => ['required', 'in:'.implode(',', AccountRequest::NEEDS)],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'max:0'], // honeypot — humans leave it empty
        ]);

        $normalized = $phones->normalize($data['phone']);

        if (! $normalized || strlen(preg_replace('/\D/', '', $normalized)) < 9) {
            return back()->withInput()->withErrors(['phone' => 'Please enter a valid phone number, for example 0712 345 678.']);
        }

        unset($data['website'], $data['consent']);

        $accountRequest = AccountRequest::create(array_merge($data, [
            'phone' => $normalized,
            'language' => $this->lang($request) === 'sw' ? 'sw' : 'en',
            'status' => 'new',
        ]));

        $this->notifyAccountRequest($accountRequest);

        return redirect()->route('account-request', ['lang' => $request->query('lang')])->with('sent', 'received');
    }

    private function notifyAccountRequest(AccountRequest $r): void
    {
        try {
            $body = "New account request #{$r->id}\nName: {$r->name}\nPhone: {$r->phone}\nEmail: {$r->email}\nEvent: {$r->event_type}"
                .($r->event_date ? ' on '.$r->event_date->format('j M Y') : '')."\nPlace: {$r->location}\nGuests: {$r->guests}\nNeeds: {$r->needs}\n\n{$r->message}\n\nNext: contact them, then create their account in User Management.";
            // Goes to the company inbox (info@fanikisha.app unless COMPANY_EMAIL is changed).
            // Reply-To lets you answer the person straight from the mail.
            Mail::raw($body, function ($m) use ($r) {
                $m->to(config('company.email'))->subject("Fanikisha account request #{$r->id}: {$r->name}");
                if (filled($r->email)) {
                    $m->replyTo($r->email, $r->name);
                }
            });

            if (filled($r->email)) {
                $sw = $r->language === 'sw';
                $text = $sw
                    ? "Habari {$r->name},\n\nTumepokea ombi lako la akaunti ya Fanikisha. Tutawasiliana nawe kwa simu au barua pepe ndani ya siku 2 za kazi.\n\n— ".config('company.legal_name')
                    : "Hello {$r->name},\n\nWe received your request for a Fanikisha account. We will contact you by phone or email within 2 working days.\n\n— ".config('company.legal_name');
                Mail::raw($text, fn ($m) => $m->to($r->email)->subject($sw ? 'Fanikisha: tumepokea ombi lako' : 'Fanikisha: we received your request'));
            }
        } catch (Throwable $e) {
            // The request is stored; a mail failure must not lose it or show the person an error.
            Log::warning('Account request email failed', ['id' => $r->id, 'message' => $e->getMessage()]);
        }
    }

    private function notifyDpo(DataRequest $r): void
    {
        try {
            $body = "New data request #{$r->id} ({$r->type})\nName: {$r->name}\nPhone: {$r->phone}\nEmail: {$r->email}\n\n{$r->details}\n\nAcknowledge within 2 working days; complete within 30 days.";
            Mail::raw($body, fn ($m) => $m->to(config('company.email'))->subject("Fanikisha data request #{$r->id}: {$r->type}"));
        } catch (Throwable $e) {
            // The request is stored; a mail failure must not lose it or show the person an error.
            Log::warning('Data request email failed', ['id' => $r->id, 'message' => $e->getMessage()]);
        }
    }

    private function legal(Request $request, string $file, string $title, string $titleSw, string $description)
    {
        $c = config('company');
        $sw = $this->lang($request) === 'sw';
        $path = resource_path("legal/{$file}.md");

        $phone = $c['phone'];
        $replace = [
            '%LEGAL_NAME%' => $c['legal_name'],
            '%ADDRESS%' => $c['address'],
            '%EMAIL%' => $c['email'],
            '%REG_LINE%' => $c['registration'] ? ' (registration no. '.$c['registration'].')' : '',
            '%PHONE_LINE%' => $phone ? ' · Phone: '.$phone : '',
            '%PHONE_SUPPORT%' => $phone ? ' and by phone on '.$phone : '',
            '%PDPC_LINE%' => ($c['pdpc_certificate'] ?? null) ? 'We are registered with the Personal Data Protection Commission under certificate no. '.$c['pdpc_certificate'].'.' : '',
        ];

        $html = Str::markdown(strtr(file_get_contents($path), $replace), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return view('public.legal', [
            'lang' => $sw ? 'sw' : 'en',
            'title' => $sw ? $titleSw : $title,
            'description' => $description,
            'html' => $html,
        ]);
    }

    private function lang(Request $request): string
    {
        return $request->query('lang') === 'sw' ? 'sw' : 'en';
    }
}
