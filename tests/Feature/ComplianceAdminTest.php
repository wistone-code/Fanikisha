<?php

namespace Tests\Feature;

use App\Models\DataRequest;
use App\Models\MessageOptOut;
use App\Models\User;
use App\Services\OptOutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class ComplianceAdminTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function superUser(): User
    {
        return User::factory()->create(['is_super_user' => true]);
    }

    public function test_only_the_system_admin_can_open_the_compliance_desk(): void
    {
        [, $organiser] = $this->ecardEvent();

        $this->get(route('admin.compliance'))->assertRedirect(route('login'));
        $this->actingAs($organiser)->get(route('admin.compliance'))->assertForbidden();
        $this->actingAs($this->superUser())->get(route('admin.compliance'))->assertOk()->assertSee('Privacy');
    }

    public function test_admin_sees_requests_and_flags_late_ones(): void
    {
        $fresh = DataRequest::create(['type' => 'delete', 'phone' => '0712111222', 'status' => 'new']);
        $old = DataRequest::create(['type' => 'access', 'phone' => '0713333444', 'status' => 'acknowledged']);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();

        $this->assertSame('ok', $fresh->urgency());
        $this->assertSame('overdue', $old->fresh()->urgency());

        $this->actingAs($this->superUser())->get(route('admin.compliance'))
            ->assertSee('0712111222')->assertSee('Over 30 days');
    }

    public function test_acknowledge_and_close_update_status_and_email_the_requester(): void
    {
        Mail::fake();
        $admin = $this->superUser();
        $r = DataRequest::create(['type' => 'delete', 'phone' => '0712111222', 'email' => 'guest@example.com', 'status' => 'new']);

        $this->actingAs($admin)->patch(route('admin.compliance.request', $r), ['action' => 'acknowledge'])->assertRedirect();
        $this->assertSame('acknowledged', $r->fresh()->status);

        $this->actingAs($admin)->patch(route('admin.compliance.request', $r), ['action' => 'close', 'note' => 'Deleted from 2 events.'])->assertRedirect();
        $r->refresh();
        $this->assertSame('closed', $r->status);
        $this->assertSame('Deleted from 2 events.', $r->note);
        $this->assertNotNull($r->closed_at);

        $this->actingAs($admin)->patch(route('admin.compliance.request', $r), ['action' => 'reopen']);
        $this->assertSame('acknowledged', $r->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'compliance.request_close']);
    }

    public function test_admin_can_add_and_remove_blocked_numbers(): void
    {
        $admin = $this->superUser();

        $this->actingAs($admin)->post(route('admin.compliance.blocked.add'), ['phone' => '0712 000 555'])->assertRedirect();
        $this->assertTrue(app(OptOutService::class)->isOptedOut('+255712000555'));
        $this->assertSame('admin', MessageOptOut::first()->source);

        $this->actingAs($admin)->post(route('admin.compliance.blocked.add'), ['phone' => 'abc'])->assertSessionHasErrors('phone');

        $this->actingAs($admin)->delete(route('admin.compliance.blocked.remove', MessageOptOut::first()))->assertRedirect();
        $this->assertFalse(app(OptOutService::class)->isOptedOut('0712000555'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'compliance.optout_removed']);
    }

    public function test_blocked_tab_lists_and_searches_numbers(): void
    {
        app(OptOutService::class)->add('0712000555', 'card');
        app(OptOutService::class)->add('0713999888', 'request');

        $this->actingAs($this->superUser())->get(route('admin.compliance', ['tab' => 'blocked', 'q' => '999']))
            ->assertOk()->assertSee('+255713999888')->assertDontSee('+255712000555');
    }

    public function test_admin_approves_and_unblocks_a_stop_request(): void
    {
        $admin = $this->superUser();
        $r = \App\Models\DataRequest::create(['type' => 'stop', 'phone' => '0712 777 888', 'status' => 'new']);

        $this->actingAs($admin)->get(route('admin.compliance'))->assertSee('Approve &amp; block', false)->assertSee('not blocked yet');

        $this->actingAs($admin)->patch(route('admin.compliance.request', $r), ['action' => 'block'])->assertRedirect();
        $this->assertTrue(app(OptOutService::class)->isOptedOut('+255712777888'));
        $this->assertSame('acknowledged', $r->fresh()->status);
        // The Unblock form asks for confirmation; its action must travel in a hidden field, not only in the button.
        $this->actingAs($admin)->get(route('admin.compliance'))->assertSee('Unblock')->assertSee('<input type="hidden" name="action" value="unblock">', false);

        $this->actingAs($admin)->patch(route('admin.compliance.request', $r), ['action' => 'unblock'])->assertRedirect();
        $this->assertFalse(app(OptOutService::class)->isOptedOut('0712777888'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'compliance.request_unblock']);
    }
}
