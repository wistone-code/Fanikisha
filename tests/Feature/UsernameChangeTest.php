<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class UsernameChangeTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function change(User $user, string $name, string $pw = 'password'): void
    {
        $this->actingAs($user)->patch(route('account.username.update'), ['username' => $name, 'current_password' => $pw]);
    }

    public function test_every_role_can_change_its_own_username(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $viewer = $this->memberOf($event, 'viewer');
        $door = $this->memberOf($event, 'scanner');
        $super = User::factory()->create(['is_super_user' => true]);

        foreach ([[$admin, 'new.admin'], [$viewer, 'new.viewer'], [$door, 'new.door'], [$super, 'new.super']] as [$u, $name]) {
            $this->change($u, $name);
            $this->assertSame($name, $u->fresh()->username, $name);
        }
    }

    public function test_the_current_password_is_needed_and_the_name_must_be_free(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $viewer = $this->memberOf($event, 'viewer');
        $old = $viewer->username;

        $this->change($viewer, 'wrong.pw', 'not-the-password');
        $this->assertSame($old, $viewer->fresh()->username);

        $this->change($viewer, $admin->username);
        $this->assertSame($old, $viewer->fresh()->username);
    }

    public function test_viewers_see_account_settings_but_not_email_and_phone(): void
    {
        [$event] = $this->ecardEvent();
        $viewer = $this->memberOf($event, 'viewer');
        $html = $this->actingAs($viewer)->get(route('dashboard'))->getContent();

        $this->assertStringContainsString('Save username', $html);
        $this->assertStringNotContainsString('Save email', $html);
        $this->assertStringNotContainsString('Save phone', $html);
    }

    public function test_the_system_admin_page_has_a_username_form(): void
    {
        $super = User::factory()->create(['is_super_user' => true]);
        $this->actingAs($super)->get(route('admin.account'))->assertOk()->assertSee('Save username');
    }
}
