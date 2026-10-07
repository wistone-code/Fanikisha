<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

/** The System Admin's log must show every account's activity, not just the accounts an admin created by hand. */
class ActivityLogCoverageTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function superUser(): User
    {
        return User::factory()->create(['is_super_user' => true]);
    }

    public function test_team_changes_made_by_an_event_admin_are_logged(): void
    {
        $event = Event::factory()->create(['event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);
        $admin = $this->memberOf($event, 'admin');

        $this->actingAs($admin)->post(route('team.store'), ['name' => 'Door Dan', 'username' => 'doordan', 'email' => 'dan@example.com', 'role' => 'scanner'])->assertRedirect();
        $dan = User::where('username', 'doordan')->firstOrFail();
        $member = EventMember::where('user_id', $dan->id)->firstOrFail();

        $this->actingAs($admin)->post(route('team.toggle-disabled', $member));
        $this->actingAs($admin)->post(route('team.toggle-disabled', $member));
        $this->actingAs($admin)->post(route('team.reset-password', $member));
        $this->actingAs($admin)->delete(route('team.destroy', $member));

        $actions = ActivityLog::where('target_user_id', $dan->id)->pluck('action')->all();
        foreach (['team.member_added', 'team.member_disabled', 'team.member_enabled', 'team.password_reset', 'team.member_removed'] as $a) {
            $this->assertContains($a, $actions, "{$a} was not logged");
        }
    }

    public function test_event_settings_changes_are_logged(): void
    {
        $event = Event::factory()->create(['event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);
        $admin = $this->memberOf($event, 'admin');

        $this->actingAs($admin)->patch(route('event.settings.update'), [
            'name' => 'Renamed', 'event_type' => 'Wedding', 'place' => 'Hall', 'event_date' => now()->addDays(30)->toDateString(), 'pledge_deadline' => now()->addDays(25)->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', ['action' => 'event.updated', 'actor_id' => $admin->id, 'event_id' => $event->id]);
    }

    public function test_failed_and_blocked_sign_ins_are_logged_against_the_account(): void
    {
        $user = User::factory()->create(['username' => 'maria', 'password' => Hash::make('right-pass-1')]);

        $this->post(route('login.attempt'), ['username' => 'maria', 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.login_failed', 'target_user_id' => $user->id, 'actor_id' => null]);

        $user->forceFill(['is_suspended' => true])->save();
        $this->post(route('login.attempt'), ['username' => 'maria', 'password' => 'right-pass-1'])->assertSessionHasErrors('username');
        $this->assertSame(2, ActivityLog::where('action', 'account.login_failed')->where('target_user_id', $user->id)->count());

        $this->actingAs($this->superUser())->get(route('admin.logs.index'))->assertOk()->assertSee('Failed sign-in for')->assertSee('not signed in');
    }

    public function test_a_suspended_or_disabled_account_that_gets_signed_out_is_logged(): void
    {
        $event = Event::factory()->create(['event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);
        $door = $this->memberOf($event, 'scanner');
        EventMember::where('user_id', $door->id)->update(['disabled_at' => now()]);

        $this->actingAs($door)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.logout', 'target_user_id' => $door->id]);

        $other = User::factory()->create(['is_suspended' => true]);
        $this->actingAs($other)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.logout', 'target_user_id' => $other->id]);
    }

    public function test_a_remembered_sign_in_is_logged_once_and_the_login_form_does_not_double_log(): void
    {
        $user = User::factory()->create();

        $this->get(route('login')); // any non-login request
        Events::dispatch(new Login('web', $user, true));
        $this->assertSame(1, ActivityLog::where('action', 'account.login')->where('target_user_id', $user->id)->count());
        $this->assertStringContainsString('remembered device', ActivityLog::latest('id')->first()->description);

        $this->post(route('login.attempt'), ['username' => $user->username, 'password' => 'password', 'remember' => '1']);
        $this->assertLessThanOrEqual(2, ActivityLog::where('action', 'account.login')->where('target_user_id', $user->id)->count());
    }

    public function test_filtering_by_an_account_shows_what_it_did_as_well_as_what_was_done_to_it(): void
    {
        $event = Event::factory()->create(['event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString(), 'name' => 'Filter Fest']);
        $admin = $this->memberOf($event, 'admin');
        ActivityLog::create(['actor_id' => $admin->id, 'target_user_id' => null, 'event_id' => $event->id, 'action' => 'event.created', 'description' => 'Admin did a thing for Filter Fest', 'created_at' => now()]);
        ActivityLog::create(['actor_id' => null, 'target_user_id' => User::factory()->create()->id, 'action' => 'account.login', 'description' => 'Somebody else logged in', 'created_at' => now()]);

        $this->actingAs($this->superUser())->get(route('admin.logs.index', ['user' => $admin->id]))->assertOk()
            ->assertSee('Admin did a thing for Filter Fest')->assertDontSee('Somebody else logged in');
    }
}
