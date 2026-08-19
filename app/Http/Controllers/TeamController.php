<?php

namespace App\Http\Controllers;

use App\Models\EventMember;
use App\Models\User;
use App\Services\PasswordGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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

    public function store(Request $request): RedirectResponse
    {
        $event = app('currentEvent');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            // Mandatory when the role being granted is Admin; optional for Viewer.
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email', 'required_if:role,admin'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()],
            'role' => ['required', 'in:admin,viewer'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'is_super_user' => false,
            'must_change_password' => true,
            'created_by' => $request->user()->id,
        ]);

        EventMember::create(['event_id' => $event->id, 'user_id' => $user->id, 'role' => $data['role']]);

        return back()->with('status', 'Member added');
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

    /** Admin resetting SOMEONE ELSE's password — generates a random temp password. */
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
