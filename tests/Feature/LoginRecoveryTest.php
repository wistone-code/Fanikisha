<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Sign-in never ends on a dead-end error screen. */
class LoginRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_old_sign_in_form_returns_to_the_form_with_the_username_kept(): void
    {
        // Simulate the stale-token check failing for this request, as it does in production for an old form.
        $this->app->instance(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, new class
        {
            public function handle($request, $next)
            {
                throw new TokenMismatchException('CSRF token mismatch.');
            }
        });

        $this->post(route('login.attempt'), ['username' => 'mary', 'password' => 'x'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'tap Sign in again'))
            ->assertSessionHasInput('username', 'mary');
    }

    public function test_the_sign_in_form_is_never_stored(): void
    {
        $this->get(route('login'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_too_many_attempts_shows_a_message_on_the_form(): void
    {
        User::factory()->create(['username' => 'mary']);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.attempt'), ['username' => 'mary', 'password' => 'nope']);
        }

        $this->post(route('login.attempt'), ['username' => 'mary', 'password' => 'nope'])->assertRedirect(route('login'));
        $this->followingRedirects()->get(route('login'))->assertSee('Too many sign-in attempts');
    }

    public function test_not_allowed_page_explains_and_offers_a_way_back(): void
    {
        Route::get('/_test403', fn () => abort(403, "You don't have permission to do that."))->middleware('web');

        $this->get('/_test403')->assertStatus(403)->assertSee('Not allowed')->assertSee('Only the event Admin')->assertSee('Go back');
    }
}
