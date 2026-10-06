<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventMember;
use App\Models\Pledge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckinSyncTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(Event $event, string $role): User
    {
        $user = User::factory()->create();
        EventMember::create(['event_id' => $event->id, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function eventWithAdmin(): array
    {
        $event = Event::factory()->create();
        $admin = $this->memberOf($event, 'admin');

        return [$event, $admin];
    }

    private function guest(Event $event, string $token, array $extra = []): Pledge
    {
        return Pledge::factory()->create(array_merge(['event_id' => $event->id, 'invite_token' => $token], $extra));
    }

    // ---- Guest list for the offline scanner ---------------------------------------------

    public function test_guest_list_has_only_active_cards_with_minimal_fields(): void
    {
        [$event, $admin] = $this->eventWithAdmin();
        $this->guest($event, 'TOKEN-A', ['name' => 'Amani', 'phone' => '255712345678']);
        Pledge::factory()->create(['event_id' => $event->id, 'invite_token' => null, 'name' => 'Not activated']);

        $response = $this->actingAs($admin)->getJson(route('checkin.guest-list'))->assertOk();

        $response->assertJsonPath('event_id', $event->id);
        $response->assertJsonCount(1, 'guests');
        $response->assertJsonPath('guests.0.name', 'Amani');
        $response->assertJsonPath('guests.0.token', 'TOKEN-A');
        $response->assertJsonPath('guests.0.phone_last', '5678');
        $this->assertNotEmpty($response->json('csrf'));
        // The full phone number must never be sent to the device.
        $this->assertStringNotContainsString('255712345678', $response->getContent());
    }

    public function test_guest_list_never_includes_another_events_guests(): void
    {
        [$event, $admin] = $this->eventWithAdmin();
        $other = Event::factory()->create();
        $this->guest($other, 'OTHER-TOKEN', ['name' => 'Outsider']);

        $this->actingAs($admin)->getJson(route('checkin.guest-list'))
            ->assertOk()
            ->assertJsonCount(0, 'guests');
    }

    // ---- Batch sync -----------------------------------------------------------------------

    public function test_sync_checks_guests_in_and_records_who_and_when(): void
    {
        [$event, $admin] = $this->eventWithAdmin();
        $pledge = $this->guest($event, 'TOKEN-1');

        $this->actingAs($admin)->postJson(route('checkin.sync'), [
            'scans' => [['token' => 'TOKEN-1', 'scanned_at' => '2026-01-01T09:30:00Z']],
        ])->assertOk()
            ->assertJsonPath('results.0.status', 'checked_in')
            ->assertJsonPath('results.0.name', $pledge->name);

        $fresh = $pledge->fresh();
        $this->assertSame('09:30', $fresh->checked_in_at->clone()->utc()->format('H:i'));
        $this->assertSame($admin->id, (int) $fresh->checked_in_by);
    }

    public function test_first_check_in_wins_and_the_earlier_time_is_kept(): void
    {
        [$event, $admin] = $this->eventWithAdmin();
        $pledge = $this->guest($event, 'TOKEN-1', ['checked_in_at' => '2026-01-01 08:00:00', 'checked_in_by' => 999]);

        $this->actingAs($admin)->postJson(route('checkin.sync'), [
            'scans' => [['token' => 'TOKEN-1', 'scanned_at' => '2026-01-01T09:30:00Z']],
        ])->assertOk()
            ->assertJsonPath('results.0.status', 'already')
            ->assertJsonPath('results.0.name', $pledge->name);

        $fresh = $pledge->fresh();
        $this->assertSame('2026-01-01 08:00:00', $fresh->checked_in_at->format('Y-m-d H:i:s'));
        $this->assertSame(999, (int) $fresh->checked_in_by);
    }

    public function test_unknown_tokens_and_other_events_tokens_are_reported_not_applied(): void
    {
        [$event, $admin] = $this->eventWithAdmin();
        $other = Event::factory()->create();
        $outsider = $this->guest($other, 'OTHER-TOKEN');

        $response = $this->actingAs($admin)->postJson(route('checkin.sync'), [
            'scans' => [
                ['token' => 'NOPE', 'scanned_at' => null],
                ['token' => 'https://fanikisha.app/rsvp/OTHER-TOKEN', 'scanned_at' => null],
            ],
        ])->assertOk();

        $response->assertJsonPath('results.0.status', 'unknown');
        $response->assertJsonPath('results.1.status', 'unknown');
        $this->assertNull($outsider->fresh()->checked_in_at);
    }

    public function test_a_full_card_link_is_accepted_and_a_token_repeated_in_one_batch_counts_once(): void
    {
        [$event, $admin] = $this->eventWithAdmin();
        $this->guest($event, 'TOKEN-1');

        $response = $this->actingAs($admin)->postJson(route('checkin.sync'), [
            'scans' => [
                ['token' => 'https://fanikisha.app/rsvp/TOKEN-1', 'scanned_at' => null],
                ['token' => 'TOKEN-1', 'scanned_at' => null],
            ],
        ])->assertOk();

        $response->assertJsonCount(1, 'results');
        $response->assertJsonPath('results.0.status', 'checked_in');
    }

    public function test_a_scan_time_in_the_future_is_clamped_to_now(): void
    {
        [$event, $admin] = $this->eventWithAdmin();
        $pledge = $this->guest($event, 'TOKEN-1');

        $this->actingAs($admin)->postJson(route('checkin.sync'), [
            'scans' => [['token' => 'TOKEN-1', 'scanned_at' => now()->addDay()->toIso8601String()]],
        ])->assertOk();

        $this->assertTrue($pledge->fresh()->checked_in_at->lte(now()->addMinute()));
    }

    public function test_sync_validates_its_input(): void
    {
        [$event, $admin] = $this->eventWithAdmin();

        $this->actingAs($admin)->postJson(route('checkin.sync'), ['scans' => []])->assertStatus(422);
        $this->actingAs($admin)->postJson(route('checkin.sync'), [])->assertStatus(422);

        $tooMany = array_fill(0, 501, ['token' => 'X', 'scanned_at' => null]);
        $this->actingAs($admin)->postJson(route('checkin.sync'), ['scans' => $tooMany])->assertStatus(422);
    }

    // ---- Who may use it -------------------------------------------------------------------

    public function test_viewers_cannot_download_the_list_or_sync(): void
    {
        $event = Event::factory()->create();
        $viewer = $this->memberOf($event, 'viewer');
        $this->guest($event, 'TOKEN-1');

        $this->actingAs($viewer)->getJson(route('checkin.guest-list'))->assertForbidden();
        $this->actingAs($viewer)->getJson(route('checkin.token'))->assertForbidden();
        $this->actingAs($viewer)->postJson(route('checkin.sync'), [
            'scans' => [['token' => 'TOKEN-1', 'scanned_at' => null]],
        ])->assertForbidden();
    }

    public function test_logged_out_visitors_cannot_use_the_offline_endpoints(): void
    {
        $this->getJson(route('checkin.guest-list'))->assertUnauthorized();
        $this->getJson(route('checkin.token'))->assertUnauthorized();
        $this->postJson(route('checkin.sync'), ['scans' => [['token' => 'X']]])->assertUnauthorized();
    }

    // ---- Live scans: a second scan must not change the first check-in ---------------------

    public function test_scanning_the_same_card_twice_reports_already_checked_in_and_keeps_the_first_time(): void
    {
        [$event, $admin] = $this->eventWithAdmin();
        $pledge = $this->guest($event, 'TOKEN-1');

        $first = $this->actingAs($admin)->postJson(route('checkin.verify'), ['token' => 'TOKEN-1'])->assertOk();
        $first->assertJsonPath('found', true)->assertJsonPath('already', false);
        $firstTime = $pledge->fresh()->checked_in_at;
        $this->assertSame($admin->id, (int) $pledge->fresh()->checked_in_by);

        $this->travel(5)->minutes();

        $second = $this->actingAs($admin)->postJson(route('checkin.verify'), ['token' => 'TOKEN-1'])->assertOk();
        $second->assertJsonPath('found', true)->assertJsonPath('already', true);
        $this->assertEquals($firstTime, $pledge->fresh()->checked_in_at);
    }
}
