<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

/** A WhatsApp invitation that does not arrive is sent by normal SMS automatically, once. */
class WhatsAppSmsFallbackTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private const SECRET = 'app-secret-for-tests';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.whatsapp.app_secret' => self::SECRET, 'services.whatsapp.token' => 't', 'services.whatsapp.phone_number_id' => '123',
            'services.beem.api_key' => 'k', 'services.beem.secret_key' => 's',
        ]);
    }

    private function report(array $statuses)
    {
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => ['statuses' => $statuses]]]]]]);

        return $this->call('POST', route('webhooks.whatsapp.receive'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, self::SECRET),
        ], $body);
    }

    private function sentOnWhatsApp(array $eventAttrs = []): array
    {
        [$event, $admin] = $this->ecardEvent(array_merge(['whatsapp_quota' => 5, 'whatsapp_sent_count' => 1, 'sms_quota' => 10, 'sms_sent_count' => 0], $eventAttrs));
        $guest = $this->guestCard($event, ['phone' => '0712345678', 'invite_sent_at' => now(), 'invite_channel' => 'whatsapp',
            'whatsapp_message_id' => 'wamid.ABC', 'whatsapp_status' => 'sent']);

        return [$event, $guest, $admin];
    }

    public function test_a_failed_whatsapp_invitation_is_sent_by_sms_automatically(): void
    {
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'valid' => 1, 'invalid' => 0, 'request_id' => 1])]);
        [$event, $guest, $admin] = $this->sentOnWhatsApp();

        $this->report([['id' => 'wamid.ABC', 'status' => 'failed', 'errors' => [['code' => 131049, 'title' => 'ecosystem engagement']]]])->assertOk();

        $guest->refresh();
        Http::assertSentCount(1);
        $this->assertSame('sms', $guest->invite_channel);
        $this->assertSame('failed', $guest->whatsapp_status);
        $this->assertStringContainsString('[131049]', $guest->whatsapp_error);
        $this->assertSame(1, $event->fresh()->sms_sent_count);

        // A repeated report does not send a second SMS.
        $this->report([['id' => 'wamid.ABC', 'status' => 'failed', 'errors' => [['code' => 131049]]]])->assertOk();
        Http::assertSentCount(1);

        $this->actingAs($admin)->get(route('delivery.index'))->assertSee('sent by SMS instead');
    }

    public function test_no_sms_when_the_event_has_no_sms_left(): void
    {
        Http::fake();
        [, $guest] = $this->sentOnWhatsApp(['sms_quota' => 5, 'sms_sent_count' => 5]);

        $this->report([['id' => 'wamid.ABC', 'status' => 'failed', 'errors' => [['code' => 131026]]]])->assertOk();

        Http::assertNothingSent();
        $this->assertSame('whatsapp', $guest->fresh()->invite_channel);
        $this->assertSame('failed', $guest->fresh()->whatsapp_status);
    }

    public function test_a_whatsapp_send_refused_by_meta_goes_out_by_sms(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['code' => 131026, 'message' => 'Receiver is incapable']], 400),
            'apisms.beem.africa/*' => Http::response(['successful' => true, 'valid' => 1, 'invalid' => 0, 'request_id' => 1]),
        ]);
        [$event, $admin] = $this->ecardEvent(['whatsapp_quota' => 5, 'sms_quota' => 10]);
        $guest = $this->guestCard($event, ['phone' => '0712345678']);

        $this->actingAs($admin)->post(route('guests.whatsapp-send', $guest))->assertSessionHas('status', fn ($s) => str_contains($s, 'sent by SMS instead'));

        $this->assertSame('sms', $guest->fresh()->invite_channel);
        $this->assertSame(0, (int) $event->fresh()->whatsapp_sent_count);
    }
}
