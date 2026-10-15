<?php

namespace Tests\Feature;

use App\Services\BeemSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsPlainTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_special_characters_become_plain_keyboard_characters(): void
    {
        $this->assertSame(
            'Dear Jose, thank you for celebrating with us. Your presence made the day special! - Asha & Juma',
            BeemSmsService::plainText('Dear José, thank you for celebrating with us. Your presence made the day special! — Asha & Juma'),
        );
        $this->assertSame('"Yes" it\'s ok... - fine', BeemSmsService::plainText("\u{201C}Yes\u{201D} it\u{2019}s ok\u{2026} \u{2013} fine"));
        $this->assertSame('Habari Amina, asante!', BeemSmsService::plainText('Habari Amina, asante! 🎉'));
        $this->assertSame("Line one\nLine two", BeemSmsService::plainText("Line one\nLine two"));
    }

    public function test_the_text_sent_to_beem_is_plain_so_the_unicode_error_cannot_happen(): void
    {
        config(['services.beem.api_key' => 'k', 'services.beem.secret_key' => 's']);
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'valid' => 1])]);

        $r = app(BeemSmsService::class)->sendSingle('Dear Neema, thank you — we missed you.', '0712345678');

        $this->assertTrue($r['successful']);
        Http::assertSent(fn ($req) => $req['message'] === 'Dear Neema, thank you - we missed you.' && $req['encoding'] === 0);
    }
}
