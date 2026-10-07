<?php

namespace Tests\Feature;

use App\Models\EventPhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class PhotoWallAndAfterEventTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function wallEvent(array $attrs = []): array
    {
        return $this->ecardEvent(array_merge([
            'photo_wall_enabled' => true, 'photo_wall_token' => 'WALLTOKEN123', 'photo_wall_open_mode' => 'always',
            'photo_wall_max_per_guest' => 3, 'photo_wall_max_total' => 100,
        ], $attrs));
    }

    private function photo(int $n = 1): array
    {
        return array_map(fn ($i) => UploadedFile::fake()->createWithContent("p{$i}.jpg", $this->fakeJpeg(800, 600)), range(1, $n));
    }

    private function beem(): void
    {
        config(['services.beem.api_key' => 'k', 'services.beem.secret_key' => 's']);
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'valid' => 1, 'invalid' => 0])]);
    }

    // ---- Photo wall -----------------------------------------------------------------------

    public function test_admin_turning_it_on_creates_a_secret_link(): void
    {
        [$event, $admin] = $this->ecardEvent();

        $this->actingAs($admin)->patch(route('photos.update'), [
            'photo_wall_enabled' => 1, 'photo_wall_access' => 'link', 'photo_wall_open_mode' => 'always',
            'photo_wall_close_days' => 7, 'photo_wall_max_per_guest' => 5, 'photo_wall_max_total' => 50,
        ])->assertRedirect();

        $this->assertSame(32, strlen($event->fresh()->photo_wall_token));
        $this->get(route('wall.show', $event->fresh()->photo_wall_token))->assertOk()->assertSee($event->name);
    }

    public function test_wall_is_404_when_off_or_link_is_wrong(): void
    {
        [$event] = $this->wallEvent(['photo_wall_enabled' => false]);
        $this->get(route('wall.show', 'WALLTOKEN123'))->assertNotFound();
        $event->update(['photo_wall_enabled' => true]);
        $this->get(route('wall.show', 'nope'))->assertNotFound();
    }

    public function test_guest_uploads_photos_which_are_resized_and_stored(): void
    {
        [$event] = $this->wallEvent();

        $this->postJson(route('wall.upload', 'WALLTOKEN123'), ['photos' => $this->photo(2), 'name' => 'Amina'])->assertOk()->assertJsonPath('saved', 2);

        $this->assertSame(2, $event->photos()->count());
        $p = $event->photos()->first();
        $this->assertSame('Amina', $p->uploader_name);
        $this->assertNotEmpty($p->thumb);
        $this->assertStringStartsWith("\xFF\xD8", $p->image); // JPEG
    }

    public function test_per_guest_limit_is_enforced_across_uploads(): void
    {
        [$event] = $this->wallEvent();
        $card = $this->guestCard($event, ['name' => 'Hawa']);

        $this->postJson(route('wall.upload', 'WALLTOKEN123'), ['photos' => $this->photo(2), 'c' => $card->invite_token])->assertOk();
        $this->postJson(route('wall.upload', 'WALLTOKEN123'), ['photos' => $this->photo(2), 'c' => $card->invite_token])->assertOk()->assertJsonPath('saved', 1);
        $this->postJson(route('wall.upload', 'WALLTOKEN123'), ['photos' => $this->photo(1), 'c' => $card->invite_token])->assertStatus(422);

        $this->assertSame(3, $event->photos()->count());
        $this->assertSame('Hawa', $event->photos()->first()->uploader_name);
    }

    public function test_wall_total_cap_and_non_images_are_refused(): void
    {
        [$event] = $this->wallEvent(['photo_wall_max_total' => 10]);
        for ($i = 0; $i < 10; $i++) {
            EventPhoto::create(['event_id' => $event->id, 'uploader_key' => "k{$i}", 'thumb' => 'x', 'image' => 'x']);
        }

        $this->postJson(route('wall.upload', 'WALLTOKEN123'), ['photos' => $this->photo(1)])->assertStatus(403)->assertJsonPath('message', 'The photo wall is full.');

        $event->update(['photo_wall_max_total' => 100]);
        $bad = UploadedFile::fake()->createWithContent('x.jpg', 'not an image')->mimeType('text/plain');
        $this->postJson(route('wall.upload', 'WALLTOKEN123'), ['photos' => [$bad]])->assertStatus(422);
    }

    public function test_uploads_wait_for_the_event_day_and_stop_after_the_closing_period(): void
    {
        [$event] = $this->wallEvent(['photo_wall_open_mode' => 'event_day', 'event_date' => now()->addDays(3)->toDateString(), 'photo_wall_close_days' => 2]);
        $this->postJson(route('wall.upload', 'WALLTOKEN123'), ['photos' => $this->photo(1)])->assertStatus(403);

        $event->update(['event_date' => now()->toDateString()]);
        $this->postJson(route('wall.upload', 'WALLTOKEN123'), ['photos' => $this->photo(1)])->assertOk();

        $event->update(['event_date' => now()->subDays(5)->toDateString()]);
        $this->postJson(route('wall.upload', 'WALLTOKEN123'), ['photos' => $this->photo(1)])->assertStatus(403);
    }

    public function test_organiser_can_pause_uploads(): void
    {
        [$event] = $this->wallEvent(['photo_wall_uploads_blocked' => true]);
        $this->postJson(route('wall.upload', 'WALLTOKEN123'), ['photos' => $this->photo(1)])->assertStatus(403);
    }

    public function test_pin_gates_viewing_and_uploading(): void
    {
        [$event] = $this->wallEvent(['photo_wall_pin' => '4321']);
        $p = EventPhoto::create(['event_id' => $event->id, 'uploader_key' => 'k', 'thumb' => 'x', 'image' => 'x']);

        $this->get(route('wall.show', 'WALLTOKEN123'))->assertSee('Enter the PIN');
        $this->get(route('wall.thumb', ['WALLTOKEN123', $p->id]))->assertForbidden();
        $this->postJson(route('wall.upload', 'WALLTOKEN123'), ['photos' => $this->photo(1)])->assertForbidden();

        $this->post(route('wall.pin', 'WALLTOKEN123'), ['pin' => '0000'])->assertSessionHasErrors('pin');
        $this->post(route('wall.pin', 'WALLTOKEN123'), ['pin' => '4321'])->assertRedirect();

        $this->get(route('wall.thumb', ['WALLTOKEN123', $p->id]))->assertOk();
        $this->postJson(route('wall.upload', 'WALLTOKEN123'), ['photos' => $this->photo(1)])->assertOk();
    }

    public function test_guests_only_mode_needs_a_valid_card(): void
    {
        [$event] = $this->wallEvent(['photo_wall_access' => 'guests']);
        $card = $this->guestCard($event);

        $this->get(route('wall.show', 'WALLTOKEN123'))->assertSee('Open this from your invitation card');
        $this->postJson(route('wall.upload', 'WALLTOKEN123'), ['photos' => $this->photo(1)])->assertForbidden();
        $this->get(route('wall.show', ['WALLTOKEN123', 'c' => 'bogus']))->assertSee('Open this from your invitation card');

        $this->get(route('wall.show', ['WALLTOKEN123', 'c' => $card->invite_token]))->assertOk()->assertSee('Add your photos');
        $this->postJson(route('wall.upload', 'WALLTOKEN123'), ['photos' => $this->photo(1)])->assertOk();
    }

    public function test_hidden_photos_are_not_public_and_reports_auto_hide(): void
    {
        [$event] = $this->wallEvent();
        $p = EventPhoto::create(['event_id' => $event->id, 'uploader_key' => 'k', 'thumb' => 'x', 'image' => 'x']);

        $this->get(route('wall.full', ['WALLTOKEN123', $p->id]))->assertOk();
        foreach (range(1, 3) as $i) {
            $this->flushSession(); // three different people
            $this->postJson(route('wall.report', ['WALLTOKEN123', $p->id]))->assertOk();
        }

        $this->assertTrue($p->fresh()->hidden);
        $this->get(route('wall.full', ['WALLTOKEN123', $p->id]))->assertNotFound();
    }

    public function test_photo_from_another_wall_cannot_be_read_through_this_one(): void
    {
        [$event] = $this->wallEvent();
        [$other] = $this->wallEvent(['photo_wall_token' => 'OTHERWALL']);
        $p = EventPhoto::create(['event_id' => $other->id, 'uploader_key' => 'k', 'thumb' => 'x', 'image' => 'x']);

        $this->get(route('wall.full', ['WALLTOKEN123', $p->id]))->assertNotFound();
    }

    public function test_organiser_moderates_and_downloads(): void
    {
        [$event, $admin] = $this->wallEvent();
        $p = EventPhoto::create(['event_id' => $event->id, 'uploader_key' => 'k', 'thumb' => 'x', 'image' => $this->fakeJpeg()]);
        [$other] = $this->wallEvent(['photo_wall_token' => 'OTHERWALL']);
        $foreign = EventPhoto::create(['event_id' => $other->id, 'uploader_key' => 'k', 'thumb' => 'x', 'image' => 'x']);

        $this->actingAs($admin)->post(route('photos.toggle-hidden', $p))->assertRedirect();
        $this->assertTrue($p->fresh()->hidden);
        $this->actingAs($admin)->post(route('photos.toggle-hidden', $foreign))->assertNotFound();
        $this->actingAs($admin)->delete(route('photos.destroy', $foreign))->assertNotFound();

        $this->actingAs($admin)->post(route('photos.toggle-hidden', $p));
        $this->actingAs($admin)->get(route('photos.download'))->assertOk()->assertHeader('content-disposition');
        $this->actingAs($admin)->delete(route('photos.destroy', $p))->assertRedirect();
        $this->assertSame(0, $event->photos()->count());
    }

    public function test_new_link_invalidates_the_old_one(): void
    {
        [$event, $admin] = $this->wallEvent();
        $this->actingAs($admin)->post(route('photos.new-link'))->assertRedirect();

        $this->get(route('wall.show', 'WALLTOKEN123'))->assertNotFound();
        $this->get(route('wall.show', $event->fresh()->photo_wall_token))->assertOk();
    }

    public function test_card_links_to_the_wall_only_when_it_is_open(): void
    {
        [$event] = $this->wallEvent();
        $g = $this->guestCard($event);

        $this->get(route('guest.rsvp', $g->invite_token))->assertSee('/wall/WALLTOKEN123?c='.$g->invite_token, false);
        $event->update(['photo_wall_uploads_blocked' => true]);
        $this->get(route('guest.rsvp', $g->invite_token))->assertDontSee('/wall/WALLTOKEN123', false);
    }

    // ---- Thank-yous -----------------------------------------------------------------------

    public function test_thank_you_command_sends_once_with_the_right_wording(): void
    {
        $this->beem();
        $this->travelTo(now()->setTime(10, 0));
        [$event] = $this->ecardEvent(['thank_you_enabled' => true, 'thank_you_time' => '09:00', 'event_date' => now()->subDay()->toDateString(), 'host_names' => 'Asha & Juma']);
        $came = $this->guestCard($event, ['phone' => '255712345678', 'checked_in_at' => now()->subDay()]);
        $absent = $this->guestCard($event, ['phone' => '255712345679']);
        $this->guestCard($event, ['phone' => null]);

        $this->artisan('thankyou:send-due')->assertSuccessful();
        $this->artisan('thankyou:send-due')->assertSuccessful();

        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => str_contains($r['message'], 'thank you for celebrating') && str_contains($r['message'], 'Asha & Juma'));
        Http::assertSent(fn ($r) => str_contains($r['message'], 'we missed you'));
        $this->assertNotNull($came->fresh()->thank_you_sent_at);
        $this->assertNotNull($absent->fresh()->thank_you_sent_at);
    }

    public function test_thank_you_waits_for_the_morning_after_and_stops_after_two_weeks(): void
    {
        $this->beem();
        $this->travelTo(now()->setTime(8, 0));
        [$event] = $this->ecardEvent(['thank_you_enabled' => true, 'thank_you_time' => '09:00', 'event_date' => now()->subDay()->toDateString()]);
        $this->guestCard($event, ['phone' => '255712345678']);

        $this->artisan('thankyou:send-due')->assertSuccessful();
        Http::assertNothingSent();

        $event->update(['event_date' => now()->subDays(20)->toDateString()]);
        $this->travelTo(now()->setTime(12, 0));
        $this->artisan('thankyou:send-due')->assertSuccessful();
        Http::assertNothingSent();

        $event->update(['event_date' => now()->toDateString()]); // event day itself: not yet
        $this->artisan('thankyou:send-due')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_thank_you_can_acknowledge_contributions_for_contribution_events(): void
    {
        $this->beem();
        $event = \App\Models\Event::factory()->create(['event_date' => now()->subDay()->toDateString(), 'thank_you_acknowledge_paid' => true]);
        $p = \App\Models\Pledge::factory()->create(['event_id' => $event->id, 'paid' => 50000, 'amount' => 50000, 'checked_in_at' => now()]);

        $text = app(\App\Services\MessageTemplateService::class)->forThankYou($event, $p);

        $this->assertStringContainsString('contribution of 50,000', $text);
    }

    public function test_manual_send_now_refuses_before_the_event(): void
    {
        $this->beem();
        [$event, $admin] = $this->ecardEvent();
        $this->guestCard($event, ['phone' => '255712345678']);

        $this->actingAs($admin)->post(route('after.send'))->assertSessionHas('error');
        Http::assertNothingSent();

        $event->update(['event_date' => now()->toDateString()]);
        $this->actingAs($admin)->post(route('after.send'))->assertSessionHas('status');
        Http::assertSentCount(1);
    }

    public function test_after_event_settings_save_and_recap_renders_numbers(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $this->guestCard($event, ['rsvp_status' => 'attending', 'checked_in_at' => now(), 'invite_sent_at' => now(), 'first_opened_at' => now(), 'meal_choice' => 'Beef']);
        $this->guestCard($event, ['rsvp_status' => 'attending', 'name' => 'Said Noshow']);
        $this->guestCard($event, ['rsvp_status' => 'not_attending']);

        $this->actingAs($admin)->patch(route('after.update'), ['host_names' => 'The Family', 'thank_you_time' => '10:30', 'thank_you_enabled' => 1])->assertRedirect();
        $this->assertTrue($event->fresh()->thank_you_enabled);
        $this->assertSame('10:30', $event->fresh()->thank_you_time);

        $this->actingAs($admin)->get(route('after.recap'))->assertOk()->assertSee('Said Noshow')->assertSee('Beef')->assertSee('Said yes, did not come');
    }

    // ---- Event-day reminder ---------------------------------------------------------------

    public function test_event_day_reminder_goes_out_on_the_day_once_and_skips_decliners(): void
    {
        $this->beem();
        $this->travelTo(now()->setTime(8, 0));
        [$event] = $this->ecardEvent(['event_day_reminder_enabled' => true, 'event_day_reminder_time' => '07:00', 'event_date' => now()->toDateString(), 'event_time' => '14:00', 'venue_name' => 'Hall A']);
        $yes = $this->guestCard($event, ['phone' => '255712345678']);
        $this->guestCard($event, ['phone' => '255712345679', 'rsvp_status' => 'not_attending']);

        $this->artisan('reminders:event-day')->assertSuccessful();
        $this->artisan('reminders:event-day')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r['message'], 'today is') && str_contains($r['message'], '14:00') && str_contains($r['message'], $yes->invite_token));
        $this->assertNotNull($yes->fresh()->event_day_reminder_sent_at);
    }

    public function test_event_day_reminder_not_before_its_time_or_on_another_day(): void
    {
        $this->beem();
        $this->travelTo(now()->setTime(6, 0));
        [$event] = $this->ecardEvent(['event_day_reminder_enabled' => true, 'event_day_reminder_time' => '07:00', 'event_date' => now()->toDateString()]);
        $this->guestCard($event, ['phone' => '255712345678']);

        $this->artisan('reminders:event-day')->assertSuccessful();
        Http::assertNothingSent();

        $event->update(['event_date' => now()->addDay()->toDateString()]);
        $this->travelTo(now()->setTime(9, 0));
        $this->artisan('reminders:event-day')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_event_day_reminder_settings_validate(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $this->actingAs($admin)->patch(route('design.day-reminder'), ['event_day_reminder_time' => 'soon'])->assertSessionHasErrors('event_day_reminder_time');
        $this->actingAs($admin)->patch(route('design.day-reminder'), ['event_day_reminder_time' => '06:30', 'event_day_reminder_enabled' => 1])->assertRedirect();
        $this->assertTrue($event->fresh()->event_day_reminder_enabled);
    }

    public function test_scheduled_sms_uses_the_events_own_quota_not_a_logged_in_event(): void
    {
        $this->beem();
        $this->travelTo(now()->setTime(8, 0));
        [$event] = $this->ecardEvent(['event_day_reminder_enabled' => true, 'event_day_reminder_time' => '07:00', 'event_date' => now()->toDateString(), 'sms_quota' => 1]);
        $this->guestCard($event, ['phone' => '255712345678']);
        $this->guestCard($event, ['phone' => '255712345679']);

        $this->artisan('reminders:event-day')->assertSuccessful();

        // Needs 2, has 1: the one it can afford is sent and counted, the other waits for more quota instead of nobody being texted.
        Http::assertSentCount(1);
        $this->assertSame(1, $event->fresh()->sms_sent_count);
        $this->assertSame(1, $event->pledges()->whereNotNull('event_day_reminder_sent_at')->count());
    }
}
