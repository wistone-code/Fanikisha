<?php

namespace Tests\Feature;

use App\Models\EventMember;
use App\Models\Pledge;
use App\Models\SeatingTable;
use App\Services\CardCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class CheckinUpgradesTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    public function test_every_new_guest_gets_a_unique_card_code_per_event(): void
    {
        [$event] = $this->ecardEvent();
        $codes = collect(range(1, 40))->map(fn () => $this->guestCard($event)->card_code);

        $this->assertCount(40, $codes->unique());
        $codes->each(fn ($c) => $this->assertMatchesRegularExpression('/^[A-Z0-9]{5}$/', $c));
        $this->assertStringNotContainsString('O', $codes->implode(''));
    }

    public function test_code_normalisation_ignores_case_spaces_and_dashes(): void
    {
        $this->assertSame('K7M2Q', CardCodeService::normalize(' k7m-2q '));
    }

    public function test_check_in_by_typing_the_card_code(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event, ['name' => 'Neema']);

        $this->actingAs($admin)->postJson(route('checkin.verify'), ['code' => strtolower($g->card_code)])
            ->assertOk()->assertJsonPath('name', 'Neema')->assertJsonPath('already', false);

        $this->assertNotNull($g->fresh()->checked_in_at);
    }

    public function test_a_code_from_another_event_does_not_check_anyone_in(): void
    {
        [, $admin] = $this->ecardEvent();
        [$other] = $this->ecardEvent();
        $foreign = $this->guestCard($other);

        $this->actingAs($admin)->postJson(route('checkin.verify'), ['code' => $foreign->card_code])->assertNotFound();
        $this->assertNull($foreign->fresh()->checked_in_at);
    }

    public function test_search_finds_by_name_code_and_phone_digits(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event, ['name' => 'Zawadi Mushi', 'phone' => '255712349876']);
        $this->guestCard($event, ['name' => 'Someone Else', 'phone' => '255712340000']);

        foreach (['Zawadi', $g->card_code, '9876'] as $q) {
            $this->actingAs($admin)->getJson(route('checkin.search', ['q' => $q]))->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Zawadi Mushi');
        }
    }

    public function test_preview_does_not_check_the_guest_in(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event);

        $this->actingAs($admin)->postJson(route('checkin.verify'), ['token' => $g->invite_token, 'preview' => true])
            ->assertOk()->assertJsonPath('preview', true);

        $this->assertNull($g->fresh()->checked_in_at);
    }

    public function test_confirm_name_setting_can_be_switched_on_and_reaches_the_offline_list(): void
    {
        [$event, $admin] = $this->ecardEvent();

        $this->actingAs($admin)->patch(route('event.settings.checkin-confirm'), ['checkin_confirm_name' => 1])->assertRedirect();

        $this->actingAs($admin)->getJson(route('checkin.guest-list'))->assertJsonPath('confirm_name', true);
    }

    public function test_door_list_prints_names_codes_and_seats(): void
    {
        [$event, $admin] = $this->ecardEvent(['seating_mode' => 'table']);
        $t = SeatingTable::create(['event_id' => $event->id, 'name' => 'Table 4', 'capacity' => 8]);
        $g = $this->guestCard($event, ['name' => 'Baraka', 'seating_table_id' => $t->id]);

        $this->actingAs($admin)->get(route('checkin.door-list'))
            ->assertOk()->assertSee('Baraka')->assertSee($g->card_code)->assertSee('Table 4');
    }

    public function test_result_includes_seat_only_when_published_and_party_size(): void
    {
        [$event, $admin] = $this->ecardEvent(['seating_mode' => 'table']);
        $t = SeatingTable::create(['event_id' => $event->id, 'name' => 'Table 9', 'capacity' => 8]);
        $g = $this->guestCard($event, ['seating_table_id' => $t->id, 'card_type' => 'double', 'rsvp_status' => 'attending', 'plus_ones' => 1]);

        $this->actingAs($admin)->postJson(route('checkin.verify'), ['token' => $g->invite_token, 'preview' => true])
            ->assertJsonPath('seat', null)->assertJsonPath('people', 3);

        $event->update(['seating_published' => true]);

        $this->actingAs($admin)->postJson(route('checkin.verify'), ['token' => $g->invite_token, 'preview' => true])
            ->assertJsonPath('seat', 'Table 9');
    }

    public function test_stats_endpoint_reports_people_and_who_checked_in(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $scanner = $this->memberOf($event, 'scanner');
        $g = $this->guestCard($event, ['name' => 'Rehema', 'card_type' => 'double']);
        $this->guestCard($event);

        $this->actingAs($scanner)->postJson(route('checkin.verify'), ['token' => $g->invite_token])->assertOk();

        $res = $this->actingAs($admin)->getJson(route('checkin.stats'))->assertOk();
        $res->assertJsonPath('checked_in', 1)->assertJsonPath('expected', 2)->assertJsonPath('people_in', 2);
        $res->assertJsonPath('recent.0.name', 'Rehema')->assertJsonPath('recent.0.by', $scanner->name);
        $res->assertJsonPath('per_scanner.0.count', 1);
    }

    // ---- Door staff (scanner role) --------------------------------------------------------

    public function test_scanner_can_check_in_but_not_reach_guest_data_or_settings(): void
    {
        [$event] = $this->ecardEvent();
        $scanner = $this->memberOf($event, 'scanner');
        $g = $this->guestCard($event);

        $this->actingAs($scanner)->get(route('checkin.index'))->assertOk();
        $this->actingAs($scanner)->postJson(route('checkin.verify'), ['token' => $g->invite_token])->assertOk();

        foreach (['guests.index', 'delivery.index', 'event.settings', 'team.index', 'seating.index', 'photos.index', 'design.index', 'after.index', 'checkin.door-list', 'guests.export'] as $name) {
            $this->actingAs($scanner)->get(route($name))->assertForbidden();
        }
    }

    public function test_scanner_cannot_undo_a_check_in(): void
    {
        [$event] = $this->ecardEvent();
        $scanner = $this->memberOf($event, 'scanner');
        $g = $this->guestCard($event, ['checked_in_at' => now()]);

        $this->actingAs($scanner)->delete(route('checkin.undo', $g))->assertForbidden();
        $this->assertNotNull($g->fresh()->checked_in_at);
    }

    public function test_scanner_home_redirects_to_check_in(): void
    {
        [$event] = $this->ecardEvent();
        $scanner = $this->memberOf($event, 'scanner');

        $this->actingAs($scanner)->get(route('dashboard'))->assertRedirect(route('checkin.index'));
    }

    public function test_disabled_member_is_signed_out(): void
    {
        [$event] = $this->ecardEvent();
        $scanner = $this->memberOf($event, 'scanner');
        EventMember::where('user_id', $scanner->id)->update(['disabled_at' => now()]);

        $this->actingAs($scanner)->get(route('checkin.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_can_add_a_scanner_and_toggle_disabled(): void
    {
        [$event, $admin] = $this->ecardEvent();

        $this->actingAs($admin)->post(route('team.store'), ['name' => 'Door Dan', 'username' => 'doordan', 'email' => 'dan@example.com', 'role' => 'scanner'])->assertRedirect();
        $member = EventMember::where('event_id', $event->id)->where('role', 'scanner')->firstOrFail();

        $this->actingAs($admin)->post(route('team.toggle-disabled', $member))->assertRedirect();
        $this->assertNotNull($member->fresh()->disabled_at);
        $this->actingAs($admin)->post(route('team.toggle-disabled', $member))->assertRedirect();
        $this->assertNull($member->fresh()->disabled_at);
    }

    public function test_owner_cannot_be_disabled(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $event->update(['created_by' => $admin->id]);
        $member = EventMember::where('user_id', $admin->id)->firstOrFail();

        $this->actingAs($admin)->post(route('team.toggle-disabled', $member))->assertForbidden();
    }

    public function test_pledges_pages_are_still_blocked_for_ecard_accounts(): void
    {
        [, $admin] = $this->ecardEvent();

        $this->actingAs($admin)->get(route('pledges.index'))->assertNotFound();
        $this->actingAs($admin)->get(route('financial.index'))->assertNotFound();
        $this->actingAs($admin)->get(route('delivery.index'))->assertOk();
        $this->actingAs($admin)->get(route('seating.index'))->assertOk();
        $this->actingAs($admin)->get(route('design.index'))->assertOk();
        $this->actingAs($admin)->get(route('after.index'))->assertOk();
        $this->actingAs($admin)->get(route('photos.index'))->assertOk();
        $this->actingAs($admin)->get(route('guests.index', ['tab' => 'rsvp']))->assertOk();
        $this->actingAs($admin)->get(route('checkin.index'))->assertOk();
    }
}
