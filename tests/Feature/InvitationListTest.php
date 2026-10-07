<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Pledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

/** Event invitation page (contribution accounts): add a new guest or pick people from the pledge list. */
class InvitationListTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function fullEvent(): array
    {
        $event = Event::factory()->create(['event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);

        return [$event, $this->memberOf($event, 'admin')];
    }

    private function beem(): void
    {
        config(['services.beem.api_key' => 'k', 'services.beem.secret_key' => 's']);
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'valid' => 1, 'invalid' => 0, 'request_id' => 1])]);
    }

    private function pledge(Event $event, array $attrs = []): Pledge
    {
        return Pledge::factory()->create(array_merge(['event_id' => $event->id, 'amount' => 100000, 'paid' => 0, 'pay_token' => Str::random(32)], $attrs));
    }

    public function test_a_new_guest_is_added_with_a_live_link_and_stays_out_of_the_money_side(): void
    {
        [$event, $admin] = $this->fullEvent();
        $this->pledge($event, ['paid' => 40000]);

        $this->actingAs($admin)->post(route('guests.invite.new'), ['name' => 'Uncle Juma', 'phone' => '0712345678', 'card_type' => 'double'])
            ->assertSessionHas('status');

        $guest = Pledge::where('name', 'Uncle Juma')->first();
        $this->assertTrue($guest->guest_only);
        $this->assertTrue($guest->on_invite_list);
        $this->assertNotNull($guest->invite_token);
        $this->assertSame('double', $guest->card_type);
        $this->assertSame('+255712345678', $guest->phone);

        $stats = $event->stats();
        $this->assertSame(1, $stats['pledge_count']);
        $this->assertSame(100000.0, $stats['total_pledged']);

        $this->flushSession(); // the confirmation toast names the guest — look at the pages themselves
        $this->actingAs($admin)->get(route('pledges.index'))->assertOk()->assertDontSee('Uncle Juma');
        $this->actingAs($admin)->get(route('guests.index'))->assertOk()->assertSee('Uncle Juma');
    }

    public function test_pledgers_are_chosen_from_the_pledge_list(): void
    {
        [$event, $admin] = $this->fullEvent();
        $a = $this->pledge($event, ['name' => 'Asha', 'paid' => 100000]);
        $b = $this->pledge($event, ['name' => 'Baraka']);

        $this->actingAs($admin)->get(route('guests.index'))->assertOk()->assertSee('data-name="asha"', false)->assertSee('Nobody on the invitation list yet');
        $this->actingAs($admin)->post(route('guests.invite.select'), ['ids' => [$a->id]])->assertSessionHas('status');

        $this->assertTrue($a->fresh()->on_invite_list);
        $this->assertFalse($b->fresh()->on_invite_list);

        $page = $this->actingAs($admin)->get(route('guests.index'))->assertOk();
        $page->assertSee('data-name="baraka"', false);   // still offered in the picker
        $page->assertDontSee('data-name="asha"', false); // already on the list
    }

    public function test_a_pledger_on_the_list_can_be_invited_without_paying(): void
    {
        $this->beem();
        [$event, $admin] = $this->fullEvent();
        $owing = $this->pledge($event, ['on_invite_list' => true, 'paid' => 0, 'phone' => '255712345678']);

        $this->actingAs($admin)->get(route('guests.index'))->assertOk()->assertDontSee('Locked')->assertSee('Send invite');
        $this->actingAs($admin)->post(route('guests.send-invite', $owing))->assertSessionHas('status');
        $this->assertNotNull($owing->fresh()->invite_token);

        $this->actingAs($admin)->post(route('guests.sms', $owing))->assertSessionHas('status');
        $this->assertNotNull($owing->fresh()->invite_sent_at);
        Http::assertSentCount(1);
    }

    public function test_correcting_a_payment_down_no_longer_removes_the_invitation_link(): void
    {
        [$event, $admin] = $this->fullEvent();
        $p = $this->pledge($event, ['on_invite_list' => true, 'paid' => 100000, 'invite_token' => Str::random(32), 'phone' => null]);

        $this->actingAs($admin)->patch(route('pledges.update', $p), ['name' => $p->name, 'amount' => 100000, 'paid_correction' => 20000]);

        $this->assertNotNull($p->fresh()->invite_token);
    }

    public function test_cannot_select_a_pledger_from_another_event_or_a_guest_as_pledger(): void
    {
        [$event, $admin] = $this->fullEvent();
        [$other] = $this->fullEvent();
        $foreign = $this->pledge($other);

        $this->actingAs($admin)->post(route('guests.invite.select'), ['ids' => [$foreign->id]]);
        $this->assertFalse($foreign->fresh()->on_invite_list);
    }

    public function test_selecting_nobody_is_rejected(): void
    {
        [, $admin] = $this->fullEvent();
        $this->actingAs($admin)->post(route('guests.invite.select'), [])->assertSessionHasErrors('ids');
    }

    public function test_pledger_can_be_taken_off_before_the_invitation_is_sent_but_not_after(): void
    {
        [$event, $admin] = $this->fullEvent();
        $fresh = $this->pledge($event, ['on_invite_list' => true]);
        $sent = $this->pledge($event, ['on_invite_list' => true, 'invite_sent_at' => now()]);

        $this->actingAs($admin)->delete(route('guests.invite.unlist', $fresh))->assertSessionHas('status');
        $this->actingAs($admin)->delete(route('guests.invite.unlist', $sent))->assertSessionHasErrors('invite');

        $this->assertFalse($fresh->fresh()->on_invite_list);
        $this->assertTrue($sent->fresh()->on_invite_list);
    }

    public function test_an_invited_guest_can_be_removed_but_a_pledger_cannot_be_deleted_here(): void
    {
        [$event, $admin] = $this->fullEvent();
        $guest = $this->pledge($event, ['guest_only' => true, 'amount' => 0, 'on_invite_list' => true, 'invite_token' => Str::random(32)]);
        $pledger = $this->pledge($event, ['on_invite_list' => true]);

        $this->actingAs($admin)->delete(route('guests.destroy', $guest))->assertSessionHas('status');
        $this->actingAs($admin)->delete(route('guests.destroy', $pledger))->assertNotFound();

        $this->assertNull(Pledge::find($guest->id));
        $this->assertNotNull(Pledge::find($pledger->id));
    }

    public function test_viewers_can_see_the_list_but_not_change_it(): void
    {
        [$event] = $this->fullEvent();
        $viewer = $this->memberOf($event, 'viewer');
        $this->pledge($event, ['name' => 'Owing Olga', 'on_invite_list' => true, 'paid' => 1]);
        $this->pledge($event, ['name' => 'Paid Pita', 'on_invite_list' => true, 'paid' => 100000]);

        $this->actingAs($viewer)->post(route('guests.invite.new'), ['name' => 'X', 'card_type' => 'single'])->assertForbidden();
        $this->actingAs($viewer)->get(route('guests.index'))->assertOk()->assertSee('Paid Pita')->assertSee('Owing Olga')->assertDontSee('Add new guest');
    }

    public function test_ecard_and_funeral_accounts_do_not_use_these_routes(): void
    {
        [, $admin] = $this->ecardEvent();
        $this->actingAs($admin)->post(route('guests.invite.new'), ['name' => 'X', 'card_type' => 'single'])->assertNotFound();
    }

    public function test_guest_only_people_are_not_in_pledge_exports_or_reminders(): void
    {
        [$event] = $this->fullEvent();
        $this->pledge($event, ['name' => 'Real', 'paid' => 0]);
        $this->pledge($event, ['name' => 'Guesty', 'guest_only' => true, 'amount' => 0]);

        $this->assertSame(['Real'], $event->pledges()->contributors()->pluck('name')->all());
        $this->assertSame(['Real'], $event->pledges()->outstanding()->pluck('name')->all());
        $rows = (new \App\Exports\PledgesExport($event))->collection()->pluck('Name')->all();
        $this->assertNotContains('Guesty', $rows);
    }
}
