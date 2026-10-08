<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventMember;
use App\Models\Pledge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

/** Sales packages: Full (everything), SMS (no e-card features), E-card (no money side). */
class PackagesTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function superUser(): User
    {
        return User::factory()->create(['is_super_user' => true]);
    }

    private function smsEvent(): array
    {
        $event = Event::factory()->create(['package' => 'sms', 'mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);

        return [$event, $this->memberOf($event, 'admin')];
    }

    private function pledge(Event $event, array $attrs = []): Pledge
    {
        return Pledge::factory()->create(array_merge(['event_id' => $event->id, 'amount' => 100000, 'paid' => 0, 'pay_token' => Str::random(32)], $attrs));
    }

    private function eventPayload(array $extra = []): array
    {
        return array_merge(['mode' => 'contributions', 'name' => 'Test Event', 'event_type' => 'Wedding', 'place' => 'Moshi', 'event_date' => now()->addDays(30)->toDateString(), 'pledge_deadline' => now()->addDays(20)->toDateString()], $extra);
    }

    // ---- account creation -------------------------------------------------------------------

    public function test_the_system_admin_picks_a_package_when_creating_an_account(): void
    {
        Mail::fake();

        $this->actingAs($this->superUser())->post(route('admin.users.store'), ['name' => 'Sam', 'username' => 'sam1', 'email' => 'sam@example.com', 'package' => 'sms'])->assertSessionHas('status');
        $this->assertSame('sms', User::where('username', 'sam1')->first()->package);

        $this->actingAs($this->superUser())->post(route('admin.users.store'), ['name' => 'Fay', 'username' => 'fay1', 'email' => 'fay@example.com'])->assertSessionHas('status');
        $this->assertSame('full', User::where('username', 'fay1')->first()->package);
    }

    public function test_an_unknown_package_is_rejected(): void
    {
        $this->actingAs($this->superUser())->post(route('admin.users.store'), ['name' => 'X', 'username' => 'x1', 'email' => 'x@example.com', 'package' => 'gold'])->assertSessionHasErrors('package');
    }

    public function test_the_create_form_offers_the_three_packages(): void
    {
        $this->actingAs($this->superUser())->get(route('admin.users.index'))->assertOk()
            ->assertSee('Full package')->assertSee('SMS package')->assertSee('E-card package');
    }

    public function test_the_event_an_account_creates_follows_its_package(): void
    {
        $sms = User::factory()->create(['package' => 'sms']);
        $this->actingAs($sms)->post(route('event.store'), $this->eventPayload(['mode' => 'ecard']))->assertRedirect();
        $e = Event::latest('id')->first();
        $this->assertSame('sms', $e->package);
        $this->assertSame('contributions', $e->mode);

        $ecard = User::factory()->create(['package' => 'ecard']);
        $this->actingAs($ecard)->post(route('event.store'), $this->eventPayload(['mode' => 'contributions']))->assertRedirect();
        $e = Event::latest('id')->first();
        $this->assertSame('ecard', $e->package);
        $this->assertSame('ecard', $e->mode);

        $full = User::factory()->create(['package' => 'full']);
        $this->actingAs($full)->post(route('event.store'), $this->eventPayload())->assertRedirect();
        $e = Event::latest('id')->first();
        $this->assertSame('full', $e->package);
        $this->assertSame('contributions', $e->mode);
    }

    public function test_the_create_event_page_only_lets_a_full_account_choose(): void
    {
        $this->actingAs(User::factory()->create(['package' => 'full']))->get(route('event.create'))->assertOk()->assertSee('Full event management');
        $this->actingAs(User::factory()->create(['package' => 'sms']))->get(route('event.create'))->assertOk()->assertDontSee('Full event management')->assertSee('SMS package');
    }

    // ---- SMS package: what is off and on ----------------------------------------------------

    public function test_sms_package_cannot_open_card_features(): void
    {
        [, $admin] = $this->smsEvent();

        foreach (['delivery.index', 'seating.index', 'photos.index', 'design.index', 'after.index', 'checkin.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertForbidden()->assertSee('Not included in your package');
        }
        $this->actingAs($admin)->get(route('guests.index', ['tab' => 'rsvp']))->assertForbidden();
    }

    public function test_sms_package_keeps_the_money_side_and_the_invitation_page(): void
    {
        [$event, $admin] = $this->smsEvent();
        $this->pledge($event, ['name' => 'Asha Pledger']);

        foreach (['dashboard', 'pledges.index', 'financial.index', 'providers.index', 'committees.index', 'schedule.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
        $this->actingAs($admin)->get(route('guests.index'))->assertOk()->assertSee('Asha Pledger')
            ->assertDontSee('Delivery')->assertDontSee('Seating')->assertDontSee('Card design')->assertDontSee('Invitation link');
    }

    public function test_sms_invitations_are_plain_text_with_no_card_link(): void
    {
        config(['services.beem.api_key' => 'k', 'services.beem.secret_key' => 's']);
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'valid' => 1, 'invalid' => 0, 'request_id' => 1])]);
        [$event, $admin] = $this->smsEvent();
        $p = $this->pledge($event, ['name' => 'Asha', 'phone' => '255712345678', 'invite_token' => null]);

        $this->actingAs($admin)->post(route('guests.sms', $p))->assertSessionHas('status');

        Http::assertSent(function ($request) {
            return str_contains($request['message'], 'Asha') && ! str_contains($request['message'], 'http') && ! str_contains($request['message'], '{link}') && str_contains($request['message'], 'look forward');
        });
        $this->assertNotNull($p->fresh()->invite_sent_at);
    }

    public function test_sms_package_has_no_whatsapp_button_but_full_package_does(): void
    {
        [$event, $admin] = $this->smsEvent();
        $p = $this->pledge($event, ['phone' => '255712345678']);

        $html = $this->actingAs($admin)->get(route('guests.index'))->assertOk()->assertSee('SMS')->getContent();
        $this->assertStringNotContainsString('/whatsapp', $html);
        $this->assertStringNotContainsString('fa-whatsapp', $html);
        $this->actingAs($admin)->get(route('guests.whatsapp', $p))->assertNotFound();

        $full = Event::factory()->create(['package' => 'full', 'mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);
        $fullAdmin = $this->memberOf($full, 'admin');
        $fp = $this->pledge($full, ['phone' => '255712345678', 'invite_token' => Str::random(32)]);
        $this->actingAs($fullAdmin)->get(route('guests.index'))->assertOk()->assertSee('guests/'.$fp->id.'/whatsapp', false);
    }

    public function test_sms_package_cannot_activate_card_links(): void
    {
        [$event, $admin] = $this->smsEvent();
        $p = $this->pledge($event, ['invite_token' => null]);

        $this->actingAs($admin)->post(route('guests.send-invite', $p))->assertForbidden();
        $this->assertNull($p->fresh()->invite_token);
    }

    public function test_public_card_and_photo_wall_are_unavailable_on_the_sms_package(): void
    {
        [$event] = $this->smsEvent();
        $g = $this->pledge($event, ['invite_token' => Str::random(32)]);
        $event->forceFill(['photo_wall_enabled' => true, 'photo_wall_token' => 'wallwall'])->save();

        $this->get(route('guest.rsvp', $g->invite_token))->assertStatus(410)->assertSee('no longer available');
        $this->get(route('wall.show', 'wallwall'))->assertNotFound();
    }

    // ---- Full + E-card -----------------------------------------------------------------------

    public function test_full_package_keeps_every_feature(): void
    {
        $event = Event::factory()->create(['package' => 'full', 'mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);
        $admin = $this->memberOf($event, 'admin');

        foreach (['delivery.index', 'seating.index', 'photos.index', 'design.index', 'after.index', 'checkin.index', 'pledges.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_ecard_package_has_cards_but_no_money_side(): void
    {
        [$event, $admin] = $this->ecardEvent(['package' => 'ecard']);

        $this->assertTrue($event->hasFeature('cards'));
        $this->assertFalse($event->hasFeature('money'));
        $this->actingAs($admin)->get(route('delivery.index'))->assertOk();
    }

    // ---- changing package --------------------------------------------------------------------

    public function test_changing_package_updates_the_account_and_its_event_and_keeps_all_data(): void
    {
        $event = Event::factory()->create(['package' => 'full', 'mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);
        $owner = $this->memberOf($event, 'admin');
        $owner->update(['package' => 'full']);
        $this->pledge($event);
        $super = $this->superUser();

        $this->actingAs($super)->patch(route('admin.users.package', $owner), ['package' => 'sms'])->assertSessionHas('status');
        $this->assertSame('sms', $owner->fresh()->package);
        $this->assertSame('sms', $event->fresh()->package);
        $this->actingAs($owner)->get(route('seating.index'))->assertForbidden();
        $this->assertSame(1, $event->pledges()->count());

        $this->actingAs($super)->patch(route('admin.users.package', $owner), ['package' => 'full']);
        $this->actingAs($owner)->get(route('seating.index'))->assertOk();
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.package_changed']);
    }

    public function test_moving_to_the_ecard_package_switches_the_event_to_ecard_mode(): void
    {
        $event = Event::factory()->create(['package' => 'full', 'mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);
        $owner = $this->memberOf($event, 'admin');

        $this->actingAs($this->superUser())->patch(route('admin.users.package', $owner), ['package' => 'ecard'])->assertSessionHas('status');

        $this->assertSame('ecard', $event->fresh()->mode);
        $this->assertSame('ecard', $event->fresh()->package);
    }

    public function test_only_the_system_admin_can_change_a_package(): void
    {
        [, $organiser] = $this->smsEvent();
        $other = User::factory()->create();

        $this->actingAs($organiser)->patch(route('admin.users.package', $other), ['package' => 'full'])->assertForbidden();
        $this->actingAs($this->superUser())->patch(route('admin.users.package', $other), ['package' => 'gold'])->assertSessionHasErrors('package');
    }

    public function test_in_app_manual_shows_only_what_the_package_includes(): void
    {
        [$event, $admin] = $this->smsEvent();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('User Manual')->assertSee(route('manual'), false);
        $this->actingAs($admin)->get(route('manual'))->assertOk()
            ->assertSee('Reminders and Pay now')->assertSee('Meeting invitation')
            ->assertDontSee('Seating plan')->assertDontSee('Photo wall');

        $full = Event::factory()->create(['package' => 'full', 'mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);
        $fullAdmin = $this->memberOf($full, 'admin');
        $this->actingAs($fullAdmin)->get(route('manual'))->assertOk()
            ->assertSee('Seating plan')->assertSee('Photo wall')->assertSee('Check-in at the entrance');

        $viewer = $this->memberOf($full, 'viewer');
        $this->actingAs($viewer)->get(route('manual'))->assertOk()->assertDontSee('Team management')->assertDontSee('Setting up your event');
    }
}
