<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WhatsAppCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppConnectionCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_token_is_cleaned_of_quotes_spaces_line_breaks_and_bearer(): void
    {
        config(['services.whatsapp.token' => "  \"Bearer EAAB cd\nef\"  ", 'services.whatsapp.phone_number_id' => ' 123 456x ']);
        $svc = app(WhatsAppCloudService::class);

        $this->assertSame('EAABcdef', $svc->token());
        $this->assertSame('123456', $svc->phoneNumberId());
        $this->assertTrue($svc->isConfigured());
    }

    public function test_sending_uses_the_cleaned_token_and_number(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        config(['services.whatsapp.token' => '"EAAXYZ"', 'services.whatsapp.phone_number_id' => ' 999 ']);

        app(WhatsAppCloudService::class)->sendTemplate('255712345678', 'event_invitation', 'en', ['A', 'B', 'C', 'D', 'E']);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/999/messages') && $r->hasHeader('Authorization', 'Bearer EAAXYZ'));
    }

    public function test_diagnose_reports_a_good_connection(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['display_phone_number' => '+255 676 056 651', 'verified_name' => 'Fanikisha', 'quality_rating' => 'GREEN'])]);
        config(['services.whatsapp.token' => 'EAAgood', 'services.whatsapp.phone_number_id' => '555']);

        $r = app(WhatsAppCloudService::class)->diagnose();

        $this->assertTrue($r['ok']);
        $this->assertStringContainsString('+255 676 056 651', implode(' ', $r['lines']));
    }

    public function test_diagnose_explains_an_expired_token_and_never_shows_it(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Error validating access token: Session has expired', 'code' => 190, 'error_subcode' => 463]], 401)]);
        config(['services.whatsapp.token' => 'EAAsecret123', 'services.whatsapp.phone_number_id' => '555']);

        $r = app(WhatsAppCloudService::class)->diagnose();
        $text = implode(' ', $r['lines']);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('code 190/463', $text);
        $this->assertStringContainsString('permanent System User token', $text);
        $this->assertStringNotContainsString('EAAsecret123', $text);
    }

    public function test_diagnose_flags_missing_settings(): void
    {
        config(['services.whatsapp.token' => '', 'services.whatsapp.phone_number_id' => '']);

        $r = app(WhatsAppCloudService::class)->diagnose();

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('WHATSAPP_TOKEN: missing', implode(' ', $r['lines']));
    }

    public function test_only_the_system_admin_can_run_the_check_and_sees_the_result(): void
    {
        $this->withoutVite();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token', 'code' => 190]], 400)]);
        config(['services.whatsapp.token' => 'EAAbad', 'services.whatsapp.phone_number_id' => '555']);

        $this->actingAs(User::factory()->create())->post(route('admin.whatsapp.check'))->assertForbidden();

        $super = User::factory()->superUser()->create();
        $this->actingAs($super)->post(route('admin.whatsapp.check'))->assertSessionHas('whatsapp_check');
        $this->actingAs($super)->withSession(['whatsapp_check' => ['ok' => false, 'lines' => ['Meta refused it: code 190']]])
            ->get(route('admin.account'))->assertOk()->assertSee('Not working yet')->assertSee('code 190');
    }
}
