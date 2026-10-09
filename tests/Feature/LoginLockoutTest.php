<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    private function wrong(string $username = 'maria'): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('login.attempt'), ['username' => $username, 'password' => 'wrong-pass']);
    }

    private function user(): User
    {
        return User::factory()->create(['username' => 'maria', 'password' => 'right-pass-1']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // The per-minute throttle is covered elsewhere; here only the lockout counts.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_four_wrong_passwords_do_not_lock_and_the_right_one_still_works(): void
    {
        $user = $this->user();

        for ($i = 0; $i < 4; $i++) {
            $this->wrong()->assertSessionHasErrors('username');
        }

        $this->assertFalse($user->fresh()->isLocked());
        $this->assertSame(4, $user->fresh()->failed_login_attempts);

        $this->post(route('login.attempt'), ['username' => 'maria', 'password' => 'right-pass-1'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, $user->fresh()->failed_login_attempts);
    }

    public function test_the_fifth_wrong_password_locks_the_account_and_says_so(): void
    {
        $user = $this->user();

        for ($i = 0; $i < 4; $i++) {
            $this->wrong();
        }
        $this->wrong()->assertSessionHasErrors(['username' => 'This account is locked after 5 wrong passwords. Please contact Fanikisha to unlock it.']);

        $this->assertTrue($user->fresh()->isLocked());
        $this->assertTrue(ActivityLog::where('action', 'account.locked')->exists());
    }

    public function test_a_locked_account_refuses_even_the_right_password(): void
    {
        $user = $this->user();
        $user->forceFill(['locked_at' => now(), 'failed_login_attempts' => 5])->save();

        $this->post(route('login.attempt'), ['username' => 'maria', 'password' => 'right-pass-1'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_a_correct_sign_in_resets_the_counter(): void
    {
        $user = $this->user();

        for ($i = 0; $i < 3; $i++) {
            $this->wrong();
        }
        $this->post(route('login.attempt'), ['username' => 'maria', 'password' => 'right-pass-1']);
        $this->post(route('logout'));

        for ($i = 0; $i < 4; $i++) {
            $this->wrong();
        }

        $this->assertFalse($user->fresh()->isLocked());
    }

    public function test_unknown_usernames_never_lock_anything_or_error(): void
    {
        $this->wrong('nobody')->assertSessionHasErrors('username');
        $this->assertSame(0, User::where('locked_at', '!=', null)->count());
    }

    public function test_only_the_system_admin_can_unlock_and_the_person_can_sign_in_again(): void
    {
        $super = User::factory()->superUser()->create();
        $user = $this->user();
        $user->forceFill(['locked_at' => now(), 'failed_login_attempts' => 5])->save();

        $this->actingAs($user)->post(route('admin.users.unlock', $user))->assertForbidden();
        $this->assertTrue($user->fresh()->isLocked());

        $this->actingAs($super)->post(route('admin.users.unlock', $user))->assertSessionHas('status');
        $this->assertFalse($user->fresh()->isLocked());
        $this->assertSame(0, $user->fresh()->failed_login_attempts);
        $this->assertTrue(ActivityLog::where('action', 'account.unlocked')->exists());

        auth()->logout();
        $this->post(route('login.attempt'), ['username' => 'maria', 'password' => 'right-pass-1'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_users_list_can_be_filtered_to_locked_and_suspended_accounts(): void
    {
        $this->withoutVite();
        $super = User::factory()->superUser()->create();
        $locked = User::factory()->create(['name' => 'Lockedperson', 'locked_at' => now()]);
        $suspended = User::factory()->create(['name' => 'Suspendedperson', 'is_suspended' => true]);
        $plain = User::factory()->create(['name' => 'Plainperson']);

        $html = $this->actingAs($super)->get(route('admin.users.index', ['status' => 'locked']))->assertOk()->getContent();
        $this->assertStringContainsString('Lockedperson', $html);
        $this->assertStringNotContainsString('Suspendedperson', $html);
        $this->assertStringNotContainsString('Plainperson', $html);
        $this->assertStringContainsString('Unlock account', $html);

        $html = $this->actingAs($super)->get(route('admin.users.index', ['status' => 'suspended']))->assertOk()->getContent();
        $this->assertStringContainsString('Suspendedperson', $html);
        $this->assertStringNotContainsString('Lockedperson', $html);
    }
}
