<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

/** Each guest receives the invitation once; sending again needs a link reset first. */
class InvitationSentOnceTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function fakeSmsAndWhatsApp(): void
    {
        config(['services.whatsapp.token' => 't', 'services.whatsapp.phone_number_id' => '123', 'services.beem.api_key' => 'k', 'services.beem.secret_key' => 's']);
        Http::fake([
            'apisms.beem.africa/*' => Http::response(['successful' => true, 'valid' => 1, 'invalid' => 0, 'request_id' => 1]),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]]),
        ]);
    }

    public function test_a_second_send_by_any_channel_is_refused_and_nothing_goes_out(): void
    {
        $this->fakeSmsAndWhatsApp();
        [$event, $admin] = $this->ecardEvent(['whatsapp_quota' => 5]);
        $guest = $this->guestCard($event, ['phone' => '0712345678']);

        $this->actingAs($admin)->post(route('guests.sms', $guest))->assertSessionHas('status');
        $this->assertSame('sms', $guest->fresh()->invite_channel);
        Http::assertSentCount(1);

        // SMS again, the WhatsApp number, and the phone's own WhatsApp are all refused.
        $this->actingAs($admin)->post(route('guests.sms', $guest))->assertSessionHas('error');
        $this->actingAs($admin)->post(route('guests.whatsapp-send', $guest))->assertSessionHas('error');
        $this->actingAs($admin)->get(route('guests.whatsapp', $guest))->assertRedirect()->assertSessionHas('error');

        Http::assertSentCount(1);
        $this->assertSame(0, $event->fresh()->whatsapp_sent_count);
        $this->assertStringContainsString('already received', session('error'));
    }

    public function test_a_failed_whatsapp_invitation_can_still_be_sent_by_sms(): void
    {
        $this->fakeSmsAndWhatsApp();
        [$event, $admin] = $this->ecardEvent();
        $guest = $this->guestCard($event, [
            'phone' => '0712345678',
            'invite_sent_at' => now(),
            'invite_channel' => 'whatsapp',
            'whatsapp_status' => 'failed',
        ]);

        $this->actingAs($admin)->post(route('guests.sms', $guest))->assertSessionHas('status');
        $this->assertSame('sms', $guest->fresh()->invite_channel);
    }

    public function test_resetting_the_link_allows_one_new_send(): void
    {
        $this->fakeSmsAndWhatsApp();
        [$event, $admin] = $this->ecardEvent();
        $guest = $this->guestCard($event, ['phone' => '0712345678', 'invite_sent_at' => now(), 'invite_channel' => 'sms']);

        $this->actingAs($admin)->post(route('rsvp.reset', $guest));
        $this->assertNull($guest->fresh()->invite_sent_at);

        $this->actingAs($admin)->post(route('guests.sms', $guest->fresh()))->assertSessionHas('status');
        $this->actingAs($admin)->post(route('guests.sms', $guest->fresh()))->assertSessionHas('error');
    }

    public function test_the_list_shows_sent_instead_of_send_buttons(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $this->guestCard($event, ['name' => 'Sent Guest', 'phone' => '0712345678', 'invite_sent_at' => now(), 'invite_channel' => 'whatsapp']);
        $this->guestCard($event, ['name' => 'New Guest', 'phone' => '0712345679']);

        $html = $this->actingAs($admin)->get(route('guests.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Sent · WhatsApp', $html);
        // Only the guest who has not been invited yet still has an SMS button.
        $this->assertSame(1, substr_count($html, '<i class="fa-solid fa-comment-sms"></i> SMS'));
    }
}
