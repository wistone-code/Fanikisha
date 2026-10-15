<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class EventExpiryTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    public function test_a_card_opens_on_the_event_day_and_expires_at_midnight_after_it(): void
    {
        [$event] = $this->ecardEvent(['event_date' => today()->toDateString()]);
        $guest = $this->guestCard($event);

        $this->get(route('guest.rsvp', $guest->invite_token))->assertOk();

        $this->travelTo(today()->addDay()->startOfDay());
        $this->get(route('guest.rsvp', $guest->invite_token))->assertStatus(410)->assertSee('This invitation has ended');
        $this->post(route('guest.rsvp.respond', $guest->invite_token), ['response' => 'attending'])->assertStatus(410);
        $this->get(route('guest.rsvp.calendar', $guest->invite_token))->assertStatus(410);
        $this->assertNull($guest->fresh()->rsvp_status);
    }

    public function test_a_card_still_opens_one_minute_before_midnight(): void
    {
        [$event] = $this->ecardEvent(['event_date' => today()->toDateString()]);
        $guest = $this->guestCard($event);

        $this->travelTo(today()->endOfDay()->subMinute());
        $this->get(route('guest.rsvp', $guest->invite_token))->assertOk();
    }

    public function test_a_guest_can_still_opt_out_of_messages_after_the_card_expired(): void
    {
        [$event] = $this->ecardEvent(['event_date' => today()->subDays(3)->toDateString()]);
        $guest = $this->guestCard($event, ['phone' => '0712345678']);

        $this->post(route('guest.rsvp.stop', $guest->invite_token))->assertRedirect();
        $this->assertTrue(app(\App\Services\OptOutService::class)->isOptedOut('0712345678'));
    }

    public function test_the_account_stays_open_for_a_month_with_a_warning_after_sign_in(): void
    {
        [$event, $host] = $this->ecardEvent(['event_date' => today()->subDays(5)->toDateString()]);
        $host->forceFill(['password' => bcrypt('Secret#123')])->save();

        $this->post(route('login.attempt'), ['username' => $host->username, 'password' => 'Secret#123'])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'This account closes on'));

        $this->actingAs($host)->get(route('dashboard'))->assertOk()->assertSee('This account closes on');
    }

    public function test_after_a_month_signing_in_is_refused_with_a_clear_message(): void
    {
        [$event, $host] = $this->ecardEvent(['event_date' => today()->subMonth()->subDay()->toDateString()]);
        $host->forceFill(['password' => bcrypt('Secret#123')])->save();

        $this->post(route('login.attempt'), ['username' => $host->username, 'password' => 'Secret#123'])
            ->assertSessionHasErrors(['username' => 'This account closed on '.$event->accountClosesOn()->format('j F Y').', one month after the event. Contact Fanikisha (info@fanikisha.app) if you need it opened again.']);
        $this->assertGuest();
    }

    public function test_a_signed_in_user_is_signed_out_once_the_account_closes(): void
    {
        [$event, $host] = $this->ecardEvent(['event_date' => today()->subMonth()->subDay()->toDateString()]);

        $this->actingAs($host)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_system_admin_can_keep_an_account_open_longer_and_the_system_admin_is_never_closed(): void
    {
        [$event, $host] = $this->ecardEvent(['event_date' => today()->subMonth()->subDay()->toDateString()]);
        $super = User::factory()->create(['is_super_user' => true]);

        $this->actingAs($super)->patch(route('admin.users.sms-quota', $host), ['sms_quota' => '', 'account_active_until' => today()->addDays(10)->toDateString()])->assertSessionHasNoErrors();

        $this->assertFalse($event->fresh()->accountClosed());
        $this->actingAs($host)->get(route('dashboard'))->assertOk();
        $this->assertNull($super->closedEvent());
    }

    public function test_no_closing_banner_before_the_event_has_taken_place(): void
    {
        [$event, $host] = $this->ecardEvent(['event_date' => today()->addDays(3)->toDateString()]);

        $this->actingAs($host)->get(route('dashboard'))->assertOk()->assertDontSee('This account closes on');
    }

    public function test_the_photo_wall_link_stops_opening_seven_days_after_the_event(): void
    {
        [$event] = $this->ecardEvent(['event_date' => today()->subDays(7)->toDateString(), 'photo_wall_enabled' => true, 'photo_wall_token' => 'WALLTOKEN123', 'photo_wall_open_mode' => 'always']);

        // Day 7 after the event is the last day it opens.
        $this->get(route('wall.show', 'WALLTOKEN123'))->assertOk();

        $this->travelTo(today()->addDay()->startOfDay());
        $this->get(route('wall.show', 'WALLTOKEN123'))->assertStatus(410)->assertSee('This photo wall has closed');
        $this->post(route('wall.upload', 'WALLTOKEN123'))->assertStatus(410);
    }

    public function test_the_hosts_wall_settings_accept_one_to_seven_days_only(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $base = ['photo_wall_enabled' => 1, 'photo_wall_access' => 'link', 'photo_wall_open_mode' => 'always', 'photo_wall_max_per_guest' => 5, 'photo_wall_max_total' => 50];

        $this->actingAs($admin)->patch(route('photos.update'), $base + ['photo_wall_close_days' => 30])->assertSessionHasErrors('photo_wall_close_days');
        $this->actingAs($admin)->patch(route('photos.update'), $base + ['photo_wall_close_days' => 7])->assertSessionHasNoErrors();
    }
}
