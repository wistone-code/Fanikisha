<?php

namespace Tests\Feature;

use App\Models\DataRequest;
use App\Models\MessageOptOut;
use App\Models\User;
use App\Services\BeemSmsService;
use App\Services\OptOutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    public function test_landing_and_legal_pages_are_public(): void
    {
        $this->get('/')->assertOk()->assertSee('Fanikisha');
        $this->get('/?lang=sw')->assertOk()->assertSee('Ingia');
        $this->get('/data-request')->assertOk();

        foreach (['/privacy' => 'Privacy Policy', '/terms' => 'Terms of Service', '/acceptable-use' => 'Acceptable Use and Messaging Policy'] as $url => $title) {
            $html = $this->get($url)->assertOk()->assertSee($title)->getContent();
            $this->assertStringNotContainsString('%LEGAL_NAME%', $html, "$url left a placeholder");
            $this->assertStringNotContainsString('%EMAIL%', $html, "$url left a placeholder");
            $this->assertStringNotContainsString('[CONFIRM', $html, "$url shows a draft placeholder");
        }
    }

    public function test_legal_tables_render_as_html_tables(): void
    {
        $this->get('/privacy')->assertSee('<table>', false)->assertSee('Suppression list', false);
    }

    public function test_company_details_come_from_config(): void
    {
        config(['company.legal_name' => 'Acme Events Ltd', 'company.registration' => 'BRELA 12345', 'company.pdpc_certificate' => 'PDPC/99']);

        $this->get('/privacy')->assertSee('Acme Events Ltd')->assertSee('BRELA 12345')->assertSee('PDPC/99');
        $this->get('/')->assertSee('Acme Events Ltd');
    }

    public function test_signed_in_user_is_sent_from_home_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_dashboard_still_requires_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_stop_request_waits_for_admin_approval_and_is_not_applied_automatically(): void
    {
        Mail::fake();

        $this->post(route('data-request.store'), ['type' => 'stop', 'phone' => '0712 345 678'])
            ->assertRedirect()->assertSessionHas('sent', 'received');

        $this->assertFalse(app(OptOutService::class)->isOptedOut('+255712345678'));
        $this->assertDatabaseHas('data_requests', ['type' => 'stop', 'status' => 'new']);
    }

    public function test_data_request_needs_a_way_to_reach_the_person_and_ignores_bots(): void
    {
        $this->post(route('data-request.store'), ['type' => 'delete'])->assertSessionHasErrors('phone');
        $this->post(route('data-request.store'), ['type' => 'delete', 'phone' => '0712345678', 'website' => 'spam'])->assertSessionHasErrors('website');

        $this->assertSame(0, DataRequest::count());
    }

    public function test_stop_button_on_the_card_suppresses_that_guest(): void
    {
        [$event] = $this->ecardEvent();
        $g = $this->guestCard($event, ['phone' => '+255712000111']);

        $this->get(route('guest.rsvp', $g->invite_token))->assertSee('Stop messages');
        $this->post(route('guest.rsvp.stop', $g->invite_token))->assertRedirect();
        $this->get(route('guest.rsvp', $g->invite_token))->assertSee('Done. You will not receive more messages');

        $this->assertSame(1, MessageOptOut::count());
        $this->assertTrue(app(OptOutService::class)->isOptedOut('0712000111'));
    }

    public function test_sms_skips_suppressed_numbers(): void
    {
        config(['services.beem.api_key' => 'k', 'services.beem.secret_key' => 's']);
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'valid' => 1, 'invalid' => 0, 'request_id' => 1])]);
        app(OptOutService::class)->add('0712000111', 'card');

        $sms = app(BeemSmsService::class);

        $none = $sms->sendBulk('Hi', collect([(object) ['phone' => '0712000111']]));
        $this->assertFalse($none['successful']);
        Http::assertNothingSent();

        $sms->sendBulk('Hi', collect([(object) ['phone' => '0712000111'], (object) ['phone' => '0713999888']]));
        Http::assertSent(function ($request) {
            $r = $request['recipients'];

            return count($r) === 1 && $r[0]['dest_addr'] === '255713999888' && $r[0]['recipient_id'] === '1';
        });
    }

    public function test_whatsapp_button_is_blocked_for_a_suppressed_guest(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event, ['phone' => '+255712000111']);
        app(OptOutService::class)->add($g->phone, 'card');

        $this->actingAs($admin)->get(route('guests.whatsapp', $g))->assertRedirect()->assertSessionHas('error');
    }
}
