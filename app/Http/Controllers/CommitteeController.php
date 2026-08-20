<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventOwnership;
use App\Models\Committee;
use App\Models\CommitteeMember;
use App\Services\BeemSmsService;
use App\Services\MessageTemplateService;
use App\Services\PhoneNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommitteeController extends Controller
{
    use AuthorizesEventOwnership;

    public function index(Request $request): View
    {
        $event = app('currentEvent');

        return view('event.committees.index', [
            'event' => $event,
            'committees' => $event->committees()->with('members.pledge')->latest()->get(),
            'pledges' => $event->pledges,
            'isAdmin' => $request->user()->isAdminOn($event),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $committee = app('currentEvent')->committees()->create(['name' => $data['name']]);

        foreach ($data['members'] ?? [] as $member) {
            $committee->members()->create($member);
        }

        return back()->with('status', 'Committee created');
    }

    public function update(Request $request, Committee $committee): RedirectResponse
    {
        $this->assertCommitteeInCurrentEvent($committee);

        $data = $request->validate($this->rules());

        $committee->update(['name' => $data['name']]);
        $committee->members()->delete();
        foreach ($data['members'] ?? [] as $member) {
            $committee->members()->create($member);
        }

        return back()->with('status', 'Committee updated');
    }

    public function destroy(Committee $committee): RedirectResponse
    {
        $this->assertCommitteeInCurrentEvent($committee);

        $committee->delete();

        return back()->with('status', 'Committee deleted');
    }

    public function updateMember(Request $request, CommitteeMember $member): RedirectResponse
    {
        $this->assertCommitteeMemberInCurrentEvent($member);

        $data = $request->validate([
            'pledge_id' => ['required', $this->pledgeExistsInCurrentEventRule()],
            'title' => ['required', 'string', 'max:255'],
        ]);

        $member->update($data);

        return back()->with('status', 'Member updated');
    }

    public function destroyMember(CommitteeMember $member): RedirectResponse
    {
        $this->assertCommitteeMemberInCurrentEvent($member);

        $member->delete();

        return back()->with('status', 'Member removed');
    }

    public function updateMessage(Request $request): RedirectResponse
    {
        $data = $request->validate(['committee_message' => ['required', 'string', 'max:5000']]);
        app('currentEvent')->update(['committee_message' => $data['committee_message']]);

        return back()->with('status', 'Notification message saved');
    }

    /** Sends the notification directly via Beem SMS instead of opening the phone's Messages app. */
    public function notifySms(CommitteeMember $member, MessageTemplateService $messages, BeemSmsService $sms): RedirectResponse
    {
        $this->assertCommitteeMemberInCurrentEvent($member);

        $event = app('currentEvent');
        $body = $messages->forCommittee($event, $member->pledge, $member->title, $member->committee->name);
        $result = $sms->sendSingle($body, $member->pledge->phone);

        return back()->with('status', $result['successful']
            ? "Notification sent to {$member->pledge->name}."
            : 'SMS send failed: '.($result['error'] ?? 'Unknown error'));
    }

    public function notifyWhatsApp(CommitteeMember $member, MessageTemplateService $messages, PhoneNumberService $phones): RedirectResponse
    {
        $this->assertCommitteeMemberInCurrentEvent($member);

        $event = app('currentEvent');
        $digits = $phones->digitsOnly($member->pledge->phone);
        $text = rawurlencode($messages->forCommittee($event, $member->pledge, $member->title, $member->committee->name));

        return redirect()->away("https://wa.me/{$digits}?text={$text}");
    }

    /**
     * Without this, `exists:pledges,id` alone would accept ANY pledge ID in the
     * whole database — letting a malicious admin link another event's pledger's
     * name into their own committee (a cross-tenant data leak, not just an IDOR).
     */
    private function pledgeExistsInCurrentEventRule()
    {
        return Rule::exists('pledges', 'id')->where('event_id', app('currentEvent')->id);
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'members' => ['array', 'max:200'],
            'members.*.pledge_id' => ['required', $this->pledgeExistsInCurrentEventRule()],
            'members.*.title' => ['required', 'string', 'max:255'],
        ];
    }
}
