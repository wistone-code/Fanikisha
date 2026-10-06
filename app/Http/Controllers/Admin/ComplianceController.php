<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataRequest;
use App\Models\MessageOptOut;
use App\Services\ActivityLogger;
use App\Services\OptOutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * System Admin's privacy-compliance desk: public data requests and the "do not message" list.
 * Holds phone numbers and names given to us by the public — never any event's guest, pledge or
 * provider content, so the System Admin's "no event data" boundary is unchanged.
 */
class ComplianceController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'blocked' ? 'blocked' : 'requests';
        $status = in_array($request->query('status'), ['new', 'acknowledged', 'closed', 'open'], true) ? $request->query('status') : 'open';
        $search = trim((string) $request->query('q'));

        $requests = DataRequest::query()
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['new', 'acknowledged']))
            ->when(in_array($status, ['new', 'acknowledged', 'closed'], true), fn ($q) => $q->where('status', $status))
            ->when($search !== '' && $tab === 'requests', fn ($q) => $q->where(fn ($w) => $w->where('phone', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderByRaw("CASE status WHEN 'new' THEN 0 WHEN 'acknowledged' THEN 1 ELSE 2 END")
            ->latest('id')
            ->limit(200)
            ->get();

        $blocked = MessageOptOut::query()
            ->when($search !== '' && $tab === 'blocked', fn ($q) => $q->where('phone', 'like', '%'.preg_replace('/\D/', '', $search).'%'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.compliance.index', [
            'tab' => $tab,
            'status' => $status,
            'search' => $search,
            'requests' => $requests,
            'blocked' => $blocked,
            'openCount' => DataRequest::whereIn('status', ['new', 'acknowledged'])->count(),
            'blockedCount' => MessageOptOut::count(),
        ]);
    }

    public function addBlocked(Request $request, OptOutService $optOuts): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);

        if (! $optOuts->add($data['phone'], 'admin')) {
            return back()->withErrors(['phone' => 'That does not look like a valid phone number.'])->withInput();
        }

        ActivityLogger::log('compliance.optout_added', 'Added a number to the do-not-message list.');

        return redirect()->route('admin.compliance', ['tab' => 'blocked'])->with('status', 'Number added. It will no longer receive any message from Fanikisha.');
    }

    public function removeBlocked(MessageOptOut $optout): RedirectResponse
    {
        $optout->delete();

        ActivityLogger::log('compliance.optout_removed', 'Removed a number from the do-not-message list (the person asked to be messaged again).');

        return redirect()->route('admin.compliance', ['tab' => 'blocked'])->with('status', 'Number removed. Only do this when the person themselves asked to receive messages again.');
    }

    public function updateRequest(Request $request, DataRequest $dataRequest): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:acknowledge,close,reopen'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['action'] === 'acknowledge') {
            $dataRequest->update(['status' => 'acknowledged', 'acknowledged_at' => $dataRequest->acknowledged_at ?? now()]);
            $this->emailRequester($dataRequest, 'We received your request', "Thank you. We received your request ({$dataRequest->type}) and will reply within 30 days of {$dataRequest->created_at->format('j M Y')}.");
            $message = 'Marked as acknowledged'.($dataRequest->email ? ' and the requester was emailed.' : '.');
        } elseif ($data['action'] === 'close') {
            $dataRequest->update(['status' => 'closed', 'closed_at' => now(), 'acknowledged_at' => $dataRequest->acknowledged_at ?? now(), 'note' => $data['note'] ?? $dataRequest->note]);
            $this->emailRequester($dataRequest, 'Your request is complete', "Your request ({$dataRequest->type}) is complete.".(filled($data['note'] ?? null) ? "\n\n".$data['note'] : '')."\n\nIf you are not satisfied you may complain to the Personal Data Protection Commission of Tanzania.");
            $message = 'Request closed'.($dataRequest->email ? ' and the requester was emailed.' : '.');
        } else {
            $dataRequest->update(['status' => 'acknowledged', 'closed_at' => null]);
            $message = 'Request reopened.';
        }

        ActivityLogger::log('compliance.request_'.$data['action'], "Data request #{$dataRequest->id} ({$dataRequest->type}): {$data['action']}.");

        return back()->with('status', $message);
    }

    private function emailRequester(DataRequest $r, string $subject, string $text): void
    {
        if (blank($r->email)) {
            return;
        }

        try {
            $footer = "\n\n— ".config('company.legal_name')."\nThis message comes from a no-reply address; to reach us write to ".config('company.email').'.';
            Mail::raw($text.$footer, fn ($m) => $m->to($r->email)->subject("Fanikisha: {$subject}"));
        } catch (Throwable $e) {
            Log::warning('Data request email to requester failed', ['id' => $r->id, 'message' => $e->getMessage()]);
        }
    }
}
