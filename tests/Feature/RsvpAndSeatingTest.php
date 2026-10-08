<?php

namespace Tests\Feature;

use App\Models\SeatingTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class RsvpAndSeatingTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function respond($g, array $data)
    {
        return $this->post(route('guest.rsvp.respond', $g->invite_token), $data);
    }

    public function test_plain_rsvp_still_works(): void
    {
        [$event] = $this->ecardEvent();
        $g = $this->guestCard($event);

        $this->respond($g, ['response' => 'attending'])->assertRedirect();
        $this->assertSame('attending', $g->fresh()->rsvp_status);
        $this->assertNotNull($g->fresh()->rsvp_at);
    }

    public function test_plus_ones_are_capped_by_card_type(): void
    {
        [$event] = $this->ecardEvent(['rsvp_plus_ones_enabled' => true, 'rsvp_max_plus_single' => 1, 'rsvp_max_plus_double' => 3]);
        $single = $this->guestCard($event);
        $double = $this->guestCard($event, ['card_type' => 'double']);

        $this->respond($single, ['response' => 'attending', 'plus_ones' => 2])->assertSessionHasErrors('plus_ones');
        $this->respond($single, ['response' => 'attending', 'plus_ones' => 1]);
        $this->respond($double, ['response' => 'attending', 'plus_ones' => 3]);

        $this->assertSame(1, $single->fresh()->plus_ones);
        $this->assertSame(3, $double->fresh()->plus_ones);
        $this->assertSame(5, $double->fresh()->headcount()); // guest + partner + 3
    }

    public function test_declining_resets_extra_guests_and_counts_zero_people(): void
    {
        [$event] = $this->ecardEvent(['rsvp_plus_ones_enabled' => true]);
        $g = $this->guestCard($event, ['rsvp_status' => 'attending', 'plus_ones' => 1]);

        $this->respond($g, ['response' => 'not_attending']);

        $this->assertSame(0, $g->fresh()->plus_ones);
        $this->assertSame(0, $g->fresh()->headcount());
    }

    public function test_meal_choice_must_be_one_of_the_options_and_is_saved(): void
    {
        [$event] = $this->ecardEvent(['rsvp_meal_enabled' => true, 'rsvp_meal_options' => "Chicken\nVegetarian"]);
        $g = $this->guestCard($event);

        $this->respond($g, ['response' => 'attending', 'meal_choice' => 'Pizza'])->assertSessionHasErrors('meal_choice');
        $this->respond($g, ['response' => 'attending', 'meal_choice' => 'Vegetarian', 'dietary_note' => 'ignored: not enabled']);

        $g->refresh();
        $this->assertSame('Vegetarian', $g->meal_choice);
        $this->assertNull($g->dietary_note);
    }

    public function test_dietary_and_message_are_saved_when_enabled(): void
    {
        [$event] = $this->ecardEvent(['rsvp_dietary_enabled' => true, 'rsvp_message_enabled' => true]);
        $g = $this->guestCard($event);

        $this->respond($g, ['response' => 'attending', 'dietary_note' => 'No nuts', 'host_message' => 'Congratulations!']);

        $this->assertSame('No nuts', $g->fresh()->dietary_note);
        $this->assertSame('Congratulations!', $g->fresh()->host_message);
    }

    public function test_replies_after_the_cutoff_are_refused(): void
    {
        [$event] = $this->ecardEvent(['rsvp_cutoff_date' => now()->subDay()->toDateString()]);
        $g = $this->guestCard($event);

        $this->respond($g, ['response' => 'attending'])->assertSessionHas('rsvp_error');
        $this->assertNull($g->fresh()->rsvp_status);
        $this->get(route('guest.rsvp', $g->invite_token))->assertOk()->assertSee('RSVP is closed');
    }

    public function test_cutoff_day_itself_is_still_open(): void
    {
        [$event] = $this->ecardEvent(['rsvp_cutoff_date' => now()->toDateString()]);
        $g = $this->guestCard($event);

        $this->respond($g, ['response' => 'attending']);
        $this->assertSame('attending', $g->fresh()->rsvp_status);
    }

    public function test_admin_saves_rsvp_options_and_totals_show_on_the_rsvp_tab(): void
    {
        [$event, $admin] = $this->ecardEvent();

        $this->actingAs($admin)->patch(route('rsvp.settings'), [
            'rsvp_plus_ones_enabled' => 1, 'rsvp_max_plus_single' => 2, 'rsvp_max_plus_double' => 0,
            'rsvp_meal_enabled' => 1, 'rsvp_meal_options' => "Chicken\n\n  Beef  \n",
        ])->assertRedirect();

        $event->refresh();
        $this->assertTrue($event->rsvp_plus_ones_enabled);
        $this->assertSame("Chicken\nBeef", $event->rsvp_meal_options);

        $this->guestCard($event, ['rsvp_status' => 'attending', 'plus_ones' => 2, 'meal_choice' => 'Beef']);
        $this->guestCard($event, ['rsvp_status' => 'attending', 'card_type' => 'double', 'meal_choice' => 'Beef']);

        $this->actingAs($admin)->get(route('guests.index', ['tab' => 'rsvp']))->assertOk()
            ->assertSee('<strong>5</strong> people expected', false)->assertSee('Beef × 2');
    }

    public function test_the_card_asks_the_extra_questions_only_when_enabled(): void
    {
        [$event] = $this->ecardEvent(['rsvp_plus_ones_enabled' => true, 'rsvp_max_plus_single' => 2, 'rsvp_meal_enabled' => true, 'rsvp_meal_options' => 'Chicken']);
        $g = $this->guestCard($event);

        $this->get(route('guest.rsvp', $g->invite_token))->assertSee('Extra guests coming with you')->assertSee('Meal choice');

        $event->update(['rsvp_plus_ones_enabled' => false, 'rsvp_meal_enabled' => false]);
        $this->get(route('guest.rsvp', $g->invite_token))->assertDontSee('Extra guests coming with you');
    }

    // ---- Seating --------------------------------------------------------------------------

    public function test_tables_can_be_created_in_bulk_and_assigned(): void
    {
        [$event, $admin] = $this->ecardEvent();

        $this->actingAs($admin)->patch(route('seating.mode'), ['seating_mode' => 'table']);
        $this->actingAs($admin)->post(route('seating.tables.store'), ['name' => 'Table', 'capacity' => 6, 'count' => 3])->assertRedirect();
        $this->assertSame(['Table 1', 'Table 2', 'Table 3'], $event->seatingTables()->pluck('name')->all());

        $t = $event->seatingTables()->first();
        $g = $this->guestCard($event);

        $this->actingAs($admin)->patch(route('seating.assign', $g), ['seating_table_id' => $t->id, 'seat_number' => 2, 'group_name' => 'Family'])->assertRedirect();

        $g->refresh();
        $this->assertSame($t->id, $g->seating_table_id);
        $this->assertSame('Table 1 · seat 2', $g->seatLabel());
        $this->assertSame('Family', $g->group_name);
    }

    public function test_over_capacity_and_unseated_warnings_appear(): void
    {
        [$event, $admin] = $this->ecardEvent(['seating_mode' => 'table']);
        $t = SeatingTable::create(['event_id' => $event->id, 'name' => 'Small', 'capacity' => 2]);
        $this->guestCard($event, ['seating_table_id' => $t->id, 'card_type' => 'double', 'plus_ones' => 1, 'rsvp_status' => 'attending']);
        $this->guestCard($event);

        $this->actingAs($admin)->get(route('seating.index'))->assertOk()
            ->assertSee('Small is over capacity (3 of 2 seats)')->assertSee('1 guest(s) have no seat yet');
    }

    public function test_grouped_guests_split_across_tables_are_warned_about(): void
    {
        [$event, $admin] = $this->ecardEvent(['seating_mode' => 'table']);
        $a = SeatingTable::create(['event_id' => $event->id, 'name' => 'A', 'capacity' => 8]);
        $b = SeatingTable::create(['event_id' => $event->id, 'name' => 'B', 'capacity' => 8]);
        $this->guestCard($event, ['group_name' => 'Cousins', 'seating_table_id' => $a->id]);
        $this->guestCard($event, ['group_name' => 'Cousins', 'seating_table_id' => $b->id]);

        $this->actingAs($admin)->get(route('seating.index'))->assertSee('Group “Cousins” is split');
    }

    public function test_auto_fill_keeps_groups_together_and_never_moves_seated_guests(): void
    {
        [$event, $admin] = $this->ecardEvent(['seating_mode' => 'table']);
        $a = SeatingTable::create(['event_id' => $event->id, 'name' => 'A', 'capacity' => 3]);
        $b = SeatingTable::create(['event_id' => $event->id, 'name' => 'B', 'capacity' => 3]);
        $seated = $this->guestCard($event, ['seating_table_id' => $a->id]);
        $g1 = $this->guestCard($event, ['group_name' => 'Family']);
        $g2 = $this->guestCard($event, ['group_name' => 'Family']);
        $g3 = $this->guestCard($event, ['group_name' => 'Family']);

        $this->actingAs($admin)->post(route('seating.auto-fill'))->assertRedirect();

        $this->assertSame($a->id, $seated->fresh()->seating_table_id);
        $this->assertSame($b->id, $g1->fresh()->seating_table_id);
        $this->assertSame($b->id, $g2->fresh()->seating_table_id);
        $this->assertSame($b->id, $g3->fresh()->seating_table_id);
    }

    public function test_seat_is_hidden_from_the_card_until_published(): void
    {
        [$event, $admin] = $this->ecardEvent(['seating_mode' => 'table']);
        $t = SeatingTable::create(['event_id' => $event->id, 'name' => 'Table 12', 'capacity' => 8]);
        $g = $this->guestCard($event, ['seating_table_id' => $t->id]);

        $this->get(route('guest.rsvp', $g->invite_token))->assertDontSee('Table 12');

        $this->actingAs($admin)->patch(route('seating.publish'), ['seating_published' => 1]);
        $this->post('/logout');

        $this->get(route('guest.rsvp', $g->invite_token))->assertSee('Table 12');
    }

    public function test_cannot_assign_a_guest_to_another_events_table(): void
    {
        [$event, $admin] = $this->ecardEvent();
        [$other] = $this->ecardEvent();
        $foreign = SeatingTable::create(['event_id' => $other->id, 'name' => 'Theirs', 'capacity' => 8]);
        $g = $this->guestCard($event);

        $this->actingAs($admin)->patch(route('seating.assign', $g), ['seating_table_id' => $foreign->id])->assertNotFound();
        $this->actingAs($admin)->patch(route('seating.tables.update', $foreign), ['name' => 'x', 'capacity' => 1])->assertNotFound();
        $this->actingAs($admin)->delete(route('seating.tables.destroy', $foreign))->assertNotFound();
    }

    public function test_deleting_a_table_leaves_guests_unseated_not_deleted(): void
    {
        [$event, $admin] = $this->ecardEvent(['seating_mode' => 'table']);
        $t = SeatingTable::create(['event_id' => $event->id, 'name' => 'T', 'capacity' => 8]);
        $g = $this->guestCard($event, ['seating_table_id' => $t->id]);

        $this->actingAs($admin)->delete(route('seating.tables.destroy', $t))->assertRedirect();

        $this->assertNull($g->fresh()->seating_table_id);
    }

    public function test_zone_seating_shows_the_area_name(): void
    {
        [$event, $admin] = $this->ecardEvent(['seating_mode' => 'zone', 'seating_published' => true]);
        $this->actingAs($admin)->post(route('seating.areas.store'), ['name' => 'VIP']);
        $area = $event->seatingAreas()->first();
        $g = $this->guestCard($event);

        $this->actingAs($admin)->patch(route('seating.assign', $g), ['seating_area_id' => $area->id]);

        $this->assertSame('VIP', $g->fresh()->seatLabel());
    }

    public function test_contributions_events_get_the_same_tabs(): void
    {
        $event = \App\Models\Event::factory()->create(['mode' => 'contributions', 'event_type' => 'Wedding']);
        $admin = $this->memberOf($event, 'admin');

        $this->actingAs($admin)->get(route('guests.index'))->assertOk()->assertSee('Delivery')->assertSee('Seating')->assertDontSee('>Photos<', false);
        $this->actingAs($admin)->get(route('seating.index'))->assertOk();
        $this->actingAs($admin)->get(route('delivery.index'))->assertOk();
    }

    public function test_resetting_an_rsvp_on_a_contribution_account_returns_the_guest_to_not_generated(): void
    {
        $event = \App\Models\Event::factory()->create(['event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);
        $admin = $this->memberOf($event, 'admin');
        $old = \Illuminate\Support\Str::random(32);
        $a = \App\Models\Pledge::factory()->create(['event_id' => $event->id, 'amount' => 0, 'guest_only' => true, 'invite_token' => $old,
            'rsvp_status' => 'attending', 'rsvp_at' => now(), 'plus_ones' => 1, 'meal_choice' => 'Chicken', 'host_message' => 'Hi', 'seat_number' => 4,
            'invite_sent_at' => now(), 'invite_channel' => 'sms', 'first_opened_at' => now(), 'open_count' => 3]);
        $b = \App\Models\Pledge::factory()->create(['event_id' => $event->id, 'amount' => 0, 'guest_only' => true, 'invite_token' => \Illuminate\Support\Str::random(32), 'rsvp_status' => 'not_attending', 'rsvp_at' => now()]);

        $this->actingAs($admin)->get(route('guests.index', ['tab' => 'rsvp']))->assertOk()->assertSee('Reset all');
        $this->actingAs($admin)->post(route('rsvp.reset', $a))->assertRedirect();

        $a = $a->fresh();
        $this->assertNull($a->rsvp_status);
        $this->assertNull($a->rsvp_at);
        $this->assertSame(0, (int) $a->plus_ones);
        $this->assertNull($a->meal_choice);
        $this->assertNull($a->host_message);
        $this->assertSame(4, (int) $a->seat_number, 'seating must not change');
        $this->assertNull($a->invite_token, 'back to "Not generated yet"');
        $this->assertNull($a->invite_revoked_at, 'so no Deactivated badge');
        $this->assertNull($a->invite_sent_at);
        $this->assertNull($a->first_opened_at);
        $this->assertSame(0, (int) $a->open_count);
        $this->assertSame('not_attending', $b->fresh()->rsvp_status);

        $this->get(route('guest.rsvp', $old))->assertStatus(410);

        $html = $this->actingAs($admin)->get(route('guests.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Not generated yet', $html);
        $this->assertStringContainsString('Send invite', $html);
        $this->assertStringNotContainsString(route('delivery.revoke', $a), $html, 'no Deactivate button once reset');

        $this->actingAs($admin)->post(route('rsvp.reset-all'))->assertRedirect();
        $this->assertNull($b->fresh()->rsvp_status);
        $this->assertNull($b->fresh()->invite_token);
    }

    public function test_on_ecard_accounts_reset_clears_the_answer_and_gives_a_fresh_working_link(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event, ['rsvp_status' => 'attending', 'rsvp_at' => now(), 'invite_sent_at' => now(), 'seat_number' => 2]);
        $old = $g->invite_token;

        $this->actingAs($admin)->post(route('rsvp.reset', $g))->assertRedirect();
        $g = $g->fresh();

        $this->assertNull($g->rsvp_status);
        $this->assertNotNull($g->invite_token);
        $this->assertNotSame($old, $g->invite_token);
        $this->assertNull($g->invite_sent_at);
        $this->assertSame(2, (int) $g->seat_number);
        $this->get(route('guest.rsvp', $old))->assertStatus(410);
        $this->get(route('guest.rsvp', $g->invite_token))->assertOk();
        $this->respond($g, ['response' => 'attending'])->assertRedirect();
        $this->assertSame('attending', $g->fresh()->rsvp_status);
    }

    public function test_rsvp_reset_is_admin_only_and_limited_to_the_current_event(): void
    {
        [$event, $admin] = $this->ecardEvent();
        [$other] = $this->ecardEvent();
        $foreign = $this->guestCard($other, ['rsvp_status' => 'attending']);

        $this->actingAs($admin)->post(route('rsvp.reset', $foreign))->assertNotFound();
        $this->assertSame('attending', $foreign->fresh()->rsvp_status);

        $member = $this->memberOf($event, 'scanner');
        $this->actingAs($member)->post(route('rsvp.reset-all'))->assertForbidden();
    }
}
