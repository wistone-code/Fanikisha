<?php

namespace App\Http\Controllers;

use App\Models\EventMember;
use App\Models\User;
use App\Services\AccountMailer;
use App\Services\ActivityLogger;
use App\Services\AccountUniquenessService;
use App\Services\PhoneNumberService;
use App\Services\PasswordGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        $event = app('currentEvent');

        $scans = $event->pledges()->whereNotNull('checked_in_by')->selectRaw('checked_in_by, COUNT(*) as n')->groupBy('checked_in_by')->pluck('n', 'checked_in_by');

        return view('event.team.index', [
            'event' => $event,
            'scans' => $scans,
            'members' => $event->members()->with('user')->get(),
        ]);
    }

    public function store(Request $request, PasswordGeneratorService $passwords, AccountMailer $mailer, AccountUniquenessService $unique, PhoneNumberService $phones): RedirectResponse
    {
        $event = app('currentEvent');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'in:admin,viewer,scanner'],
        ]);

        if ($warnings = $unique->conflicts($data['username'], $data['email'], $data['phone'] ?? null)) {
            return back()->withInput()->with('warning', $warnings);
        }

        $plainPassword = $passwords->generate();

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $phones->normalize($data['phone'] ?? null),
            'password' => Hash::make($plainPassword),
            'is_super_user' => false,
            'must_change_password' => true,
            'created_by' => $request->user()->id,
        ]);

        EventMember::create(['event_id' => $event->id, 'user_id' => $user->id, 'role' => $data['role']]);

        ActivityLogger::log('team.member_added', "{$request->user()->name} added {$user->name} ({$user->username}) to \"{$event->name}\" as {$data['role']}", $user, $event);

        $emailed = $mailer->sendWelcome($user, $plainPassword);

        return back()->with([
            'status' => $emailed ? "Member added — login details emailed to {$user->email}" : 'Member added, but the email could not be sent — share the details manually',
            'reveal_credentials' => ['name' => $user->name, 'username' => $user->username, 'password' => $plainPassword],
        ]);
    }

    public function destroy(EventMember $member): RedirectResponse
    {
        $event = app('currentEvent');
        abort_unless($member->event_id === $event->id, 404);

        if ($member->isOwner()) {
            abort(403, "The event owner can't be removed.");
        }

        $removed = $member->user;
        $member->delete();

        ActivityLogger::log('team.member_removed', ($removed?->name ?? 'A member')." ({$removed?->username}) was removed from \"{$event->name}\"", $removed, $event);

        return back()->with('status', 'Member removed');
    }

    /** Switch a member off (or back on) without deleting them — handy for door staff once the event is over. */
    public function toggleDisabled(EventMember $member): RedirectResponse
    {
        abort_unless($member->event_id === app('currentEvent')->id, 404);

        if ($member->isOwner()) {
            abort(403, "The event owner can't be disabled.");
        }

        $member->update(['disabled_at' => $member->disabled_at ? null : now()]);

        $event = app('currentEvent');
        ActivityLogger::log($member->disabled_at ? 'team.member_disabled' : 'team.member_enabled', "{$member->user->name} ({$member->user->username}) was ".($member->disabled_at ? 'disabled' : 'enabled again')." on \"{$event->name}\"", $member->user, $event);

        return back()->with('status', $member->disabled_at ? "{$member->user->name} disabled — they can no longer sign in" : "{$member->user->name} enabled again");
    }

    public function resetPassword(EventMember $member, PasswordGeneratorService $passwords): RedirectResponse
    {
        $event = app('currentEvent');
        abort_unless($member->event_id === $event->id, 404);

        $plainPassword = $passwords->generate();

        $member->user->forceFill([
            'password' => Hash::make($plainPassword),
            'must_change_password' => true,
        ])->save();

        ActivityLogger::log('team.password_reset', "Reset password for team member {$member->user->name} ({$member->user->username}) on \"{$event->name}\"", $member->user, $event);

        return back()->with([
            'status' => 'Password reset',
            'reveal_credentials' => ['name' => $member->user->name, 'username' => $member->user->username, 'password' => $plainPassword],
        ]);
    }
}