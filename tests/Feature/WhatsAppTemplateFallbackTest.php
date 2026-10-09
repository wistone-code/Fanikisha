<?php

namespace Tests\Feature;

use App\Services\WhatsAppCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class WhatsAppTemplateFallbackTest extends TestCase
{
    use EventTestHelpers;
    use RefreshDatabase;

    private function on(): void
    {
        config(['services.whatsapp.token' => 'EAAt', 'services.whatsapp.phone_number_id' => '123']);
    }

    /** Meta answers "does not exist" for every template name in $missing, success for the rest. */
    private function metaWithout(array $missing, array $missingLangs = []): void
    {
        Http::fake(function (Request $r) use ($missing, $missingLangs) {
            $t = $r['template']['name'];
            $l = $r['template']['language']['code'];

            if (in_array($t, $missing, true) || in_array("$t:$l", $missingLangs, true)) {
                return Http::response(['error' => ['code' => 132001, 'message' => 'Template name does not exist in the translation']], 404);
            }

            return Http::response(['messages' => [['id' => 'wamid.OK']]]);
        });
    }

    private function names(): array
    {
        return Http::recorded()->map(fn ($p) => $p[0]['template']['name'].':'.$p[0]['template']['language']['code'])->values()->all();
    }

    public function test_a_missing_card_template_falls_back_to_the_plain_invitation(): void
    {
        $this->on();
        $this->metaWithout(['event_invitation_card']);
        [$event] = $this->ecardEvent(['card_photo_mime' => 'image/jpeg']);
        $guest = $this->guestCard($event);

        $r = app(WhatsAppCloudService::class)->sendInvitation($event, $guest, '255712345678');

        $this->assertTrue($r['successful']);
        $this->assertSame(['event_invitation_card:en', 'event_invitation:en'], $this->names());
    }

    public function test_a_swahili_template_saved_as_english_falls_back_to_english(): void
    {
        $this->on();
        $this->metaWithout([], ['event_invitation_sw:sw']);
        [$event] = $this->ecardEvent(['sms_language' => 'sw']);
        $guest = $this->guestCard($event);

        $r = app(WhatsAppCloudService::class)->sendInvitation($event, $guest, '255712345678');

        $this->assertTrue($r['successful']);
        $this->assertSame(['event_invitation_sw:sw', 'event_invitation_sw:en'], $this->names());
    }

    public function test_a_real_problem_is_not_retried_and_shows_the_template_and_code(): void
    {
        $this->on();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 131026, 'message' => 'Undeliverable']], 400)]);
        [$event] = $this->ecardEvent();
        $guest = $this->guestCard($event);

        $r = app(WhatsAppCloudService::class)->sendInvitation($event, $guest, '255712345678');

        $this->assertFalse($r['successful']);
        $this->assertCount(1, Http::recorded());
        $this->assertStringContainsString('(Meta 131026: event_invitation, en)', $r['error']);
    }

    public function test_when_every_option_is_missing_the_host_gets_the_plain_explanation(): void
    {
        $this->on();
        $this->metaWithout(['event_invitation', 'event_invitation_card']);
        [$event] = $this->ecardEvent(['card_photo_mime' => 'image/png']);
        $guest = $this->guestCard($event);

        $r = app(WhatsAppCloudService::class)->sendInvitation($event, $guest, '255712345678');

        $this->assertFalse($r['successful']);
        $this->assertStringContainsString("isn't ready yet", $r['error']);
        $this->assertStringContainsString('Meta 132001', $r['error']);
    }
}
