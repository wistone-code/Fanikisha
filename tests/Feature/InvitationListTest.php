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

        $this->actingAs($admin)->post(route('guests.invite.new'), ['name' => 'Uncle Juma', 'phone' => '0712345678'])
            ->assertSessionHas('status');

        $guest = Pledge::where('name', 'Uncle Juma')->first();
        $this->assertTrue($guest->guest_only);
        $this->assertNotNull($guest->invite_token);
        $this->assertSame('single', $guest->card_type);
        $this->assertSame('+255712345678', $guest->phone);

        $stats = $event->stats();
        $this->assertSame(1, $stats['pledge_count']);
        $this->assertSame(100000.0, $stats['total_pledged']);

        $this->flushSession(); // the confirmation toast names the guest — look at the pages themselves
        $this->actingAs($admin)->get(route('pledges.index'))->assertOk()->assertDontSee('Uncle Juma');
        $this->actingAs($admin)->get(route('guests.index'))->assertOk()->assertSee('Uncle Juma');
    }

    public function test_every_pledger_is_on_the_invitation_page_automatically_and_there_is_no_picker(): void
    {
        [$event, $admin] = $this->fullEvent();
        $this->pledge($event, ['name' => 'Asha Pledger']);

        $this->actingAs($admin)->get(route('guests.index'))->assertOk()
            ->assertSee('Asha Pledger')->assertSee('Add guest')
            ->assertDontSee('Choose from pledge list')->assertSee('Single card (one person)')->assertSee('Double card (couple, two people)');
    }

    public function test_a_pledger_on_the_list_can_be_invited_without_paying(): void
    {
        $this->beem();
        [$event, $admin] = $this->fullEvent();
        $owing = $this->pledge($event, ['paid' => 0, 'phone' => '255712345678']);

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
        $p = $this->pledge($event, ['paid' => 100000, 'invite_token' => Str::random(32), 'phone' => null]);

        $this->actingAs($admin)->patch(route('pledges.update', $p), ['name' => $p->name, 'amount' => 100000, 'paid_correction' => 20000]);

        $this->assertNotNull($p->fresh()->invite_token);
    }

    public function test_an_invited_guest_can_be_removed_but_a_pledger_cannot_be_deleted_here(): void
    {
        [$event, $admin] = $this->fullEvent();
        $guest = $this->pledge($event, ['guest_only' => true, 'amount' => 0, 'invite_token' => Str::random(32)]);
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
        $this->pledge($event, ['name' => 'Owing Olga', 'paid' => 1]);
        $this->pledge($event, ['name' => 'Paid Pita', 'paid' => 100000]);

        $this->actingAs($viewer)->post(route('guests.invite.new'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->get(route('guests.index'))->assertOk()->assertSee('Paid Pita')->assertSee('Owing Olga')->assertDontSee('Add new guest');
    }

    public function test_ecard_and_funeral_accounts_do_not_use_these_routes(): void
    {
        [, $admin] = $this->ecardEvent();
        $this->actingAs($admin)->post(route('guests.invite.new'), ['name' => 'X'])->assertNotFound();
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

    public function test_a_guest_can_be_added_as_a_double_card_and_counts_as_two_people(): void
    {
        [$event, $admin] = $this->fullEvent();

        $this->actingAs($admin)->post(route('guests.invite.new'), ['name' => 'Mr and Mrs Kimaro', 'card_type' => 'double'])->assertSessionHas('status');
        $this->actingAs($admin)->post(route('guests.invite.new'), ['name' => 'Odd One', 'card_type' => 'vip'])->assertSessionHasErrors('card_type');

        $guest = Pledge::where('name', 'Mr and Mrs Kimaro')->first();
        $this->assertSame('double', $guest->card_type);
        $this->assertSame(2, $guest->headcount());
        $this->assertNull(Pledge::where('name', 'Odd One')->first());

        $this->actingAs($admin)->patch(route('guests.update', $guest), ['name' => 'Mr and Mrs Kimaro', 'card_type' => 'single']);
        $this->assertSame(1, $guest->fresh()->headcount());
    }
}
