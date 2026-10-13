<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

/** Guests who can't attend can still leave a message, addressed to the event by name. */
class DeclineMessageTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    public function test_a_guest_who_cannot_attend_can_leave_a_message(): void
    {
        [$event, $admin] = $this->ecardEvent(['rsvp_message_enabled' => true, 'name' => 'Mhaka & Juju Wedding']);
        $guest = $this->guestCard($event);

        $html = $this->get(route('guest.rsvp', $guest->invite_token))->assertOk()->getContent();
        $this->assertStringContainsString('A message for Mhaka &amp; Juju Wedding', $html);
        $this->assertStringNotContainsString('for the hosts', $html);

        $this->post(route('guest.rsvp.respond', $guest->invite_token), [
            'response' => 'not_attending', 'host_message' => 'Sorry I will be travelling. Congratulations!',
        ])->assertRedirect();

        $guest->refresh();
        $this->assertSame('not_attending', $guest->rsvp_status);
        $this->assertSame('Sorry I will be travelling. Congratulations!', $guest->host_message);

        $this->actingAs($admin)->get(route('guests.index', ['tab' => 'rsvp']))->assertOk()->assertSee('Sorry I will be travelling');
    }

    public function test_the_swahili_card_names_the_event_too(): void
    {
        [$event] = $this->ecardEvent(['rsvp_message_enabled' => true, 'name' => 'Harusi ya Mhaka']);
        $guest = $this->guestCard($event);

        $this->get(route('guest.rsvp', [$guest->invite_token, 'lang' => 'sw']))->assertOk()->assertSee('Ujumbe kwa Harusi ya Mhaka');
    }
}
