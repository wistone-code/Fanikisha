<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class AccountSmsTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function beem(): void
    {
        config(['services.beem.api_key' => 'k', 'services.beem.secret_key' => 's']);
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'valid' => 1, 'invalid' => 0, 'request_id' => 1]), 'api.resend.com/*' => Http::response(['id' => 'x'], 200)]);
    }

    private function newAccount(array $extra = []): array
    {
        return $extra + ['name' => 'Neema Mushi', 'username' => 'neema', 'email' => 'neema@example.com', 'phone' => '0712345678', 'package' => config('packages.default')];
    }

    public function test_the_login_sms_fits_in_one_sms(): void
    {
        $user = new User(['username' => 'neemamushi']);
        $text = AccountSms::message($user, 'Ab3xY9kLm2Q7');

        $this->assertLessThanOrEqual(160, strlen($text));
        $this->assertStringContainsString('/login', $text);
        $this->assertStringContainsString('Ab3xY9kLm2Q7', $text);
    }

    public function test_admin_creating_an_account_can_text_the_login_details(): void
    {
        $this->beem();
        $admin = User::factory()->create(['is_super_user' => true]);

        $this->actingAs($admin)->post('/admin/users', $this->newAccount(['send_sms' => '1']))->assertRedirect();

        Http::assertSent(fn ($r) => str_contains((string) $r->url(), 'beem') && str_contains((string) $r['message'], 'Jina/User: neema') && str_contains((string) $r['message'], 'Nenosiri/Password:'));
        $this->assertStringContainsString('sent by SMS', session('status'));
    }

    public function test_no_sms_when_the_box_is_unticked(): void
    {
        $this->beem();
        $admin = User::factory()->create(['is_super_user' => true]);

        $this->actingAs($admin)->post('/admin/users', $this->newAccount())->assertRedirect();

        Http::assertNotSent(fn ($r) => str_contains((string) $r->url(), 'beem'));
        $this->assertDatabaseHas('users', ['username' => 'neema']);
    }

    public function test_adding_a_team_member_texts_them_and_uses_the_events_quota(): void
    {
        $this->beem();
        [$event, $host] = $this->ecardEvent(['sms_quota' => 10, 'sms_sent_count' => 0]);

        $this->actingAs($host)->post('/team', ['name' => 'Baraka', 'username' => 'baraka', 'email' => 'b@example.com', 'phone' => '0765432198', 'role' => 'viewer', 'send_sms' => '1'])->assertRedirect();

        Http::assertSent(fn ($r) => str_contains((string) $r['message'], 'Jina/User: baraka'));
        $this->assertSame(1, (int) $event->fresh()->sms_sent_count);
    }

    public function test_team_member_is_still_added_when_the_quota_is_finished(): void
    {
        $this->beem();
        [$event, $host] = $this->ecardEvent(['sms_quota' => 5, 'sms_sent_count' => 5]);

        $this->actingAs($host)->post('/team', ['name' => 'Baraka', 'username' => 'baraka', 'email' => 'b@example.com', 'phone' => '0765432198', 'role' => 'viewer', 'send_sms' => '1'])->assertRedirect();

        $this->assertDatabaseHas('users', ['username' => 'baraka']);
        Http::assertNotSent(fn ($r) => str_contains((string) $r->url(), 'beem'));
        $this->assertStringContainsString('Quota finished', session('status'));
    }
}
