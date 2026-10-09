<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class WhatsAppQuotaTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function whatsappOn(): void
    {
        config(['services.whatsapp.token' => 't', 'services.whatsapp.phone_number_id' => '123']);
    }

    private function sentOk(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
    }

    public function test_new_events_start_with_no_whatsapp_quota(): void
    {
        $event = Event::factory()->create()->fresh();

        $this->assertSame(0, $event->whatsapp_quota);
        $this->assertFalse($event->hasWhatsappCapacity());
        $this->assertSame(0, $event->whatsappRemaining());
    }

    public function test_reserve_takes_one_until_the_quota_is_used_up(): void
    {
        $event = Event::factory()->create(['whatsapp_quota' => 2]);

        $this->assertTrue($event->reserveWhatsapp());
        $this->assertTrue($event->reserveWhatsapp());
        $this->assertFalse($event->reserveWhatsapp());
        $this->assertSame(2, $event->fresh()->whatsapp_sent_count);

        $event->releaseWhatsapp();
        $this->assertSame(1, $event->fresh()->whatsapp_sent_count);
        $this->assertTrue($event->fresh()->reserveWhatsapp());
    }

    public function test_sending_is_blocked_at_zero_quota_and_nothing_goes_to_meta(): void
    {
        $this->whatsappOn();
        Http::fake();
        [$event, $admin] = $this->ecardEvent(['whatsapp_quota' => 0]);
        $guest = $this->guestCard($event, ['phone' => '0712345678']);

        $this->actingAs($admin)->post(route('guests.whatsapp-send', $guest))
            ->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame(0, $event->fresh()->whatsapp_sent_count);
    }

    public function test_a_successful_send_uses_one_invitation(): void
    {
        $this->whatsappOn();
        $this->sentOk();
        [$event, $admin] = $this->ecardEvent(['whatsapp_quota' => 5]);
        $guest = $this->guestCard($event, ['phone' => '0712345678']);

        $this->actingAs($admin)->post(route('guests.whatsapp-send', $guest))
            ->assertSessionHas('status');

        $this->assertSame(1, $event->fresh()->whatsapp_sent_count);
    }

    public function test_a_message_meta_refuses_gives_the_invitation_back(): void
    {
        $this->whatsappOn();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template not found']], 400)]);
        [$event, $admin] = $this->ecardEvent(['whatsapp_quota' => 5]);
        $guest = $this->guestCard($event, ['phone' => '0712345678']);

        $this->actingAs($admin)->post(route('guests.whatsapp-send', $guest))
            ->assertSessionHas('error');

        $this->assertSame(0, $event->fresh()->whatsapp_sent_count);
    }

    public function test_an_expired_token_shows_a_plain_message_not_meta_jargon(): void
    {
        $this->whatsappOn();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Error validating access token', 'type' => 'OAuthException', 'code' => 190]], 401)]);
        [$event, $admin] = $this->ecardEvent(['whatsapp_quota' => 5]);
        $guest = $this->guestCard($event, ['phone' => '0712345678']);

        $response = $this->actingAs($admin)->post(route('guests.whatsapp-send', $guest));

        $message = session('error');
        $this->assertStringContainsString('temporarily unavailable', $message);
        $this->assertStringContainsString('SMS', $message);
        $this->assertStringNotContainsString('Authentication', $message);
        $this->assertStringNotContainsString('token', strtolower($message));
        $this->assertSame(0, $event->fresh()->whatsapp_sent_count);
    }

    public function test_known_meta_errors_get_friendly_messages(): void
    {
        $svc = \App\Services\WhatsAppCloudService::class;

        $this->assertStringContainsString('not on WhatsApp', $svc::friendlyError(131026));
        $this->assertStringContainsString("can't receive", $svc::friendlyError(131030));
        $this->assertStringContainsString('system admin', $svc::friendlyError(132001));
        $this->assertStringContainsString('billing', $svc::friendlyError(131042));
        $this->assertStringContainsString('Too many', $svc::friendlyError(0, '', 429));
        $this->assertStringContainsString('code 999', $svc::friendlyError(999));
    }

    public function test_guest_list_shows_a_disabled_button_when_the_quota_is_used_up(): void
    {
        $this->withoutVite();
        $this->whatsappOn();
        [$event, $admin] = $this->ecardEvent(['whatsapp_quota' => 1, 'whatsapp_sent_count' => 1]);
        $this->guestCard($event, ['phone' => '0712345678']);

        $this->actingAs($admin)->get(route('guests.index', ['tab' => 'event']))
            ->assertOk()
            ->assertSee('WhatsApp invitation quota used up', false);
    }

    public function test_system_admin_sets_the_whatsapp_quota_and_it_is_logged(): void
    {
        $super = User::factory()->superUser()->create();
        [$event, $owner] = $this->ecardEvent();
        $event->update(['created_by' => $owner->id]);

        $this->actingAs($super)->patch(route('admin.users.sms-quota', $owner), [
            'sms_quota' => 500,
            'whatsapp_quota' => 120,
        ])->assertSessionHas('status');

        $event->refresh();
        $this->assertSame(500, $event->sms_quota);
        $this->assertSame(120, $event->whatsapp_quota);
    }

    public function test_packages_without_cards_cannot_get_a_whatsapp_quota(): void
    {
        $super = User::factory()->superUser()->create();
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id, 'package' => 'sms', 'mode' => 'full', 'event_type' => 'Wedding']);
        \App\Models\EventMember::create(['event_id' => $event->id, 'user_id' => $owner->id, 'role' => 'admin']);

        $this->actingAs($super)->patch(route('admin.users.sms-quota', $owner), [
            'sms_quota' => 100,
            'whatsapp_quota' => 50,
        ]);

        $this->assertSame(0, $event->fresh()->whatsapp_quota);
    }

    public function test_settings_page_shows_the_whatsapp_quota_even_before_whatsapp_is_connected(): void
    {
        $this->withoutVite();
        [$event, $admin] = $this->ecardEvent(['whatsapp_quota' => 100, 'whatsapp_sent_count' => 25]);

        $this->actingAs($admin)->get(route('event.settings'))
            ->assertOk()
            ->assertSee('WhatsApp invitations', false)
            ->assertSee('25 of 100 sent', false);
    }

    public function test_a_team_member_has_no_quota_of_their_own_to_set(): void
    {
        $super = User::factory()->superUser()->create();
        [$event, $host] = $this->ecardEvent();
        $event->update(['created_by' => $host->id]);
        $member = User::factory()->create(['created_by' => $host->id]);
        \App\Models\EventMember::create(['event_id' => $event->id, 'user_id' => $member->id, 'role' => 'viewer']);

        $this->actingAs($super)->patch(route('admin.users.sms-quota', $member), ['sms_quota' => 10, 'whatsapp_quota' => 5])
            ->assertRedirect()->assertSessionHas("error");

        $this->assertSame(0, $event->fresh()->whatsapp_quota);
    }

    public function test_accounts_list_hides_the_quota_pencil_for_team_members_and_shows_it_for_owners(): void
    {
        $this->withoutVite();
        $super = User::factory()->superUser()->create();
        [$event, $host] = $this->ecardEvent();
        $event->update(['created_by' => $host->id]);
        $host->update(['created_by' => $super->id]);
        $member = User::factory()->create(['created_by' => $host->id]);
        \App\Models\EventMember::create(['event_id' => $event->id, 'user_id' => $member->id, 'role' => 'viewer']);

        $html = $this->actingAs($super)->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="editQuota'.$host->id.'"', $html);
        $this->assertStringNotContainsString('id="editQuota'.$member->id.'"', $html);
        $this->assertStringContainsString('team member', $html);
    }
}
