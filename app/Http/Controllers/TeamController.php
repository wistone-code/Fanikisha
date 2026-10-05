<?php

namespace App\Http\Controllers;

use App\Models\EventMember;
use App\Models\User;
use App\Services\AccountMailer;
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

        return view('event.team.index', [
            'event' => $event,
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
            'role' => ['required', 'in:admin,viewer'],
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

        $member->delete();

        return back()->with('status', 'Member removed');
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

        return back()->with([
            'status' => 'Password reset',
            'reveal_credentials' => ['name' => $member->user->name, 'username' => $member->user->username, 'password' => $plainPassword],
        ]);
    }
}