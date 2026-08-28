<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventMember;
use App\Models\User;
use App\Services\PasswordGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /**
     * The System Admin's only screen. Deliberately has zero visibility into any
     * event's data — this query only ever touches `users` and `event_members.role`,
     * plus each event's name/type/date (identifying metadata, not content) and its
     * SMS quota/usage numbers (cost-control figures, not content).
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->get('q'));
        $status = $request->get('status', 'all'); // all | attention | no_event

        $base = User::query()->where('is_super_user', false);

        if ($search) {
            $base->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $atQuota = function ($q) {
            $q->whereNotNull('sms_quota')->whereColumn('sms_sent_count', '>=', 'sms_quota');
        };

        // Counted against the search-filtered set but BEFORE the status filter below,
        // so the KPI strip always reflects true totals regardless of which chip is
        // currently active — clicking "Needs attention" narrows the table, not the
        // numbers above it that tell you it's worth clicking.
        $totalAccounts = (clone $base)->count();
        $atQuotaCount = (clone $base)->whereHas('eventMemberships.event', $atQuota)->count();
        $noEventCount = (clone $base)->whereDoesntHave('eventMemberships')->count();

        if ($status === 'attention') {
            $base->whereHas('eventMemberships.event', $atQuota);
        } elseif ($status === 'no_event') {
            $base->whereDoesntHave('eventMemberships');
        }

        $accounts = $base->with(['creator', 'eventMemberships.event'])
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(function (User $u) {
                $membership = $u->eventMemberships->first();
                $u->role_label = $membership
                    ? ($membership->role === 'admin' ? 'Admin' : 'Viewer')
                    : 'No event yet';
                $u->created_by_label = $u->creator
                    ? ($u->creator->is_super_user ? "{$u->creator->name} (System)" : $u->creator->name)
                    : '—';

                $event = $membership?->event;
                $u->event_id = $event?->id;
                $u->event_name = $event?->name;
                $u->event_type = $event?->event_type;
                $u->event_date = $event?->event_date;
                $u->sms_quota = $event?->sms_quota;
                $u->sms_sent_count = $event?->sms_sent_count;
                $u->at_quota = $event && $event->sms_quota !== null && $event->sms_sent_count >= $event->sms_quota;

                return $u;
            });

        return view('admin.users.index', compact(
            'accounts', 'search', 'status', 'totalAccounts', 'atQuotaCount', 'noEventCount'
        ));
    }

    public function store(Request $request, PasswordGeneratorService $passwords): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $plainPassword = $passwords->generate();

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($plainPassword),
            'is_super_user' => false,
            'must_change_password' => true,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.users.index')->with([
            'status' => 'Account created',
            'reveal_credentials' => ['name' => $user->name, 'username' => $user->username, 'password' => $plainPassword],
        ]);
    }

    public function updateEmail(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is_super_user, 404);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->update(['email' => $data['email']]);

        return back()->with('status', 'Email updated');
    }

    public function resetPassword(User $user, PasswordGeneratorService $passwords): RedirectResponse
    {
        abort_if($user->is_super_user, 404);

        $plainPassword = $passwords->generate();

        $user->forceFill([
            'password' => Hash::make($plainPassword),
            'must_change_password' => true,
        ])->save();

        return back()->with([
            'status' => 'Password reset',
            'reveal_credentials' => ['name' => $user->name, 'username' => $user->username, 'password' => $plainPassword],
        ]);
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->is_super_user, 404);

        // event_members rows cascade-delete via the FK, matching the prototype's
        // "removes their account and all event memberships" behaviour.
        $user->delete();

        return back()->with('status', 'Account deleted');
    }

    /** Sets (or clears) the SMS send cap for this account's event. Null = unlimited. */
    public function updateSmsQuota(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is_super_user, 404);

        $event = $user->currentEvent();
        abort_unless($event, 404, 'This account has no event yet.');

        $data = $request->validate([
            'sms_quota' => ['nullable', 'integer', 'min:0'],
        ]);

        $event->update(['sms_quota' => $data['sms_quota'] ?? null]);

        return back()->with('status', 'SMS quota updated');
    }

    /** The System Admin's own account settings — separate from the accounts they manage. */
    public function accountSettings(Request $request): View
    {
        return view('admin.account', ['account' => $request->user()]);
    }

    public function updateOwnEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->update(['email' => $data['email']]);

        return back()->with('status', 'Email updated');
    }
}
