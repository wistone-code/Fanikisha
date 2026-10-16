<?php

namespace Tests\Feature;

use App\Models\Pledge;
use App\Models\User;
use App\Rules\ValidPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class PhoneRequiredTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    public function test_the_rule_accepts_real_numbers_and_rejects_rubbish(): void
    {
        foreach (['0712 345 678', '+255712345678', '255712345678', '712345678'] as $ok) {
            $this->assertTrue(ValidPhone::ok($ok), $ok);
        }
        foreach (['', 'abc', '123', null] as $bad) {
            $this->assertFalse(ValidPhone::ok($bad), (string) $bad);
        }
    }

    public function test_accounts_cannot_be_created_without_a_phone(): void
    {
        $admin = User::factory()->create(['is_super_user' => true]);
        $this->actingAs($admin)->post('/admin/users', ['name' => 'A', 'username' => 'a1', 'email' => 'a@example.com', 'package' => config('packages.default')])->assertSessionHasErrors('phone');
        $this->assertDatabaseMissing('users', ['username' => 'a1']);
    }

    public function test_team_members_guests_pledges_and_providers_need_a_phone(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $this->actingAs($admin)->post(route('team.store'), ['name' => 'T', 'username' => 't1', 'email' => 't@example.com', 'role' => 'viewer'])->assertSessionHasErrors('phone');
        $this->actingAs($admin)->post(route('guests.store'), ['name' => 'No Phone', 'card_type' => 'single'])->assertSessionHasErrors('phone');
        $this->actingAs($admin)->post(route('guests.store'), ['name' => 'Bad Phone', 'phone' => 'abc', 'card_type' => 'single'])->assertSessionHasErrors('phone');
        $this->actingAs($admin)->post(route('guests.store'), ['name' => 'Good Phone', 'phone' => '0712345678', 'card_type' => 'single'])->assertSessionHasNoErrors();
        $this->assertSame(0, Pledge::where('name', 'No Phone')->count());
    }

    public function test_imports_skip_lines_without_a_valid_phone(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $this->actingAs($admin)->post(route('guests.import'), ['import_text' => "Has Phone 0712345678\nNo Phone Here"])->assertSessionHas('status');
        $this->assertSame(1, Pledge::where('event_id', $event->id)->count());
        $this->assertStringContainsString('valid phone', session('status'));
    }

    public function test_the_public_data_request_needs_a_phone(): void
    {
        $this->post('/data-request', ['type' => 'access', 'email' => 'x@example.com'])->assertSessionHasErrors('phone');
    }
}
