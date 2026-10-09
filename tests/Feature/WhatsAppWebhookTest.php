<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Pledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private const SECRET = 'app-secret-for-tests';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.verify_token' => 'my-verify-word', 'services.whatsapp.app_secret' => self::SECRET]);
    }

    /** Posts a delivery report the way Meta does: raw JSON body plus its HMAC signature. */
    private function report(array $statuses, ?string $secret = self::SECRET)
    {
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => ['statuses' => $statuses]]]]]]);
        $signature = 'sha256='.hash_hmac('sha256', $body, $secret ?? '');

        return $this->call('POST', route('webhooks.whatsapp.receive'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $body);
    }

    private function sentGuest(array $eventAttrs = []): array
    {
        [$event] = $this->ecardEvent(array_merge(['whatsapp_quota' => 5, 'whatsapp_sent_count' => 1], $eventAttrs));
        $guest = $this->guestCard($event, ['phone' => '0712345678', 'whatsapp_message_id' => 'wamid.ABC', 'whatsapp_status' => 'sent']);

        return [$event, $guest];
    }

    public function test_meta_setup_check_gets_the_challenge_back_with_the_right_verify_token(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=my-verify-word&hub.challenge=12345')
            ->assertOk()
            ->assertSee('12345', false);
    }

    public function test_setup_check_is_refused_with_a_wrong_or_missing_verify_token(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')->assertForbidden();

        config(['services.whatsapp.verify_token' => null]);
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=&hub.challenge=12345')->assertForbidden();
    }

    public function test_a_report_without_a_valid_signature_is_refused_and_changes_nothing(): void
    {
        [, $guest] = $this->sentGuest();

        $this->report([['id' => 'wamid.ABC', 'status' => 'delivered']], 'someone-elses-secret')->assertForbidden();
        $this->postJson(route('webhooks.whatsapp.receive'), [])->assertForbidden();

        $this->assertSame('sent', $guest->fresh()->whatsapp_status);
    }

    public function test_nothing_is_accepted_when_the_app_secret_is_not_set(): void
    {
        config(['services.whatsapp.app_secret' => '']);
        [, $guest] = $this->sentGuest();

        $this->report([['id' => 'wamid.ABC', 'status' => 'delivered']], '')->assertForbidden();
        $this->assertSame('sent', $guest->fresh()->whatsapp_status);
    }

    public function test_status_moves_forward_from_sent_to_delivered_to_read(): void
    {
        [, $guest] = $this->sentGuest();

        $this->report([['id' => 'wamid.ABC', 'status' => 'delivered', 'timestamp' => (string) now()->timestamp]])->assertOk();
        $this->assertSame('delivered', $guest->fresh()->whatsapp_status);

        $this->report([['id' => 'wamid.ABC', 'status' => 'read']])->assertOk();
        $this->assertSame('read', $guest->fresh()->whatsapp_status);
    }

    public function test_a_late_sent_report_never_moves_the_status_backwards(): void
    {
        [, $guest] = $this->sentGuest();
        $guest->update(['whatsapp_status' => 'read']);

        $this->report([['id' => 'wamid.ABC', 'status' => 'sent'], ['id' => 'wamid.ABC', 'status' => 'delivered']])->assertOk();

        $this->assertSame('read', $guest->fresh()->whatsapp_status);
    }

    public function test_a_failed_report_stores_the_reason_and_gives_the_invitation_back(): void
    {
        [$event, $guest] = $this->sentGuest();

        $this->report([['id' => 'wamid.ABC', 'status' => 'failed', 'errors' => [['code' => 131026, 'message' => 'Message undeliverable']]]])->assertOk();

        $guest->refresh();
        $this->assertSame('failed', $guest->whatsapp_status);
        $this->assertStringContainsString('not on WhatsApp', $guest->whatsapp_error);
        $this->assertSame(0, $event->fresh()->whatsapp_sent_count);
    }

    public function test_a_repeated_failed_report_gives_back_only_one_invitation(): void
    {
        [$event] = $this->sentGuest(['whatsapp_sent_count' => 2]);
        $failed = [['id' => 'wamid.ABC', 'status' => 'failed', 'errors' => [['message' => 'x']]]];

        $this->report($failed)->assertOk();
        $this->report($failed)->assertOk();

        $this->assertSame(1, $event->fresh()->whatsapp_sent_count);
    }

    public function test_a_failure_after_delivery_is_ignored(): void
    {
        [$event, $guest] = $this->sentGuest();
        $guest->update(['whatsapp_status' => 'delivered']);

        $this->report([['id' => 'wamid.ABC', 'status' => 'failed']])->assertOk();

        $this->assertSame('delivered', $guest->fresh()->whatsapp_status);
        $this->assertSame(1, $event->fresh()->whatsapp_sent_count);
    }

    public function test_unknown_message_ids_and_odd_payloads_are_harmless(): void
    {
        $this->sentGuest();

        $this->report([['id' => 'wamid.UNKNOWN', 'status' => 'delivered'], ['status' => 'delivered'], ['id' => 'wamid.ABC', 'status' => 'banana']])->assertOk();
        $this->assertSame('sent', Pledge::where('whatsapp_message_id', 'wamid.ABC')->value('whatsapp_status'));
    }

    public function test_sending_stores_the_message_id_for_the_webhook_to_match(): void
    {
        config(['services.whatsapp.token' => 't', 'services.whatsapp.phone_number_id' => '123']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.NEW']]])]);
        [$event, $admin] = $this->ecardEvent(['whatsapp_quota' => 5]);
        $guest = $this->guestCard($event, ['phone' => '0712345678']);

        $this->actingAs($admin)->post(route('guests.whatsapp-send', $guest))->assertSessionHas('status');

        $guest->refresh();
        $this->assertSame('wamid.NEW', $guest->whatsapp_message_id);
        $this->assertSame('sent', $guest->whatsapp_status);
    }

    public function test_delivery_page_shows_the_whatsapp_status(): void
    {
        $this->withoutVite();
        [$event, $guest] = $this->sentGuest();
        $guest->update(['whatsapp_status' => 'failed', 'whatsapp_error' => 'Message undeliverable']);
        $admin = $this->memberOf($event, 'admin');

        $this->actingAs($admin)->get(route('delivery.index'))
            ->assertOk()
            ->assertSee('WhatsApp failed', false)
            ->assertSee('use SMS', false);
    }
}
