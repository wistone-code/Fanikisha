<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The system admin's dashboard: key numbers, packages, upcoming events, SMS leaders, recent activity and the accounts table. */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_super_user' => true]);
    }

    private function account(array $attrs = [], ?array $event = null, string $role = 'admin'): User
    {
        $user = User::factory()->create(array_merge(['is_super_user' => false], $attrs));

        if ($event !== null) {
            $ev = Event::factory()->create(array_merge(['created_by' => $user->id], $event));
            EventMember::create(['event_id' => $ev->id, 'user_id' => $user->id, 'role' => $role]);
        }

        return $user;
    }

    public function test_dashboard_shows_the_key_numbers_packages_and_lists(): void
    {
        $this->account(['name' => 'Alpha Host', 'package' => 'full'], ['name' => 'Alpha Wedding', 'event_date' => now()->addDays(10)->toDateString(), 'sms_quota' => 100, 'sms_sent_count' => 100]);
        $this->account(['name' => 'Beta Host', 'package' => 'sms'], ['name' => 'Beta Send-off', 'event_date' => now()->addDays(90)->toDateString(), 'sms_quota' => 1000, 'sms_sent_count' => 50]);
        $this->account(['name' => 'Gamma Host', 'package' => 'ecard', 'is_suspended' => true]);
        $this->account(['name' => 'Old Event Host'], ['name' => 'Past Wedding', 'event_date' => now()->subDays(30)->toDateString(), 'sms_sent_count' => 5]);
        ActivityLog::create(['actor_id' => null, 'action' => 'account.created', 'description' => 'Created account for Delta', 'created_at' => now()]);

        $res = $this->actingAs($this->admin())->get(route('admin.users.index'))->assertOk();

        $res->assertViewHas('totalAccounts', 4)
            ->assertViewHas('eventCount', 3)
            ->assertViewHas('upcomingCount', 2)
            ->assertViewHas('soonCount', 1)
            ->assertViewHas('suspendedCount', 1)
            ->assertViewHas('atQuotaCount', 1)
            ->assertViewHas('noEventCount', 1)
            ->assertViewHas('totalSmsSent', 155)
            ->assertViewHas('packageCounts', fn ($c) => $c['full'] === 2 && $c['sms'] === 1 && $c['ecard'] === 1)
            ->assertViewHas('quotaUsedPct', 14); // (100 + 50) of (100 + 1000) quota

        $res->assertSee('Dashboard')->assertSee('Upcoming events')->assertSee('Most SMS used')->assertSee('Recent activity')
            ->assertSee('Alpha Wedding')->assertSee('Created account for Delta')->assertSee('Needs attention');
    }

    public function test_upcoming_events_are_soonest_first_and_exclude_past_ones(): void
    {
        $this->account([], ['name' => 'Later Event', 'event_date' => now()->addDays(60)->toDateString()]);
        $this->account([], ['name' => 'Sooner Event', 'event_date' => now()->addDays(5)->toDateString()]);
        $this->account([], ['name' => 'Gone Event', 'event_date' => now()->subDays(5)->toDateString()]);

        $upcoming = $this->actingAs($this->admin())->get(route('admin.users.index'))->viewData('upcomingEvents');

        $this->assertSame(['Sooner Event', 'Later Event'], $upcoming->pluck('name')->all());
    }

    public function test_accounts_table_shows_name_email_package_and_quota_and_filters_still_work(): void
    {
        $this->account(['name' => 'Neema Host', 'username' => 'neema1', 'email' => 'neema@example.com', 'package' => 'ecard'], ['name' => 'Neema Wedding', 'sms_quota' => 10, 'sms_sent_count' => 10]);
        $this->account(['name' => 'Quiet Person', 'username' => 'quiet1', 'email' => 'quiet@example.com']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('Neema Host')->assertSee('neema1')->assertSee('neema@example.com')->assertSee('Quiet Person');

        // (names also appear in the "reassign event" pickers, so check the rows the table was given)
        $rows = fn (array $q) => $this->actingAs($admin)->get(route('admin.users.index', $q))->assertOk()->viewData('accounts')->pluck('name')->all();
        $this->assertSame(['Neema Host'], $rows(['status' => 'attention']));
        $this->assertSame(['Quiet Person'], $rows(['status' => 'no_event']));
        $this->assertSame(['Neema Host'], $rows(['q' => 'neema@example']));
    }

    public function test_an_empty_platform_still_renders(): void
    {
        $this->actingAs($this->admin())->get(route('admin.users.index'))->assertOk()
            ->assertSee('No upcoming events')->assertSee('No SMS sent yet')->assertSee('No activity recorded yet');
    }

    public function test_the_dashboard_is_closed_to_ordinary_accounts(): void
    {
        $this->actingAs($this->account())->get(route('admin.users.index'))->assertForbidden();
    }
}
