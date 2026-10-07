<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

/** The classic dropdown menus: system admin navigation and the account menu. */
class NavMenuTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    public function test_system_admin_menu_is_grouped_and_links_to_every_admin_page(): void
    {
        $admin = User::factory()->create(['is_super_user' => true]);

        $res = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();

        $res->assertSee('id="navMenu"', false)->assertSee('cm-menu', false)
            ->assertSee('Manage')->assertSee('Compliance')->assertSee('Your account')
            ->assertSee('Dashboard &amp; accounts', false)->assertSee('Activity logs')->assertSee('Privacy &amp; opt-outs', false)
            ->assertSee('Account settings')->assertSee('Log out')
            ->assertSee('href="'.route('admin.logs.index').'"', false)
            ->assertSee('href="'.route('admin.compliance').'"', false)
            ->assertSee('href="'.route('admin.account').'"', false);
    }

    public function test_the_menu_logout_still_signs_the_admin_out(): void
    {
        $admin = User::factory()->create(['is_super_user' => true]);

        $this->actingAs($admin)->post(route('logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_event_accounts_get_the_same_styled_account_menu(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $viewer = $this->memberOf($event, 'viewer');

        $this->actingAs($admin)->get(route('guests.index'))->assertOk()
            ->assertSee('id="accountMenu"', false)->assertSee('cm-menu', false)->assertSee('Account settings')->assertSee('Log out');

        $this->actingAs($viewer)->get(route('guests.index'))->assertOk()
            ->assertSee('id="accountMenu"', false)->assertSee('Log out')->assertDontSee('fa-gear"></i> Account settings', false);
    }
}
