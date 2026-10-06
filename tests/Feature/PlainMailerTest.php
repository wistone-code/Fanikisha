<?php

namespace Tests\Feature;

use App\Models\AccountRequest;
use App\Services\PlainMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlainMailerTest extends TestCase
{
    use RefreshDatabase;

    private function request(array $over = []): array
    {
        return $over + [
            'name' => 'Asha Mushi', 'phone' => '0712 345 678', 'email' => 'asha@example.com',
            'event_type' => 'wedding', 'needs' => 'ecards', 'consent' => '1',
        ];
    }

    public function test_with_a_resend_key_mail_goes_over_https_not_smtp(): void
    {
        config(['services.resend.key' => 're_test', 'company.email' => 'info@fanikisha.app']);
        Http::fake(['api.resend.com/*' => Http::response(['id' => 'x'], 200)]);
        Mail::fake();

        $this->post('/request-account', $this->request())->assertRedirect(route('home'));

        // One mail to the company inbox (Reply-To = the visitor) and one confirmation to the visitor.
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.resend.com/emails'
            && $r['to'] === ['info@fanikisha.app']
            && str_contains($r['subject'], 'account request')
            && str_contains($r['text'], 'Asha Mushi')
            && $r['reply_to'] === ['Asha Mushi <asha@example.com>']);
        Http::assertSent(fn ($r) => $r['to'] === ['asha@example.com']);
        Mail::assertNothingSent();
        $this->assertSame(1, AccountRequest::count());
    }

    public function test_data_requests_use_the_same_path(): void
    {
        config(['services.resend.key' => 're_test', 'company.email' => 'info@fanikisha.app']);
        Http::fake(['api.resend.com/*' => Http::response(['id' => 'x'], 200)]);

        $this->post('/data-request', ['type' => 'access', 'phone' => '0712111222'])->assertRedirect();

        Http::assertSent(fn ($r) => $r['to'] === ['info@fanikisha.app'] && str_contains($r['subject'], 'data request'));
    }

    public function test_a_failing_mail_service_never_loses_the_request_or_shows_an_error(): void
    {
        config(['services.resend.key' => 're_test']);
        Http::fake(['api.resend.com/*' => Http::response(['message' => 'domain not verified'], 403)]);
        Log::spy();

        $this->post('/request-account', $this->request())->assertRedirect(route('home'))->assertSessionHasNoErrors();

        $this->assertSame(1, AccountRequest::count());
        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    public function test_without_a_key_it_falls_back_to_the_laravel_mailer(): void
    {
        config(['services.resend.key' => null]);
        Mail::fake();

        $this->assertTrue(app(PlainMailer::class)->send('info@fanikisha.app', 'Hello', 'Body'));
        $this->assertFalse(app(PlainMailer::class)->send('', 'Hello', 'Body'));
    }
}
