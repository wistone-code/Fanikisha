<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\User;
use App\Services\AccountMailer;
use App\Services\AccountUniquenessService;
use App\Services\PhoneNumberService;
use App\Services\ActivityLogger;
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
        $status = $request->get('status', 'all'); // all | attention | no_event | locked | suspended

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
        $lockedCount = (clone $base)->whereNotNull('locked_at')->count();
        $suspendedCount = (clone $base)->where('is_suspended', true)->count();

        // Lifetime running total — there's no per-send log to break this down by
        // month, so this is deliberately labelled "all-time" in the view rather
        // than implying a monthly figure the data can't actually support.
        $totalSmsSent = (int) Event::sum('sms_sent_count');
        $estimatedSmsCost = $totalSmsSent * (float) config('services.beem.cost_per_sms');

        // Reused across every row's "Reassign event" modal — an account can only
        // receive an event if it doesn't already have one of its own (accounts are
        // capped at a single event, whether owner or team member).
        $eligibleTargets = User::where('is_super_user', false)
            ->whereDoesntHave('eventMemberships')
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        if ($status === 'attention') {
            $base->whereHas('eventMemberships.event', $atQuota);
        } elseif ($status === 'no_event') {
            $base->whereDoesntHave('eventMemberships');
        } elseif ($status === 'locked') {
            $base->whereNotNull('locked_at');
        } elseif ($status === 'suspended') {
            $base->where('is_suspended', true);
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
                $u->owns_event = (bool) ($event && $this->ownEvent($u));
                $u->has_cards = (bool) $event?->hasFeature('cards');
                $u->whatsapp_quota = $event?->whatsapp_quota;
                $u->whatsapp_sent_count = $event?->whatsapp_sent_count;
                $u->at_quota = $event && $event->sms_quota !== null && $event->sms_sent_count >= $event->sms_quota;

                return $u;
            });

        return view('admin.users.index', compact(
            'accounts', 'search', 'status', 'totalAccounts', 'atQuotaCount', 'noEventCount', 'lockedCount', 'suspendedCount', 'totalSmsSent', 'estimatedSmsCost', 'eligibleTargets'
        ) + $this->dashboard());
    }

    /**
     * Whole-platform overview for the admin dashboard. Only identifying metadata and cost-control numbers
     * (the same things the accounts table already shows) — never any event's guests, pledges or money.
     *
     * @return array<string, mixed>
     */
    private function dashboard(): array
    {
        $people = User::where('is_super_user', false);

        $packageCounts = [];
        foreach ((clone $people)->selectRaw("COALESCE(package, 'full') as pkg, COUNT(*) as n")->groupBy('pkg')->pluck('n', 'pkg') as $key => $n) {
            $packageCounts[$key] = (int) $n;
        }

        $today = now()->startOfDay();
        $quotaTotals = Event::whereNotNull('sms_quota')->selectRaw('COALESCE(SUM(sms_sent_count),0) as sent, COALESCE(SUM(sms_quota),0) as quota')->first();

        return [
            'packageCounts' => $packageCounts,
            'suspendedCount' => (clone $people)->where('is_suspended', true)->count(),
            'newAccounts' => (clone $people)->where('created_at', '>=', now()->subDays(30))->count(),
            'eventCount' => Event::count(),
            'upcomingCount' => Event::whereDate('event_date', '>=', $today)->count(),
            'soonCount' => Event::whereDate('event_date', '>=', $today)->whereDate('event_date', '<=', $today->copy()->addDays(30))->count(),
            'upcomingEvents' => Event::with('owner')->whereDate('event_date', '>=', $today)->orderBy('event_date')->limit(5)->get(),
            'topSms' => Event::with('owner')->where('sms_sent_count', '>', 0)->orderByDesc('sms_sent_count')->limit(5)->get(),
            'quotaUsedPct' => $quotaTotals && (int) $quotaTotals->quota > 0 ? (int) round($quotaTotals->sent / $quotaTotals->quota * 100) : null,
            'recentActivity' => \App\Models\ActivityLog::with(['actor', 'targetUser'])->latest('created_at')->limit(6)->get(),
        ];
    }

    public function store(Request $request, PasswordGeneratorService $passwords, AccountMailer $mailer, AccountUniquenessService $unique, PhoneNumberService $phones): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'package' => ['nullable', 'in:'.implode(',', array_keys(config('packages.packages')))],
        ]);

        if ($warnings = $unique->conflicts($data['username'], $data['email'], $data['phone'] ?? null)) {
            return back()->withInput()->with('warning', $warnings);
        }

        $data['package'] = $data['package'] ?? config('packages.default');
        $plainPassword = $passwords->generate();

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $phones->normalize($data['phone'] ?? null),
            'package' => $data['package'],
            'password' => Hash::make($plainPassword),
            'is_super_user' => false,
            'must_change_password' => true,
            'created_by' => $request->user()->id,
        ]);

        ActivityLogger::log('account.created', "Created account for {$user->name} ({$user->username}) — ".config("packages.packages.{$data['package']}.label"), $user);

        $emailed = $mailer->sendWelcome($user, $plainPassword);

        return redirect()->route('admin.users.index')->with([
            'status' => $emailed ? "Account created — login details emailed to {$user->email}" : 'Account created, but the email could not be sent — share the details manually',
            'reveal_credentials' => ['name' => $user->name, 'username' => $user->username, 'password' => $plainPassword],
        ]);
    }

    public function update(Request $request, User $user, AccountUniquenessService $unique, PhoneNumberService $phones): RedirectResponse
    {
        abort_if($user->is_super_user, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        if ($warnings = $unique->conflicts($data['username'], $data['email'], $data['phone'] ?? null, $user->id)) {
            return back()->with('warning', $warnings);
        }

        $data['phone'] = $phones->normalize($data['phone'] ?? null);

        $before = $user->only(['name', 'username', 'email', 'phone']);

        $user->update($data);

        $changes = array_filter($data, fn ($value, $key) => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH);
        if ($changes) {
            $summary = collect($changes)->map(fn ($v, $k) => "{$k}: \"{$before[$k]}\" → \"{$v}\"")->implode(', ');
            ActivityLogger::log('account.updated', "Updated account for {$user->name} — {$summary}", $user);
        }

        return back()->with('status', 'Account updated');
    }

    public function resetPassword(User $user, PasswordGeneratorService $passwords): RedirectResponse
    {
        abort_if($user->is_super_user, 404);

        $plainPassword = $passwords->generate();

        $user->forceFill([
            'password' => Hash::make($plainPassword),
            'must_change_password' => true,
        ])->save();
        \App\Support\SessionCleaner::endOthers($user);

        ActivityLogger::log('account.password_reset', "Reset password for {$user->name} ({$user->username})", $user);

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
        ActivityLogger::log('account.deleted', "Deleted account for {$user->name} ({$user->username})", $user);
        $user->delete();

        return back()->with('status', 'Account deleted');
    }

    /**
     * Pauses an account without deleting it — blocks login (see LoginController and
     * EnsureNotSuspended) while leaving the account and all its event data intact.
     */
    public function toggleSuspend(User $user): RedirectResponse
    {
        abort_if($user->is_super_user, 404);

        $user->update(['is_suspended' => ! $user->is_suspended]);

        $action = $user->is_suspended ? 'account.suspended' : 'account.reactivated';
        $verb = $user->is_suspended ? 'Suspended' : 'Reactivated';
        ActivityLogger::log($action, "{$verb} account for {$user->name} ({$user->username})", $user);

        return back()->with('status', "{$verb} account for {$user->name}");
    }

    /** Clears a lockout caused by too many wrong passwords, so the person can sign in again. */
    public function unlock(User $user): RedirectResponse
    {
        abort_if($user->is_super_user, 404);

        $user->forceFill(['locked_at' => null, 'failed_login_attempts' => 0])->save();

        ActivityLogger::log('account.unlocked', "Unlocked account for {$user->name} ({$user->username})", $user);

        return back()->with('status', "Unlocked {$user->name}'s account");
    }

    /**
     * Moves one account's single event membership to a different account — for
     * when someone loses access to their phone/account and needs a fresh login,
     * without recreating the event or losing any of its pledges/providers/schedule.
     * The target can be an existing account with no event of its own, or a brand
     * new account created on the spot (same temp-password flow as store()).
     */
    public function reassignEvent(Request $request, User $user, PasswordGeneratorService $passwords, AccountMailer $mailer, AccountUniquenessService $unique, PhoneNumberService $phones): RedirectResponse
    {
        abort_if($user->is_super_user, 404);

        $event = $user->currentEvent();
        abort_unless($event, 404, 'This account has no event to reassign.');

        $mode = $request->validate(['mode' => ['required', 'in:existing,new']])['mode'];
        $revealCredentials = null;
        $emailed = null;

        if ($mode === 'existing') {
            $data = $request->validate(['target_user_id' => ['required', 'exists:users,id']]);

            if ((int) $data['target_user_id'] === $user->id) {
                return back()->withErrors(['target_user_id' => 'Choose a different account.']);
            }

            $target = User::findOrFail($data['target_user_id']);
            abort_if($target->is_super_user, 422, 'Cannot assign an event to the System Admin.');

            if ($target->currentEvent()) {
                return back()->withErrors(['target_user_id' => "{$target->name} already has an event of their own."]);
            }
        } else {
            $data = $request->validate([
                'new_name' => ['required', 'string', 'max:255'],
                'new_username' => ['required', 'string', 'max:255'],
                'new_email' => ['required', 'email', 'max:255'],
                'new_phone' => ['nullable', 'string', 'max:20'],
            ]);

            if ($warnings = $unique->conflicts($data['new_username'], $data['new_email'], $data['new_phone'] ?? null)) {
                return back()->with('warning', $warnings);
            }

            $plainPassword = $passwords->generate();

            $target = User::create([
                'name' => $data['new_name'],
                'username' => $data['new_username'],
                'email' => $data['new_email'],
                'phone' => $phones->normalize($data['new_phone'] ?? null),
                'password' => Hash::make($plainPassword),
                'is_super_user' => false,
                'must_change_password' => true,
                'created_by' => $request->user()->id,
            ]);

            ActivityLogger::log('account.created', "Created account for {$target->name} ({$target->username})", $target);

            $revealCredentials = ['name' => $target->name, 'username' => $target->username, 'password' => $plainPassword];
            $emailed = $mailer->sendWelcome($target, $plainPassword);
        }

        $membership = EventMember::where('event_id', $event->id)->where('user_id', $user->id)->first();
        abort_unless($membership, 404);

        $membership->update(['user_id' => $target->id]);

        ActivityLogger::log(
            'event.reassigned',
            "Reassigned event \"{$event->name}\" from {$user->name} ({$user->username}) to {$target->name} ({$target->username})",
            $target,
            $event
        );

        return back()->with(array_filter([
            'status' => "Event reassigned to {$target->name}".($emailed === true ? ' — login details emailed' : ($emailed === false ? ' — email could not be sent, share the details manually' : '')),
            'reveal_credentials' => $revealCredentials,
        ]));
    }

    /** The event this account owns (not one it only helps with as a team member). */
    private function ownEvent(User $user): ?Event
    {
        $event = $user->currentEvent();

        if (! $event) {
            return null;
        }

        // Team members are created by the event's own owner (accounts are created by the System Admin), and their "current event" is the owner's.
        $creator = $user->created_by ? User::find($user->created_by) : null;
        $isTeamMember = $creator && ! $creator->is_super_user && $creator->eventMemberships()->where('event_id', $event->id)->exists();

        return $isTeamMember ? null : $event;
    }

    /** Moves an account between packages. Nothing is deleted — features are hidden or shown again. */
    public function updatePackage(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is_super_user, 404);

        // The package is set on the event's admin account; viewers and door staff simply follow it.
        $membership = $user->eventMemberships()->first();
        abort_if($membership && $membership->role !== 'admin', 403, 'Change the package on the event admin account — viewers and door staff follow it.');

        $data = $request->validate(['package' => ['required', 'in:'.implode(',', array_keys(config('packages.packages')))]]);
        $old = $user->package ?: config('packages.default');

        $user->update(['package' => $data['package']]);

        // The package belongs to the whole event: changing it on the owner or on any team admin updates the event,
        // and every account on it (admins, viewers, door staff) shows and gets the same package.
        // E-card is a different kind of event (no money side), so mode follows too.
        $event = $user->currentEvent();
        if ($event) {
            // E-card guests are plain guests. Moving to a money package must not turn them into pledgers with a zero pledge.
            if ($event->mode === Event::MODE_ECARD && $data['package'] !== 'ecard') {
                $event->pledges()->where('guest_only', false)->where('amount', 0)->update(['guest_only' => true]);
            }

            $event->update([
                'package' => $data['package'],
                'mode' => $data['package'] === 'ecard' ? Event::MODE_ECARD : Event::MODE_CONTRIBUTIONS,
            ]);

            User::where('is_super_user', false)
                ->where(fn ($q) => $q->whereIn('id', EventMember::where('event_id', $event->id)->select('user_id'))->orWhere('id', $event->created_by))
                ->update(['package' => $data['package']]);
        }

        ActivityLogger::log('account.package_changed', "Changed {$user->name}'s package from ".config("packages.packages.{$old}.label").' to '.config("packages.packages.{$data['package']}.label"), $user, $event);

        return back()->with('status', "{$user->name} is now on the ".config("packages.packages.{$data['package']}.label"));
    }

    /** Sets (or clears) the SMS send cap for this account's event. Null = unlimited. */
    public function updateSmsQuota(Request $request, User $user): RedirectResponse
    {
        if ($user->is_super_user) {
            return back()->with('error', 'System admin accounts have no quota.');
        }

        $event = $this->ownEvent($user);

        if (! $event) {
            return back()->with('error', "{$user->name} has no event of their own (a team member shares the host's event, or no event exists yet). Set the quota on the account that owns the event.");
        }

        $data = $request->validate([
            'sms_quota' => ['nullable', 'integer', 'min:0'],
            'whatsapp_quota' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        $event->update(['sms_quota' => $data['sms_quota'] ?? null]);

        ActivityLogger::log('account.sms_quota_updated', "Set SMS quota for {$user->name}'s event to ".($data['sms_quota'] ?? 'unlimited'), $user, $event);

        // WhatsApp invitations only exist for packages with e-cards; a blank field means 0 (no WhatsApp).
        if ($request->has('whatsapp_quota') && $event->hasFeature('cards')) {
            $event->update(['whatsapp_quota' => (int) ($data['whatsapp_quota'] ?? 0)]);

            ActivityLogger::log('account.whatsapp_quota_updated', "Set WhatsApp invitation quota for {$user->name}'s event to ".(int) ($data['whatsapp_quota'] ?? 0), $user, $event);
        }

        return back()->with('status', 'Quota updated');
    }

    /** Asks Meta whether the saved WhatsApp token and phone number work, and shows the exact answer. */
    public function checkWhatsApp(\App\Services\WhatsAppCloudService $whatsapp): RedirectResponse
    {
        return back()->with('whatsapp_check', $whatsapp->diagnose());
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
            'current_password' => ['required', 'current_password'],
        ]);

        $user->update(['email' => $data['email']]);

        return back()->with('status', 'Email updated');
    }
}
