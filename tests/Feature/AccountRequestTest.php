<?php

namespace Tests\Feature;

use App\Models\AccountRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountRequestTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $over = []): array
    {
        return $over + [
            'name' => 'Asha Mushi', 'phone' => '0712 345 678', 'email' => 'asha@example.com',
            'event_type' => 'wedding', 'event_date' => now()->addMonths(2)->toDateString(),
            'location' => 'Arusha', 'guests' => 250, 'needs' => 'both', 'message' => 'Hello', 'consent' => '1',
        ];
    }

    public function test_landing_page_button_opens_the_form_in_both_languages(): void
    {
        $this->get('/')->assertOk()->assertSee(route('account-request'), false);
        $this->get('/request-account')->assertOk()->assertSee('Request an account')->assertSee('Event date');
        $this->get('/request-account?lang=sw')->assertOk()->assertSee('Tarehe ya tukio');
    }

    public function test_a_visitor_can_send_a_request_and_the_team_is_emailed(): void
    {
        Mail::fake();

        $this->post('/request-account', $this->payload())->assertRedirect(route('home'))->assertSessionHas('account_requested');

        $this->get('/')->assertOk()->assertSee('We received your request');

        $r = AccountRequest::first();
        $this->assertSame('new', $r->status);
        $this->assertSame('+255712345678', $r->phone);
        $this->assertSame(250, $r->guests);
        $this->assertSame('both', $r->needs);
    }

    public function test_required_fields_consent_and_honeypot_are_enforced(): void
    {
        $this->post('/request-account', $this->payload(['name' => '']))->assertSessionHasErrors('name');
        $this->post('/request-account', $this->payload(['phone' => 'abc']))->assertSessionHasErrors('phone');
        $this->post('/request-account', $this->payload(['consent' => null]))->assertSessionHasErrors('consent');
        $this->post('/request-account', $this->payload(['event_type' => 'bogus']))->assertSessionHasErrors('event_type');
        $this->post('/request-account', $this->payload(['event_date' => '2020-01-01']))->assertSessionHasErrors('event_date');
        $this->assertSame(0, AccountRequest::count());
    }

    public function test_the_honeypot_field_rejects_bots(): void
    {
        $this->post('/request-account', $this->payload(['website' => 'spam.example']))->assertSessionHasErrors('website');
        $this->assertSame(0, AccountRequest::count());
    }

    public function test_the_email_shows_what_was_chosen_not_a_code(): void
    {
        config(['services.resend.key' => 're_test', 'company.email' => 'info@fanikisha.app']);
        \Illuminate\Support\Facades\Http::fake(['api.resend.com/*' => \Illuminate\Support\Facades\Http::response(['id' => 'x'], 200)]);

        $this->post('/request-account', $this->payload(['needs' => 'both']))->assertSessionHasNoErrors();

        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r['to'] === ['info@fanikisha.app']
            && str_contains($r['text'], 'plus pledges and contributions (full account)')
            && ! str_contains($r['text'], 'Needs: both'));
    }

    public function test_email_is_optional(): void
    {
        Mail::fake();
        $this->post('/request-account', $this->payload(['email' => null]))->assertSessionHasNoErrors();
        $this->assertNull(AccountRequest::first()->email);
    }
}
