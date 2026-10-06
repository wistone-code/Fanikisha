<?php

namespace Tests\Feature;

use App\Models\EventAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class CardSecurityAndDesignTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    public function test_revoked_card_shows_a_clear_page_and_is_refused_at_the_door(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event);
        $old = $g->invite_token;

        $this->actingAs($admin)->post(route('delivery.revoke', $g))->assertRedirect();
        $this->assertNull($g->fresh()->invite_token);

        $this->get(route('guest.rsvp', $old))->assertStatus(410)->assertSee('no longer valid');
        $this->actingAs($admin)->postJson(route('checkin.verify'), ['token' => $old])->assertNotFound()->assertJsonPath('revoked', true);
        $this->get(route('guest.rsvp', 'never-existed'))->assertNotFound();
    }

    public function test_reissue_creates_a_new_token_and_kills_the_old_one(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event, ['invite_sent_at' => now(), 'first_opened_at' => now(), 'open_count' => 4]);
        $old = $g->invite_token;

        $this->actingAs($admin)->post(route('delivery.reissue', $g))->assertRedirect();

        $g->refresh();
        $this->assertNotSame($old, $g->invite_token);
        $this->assertNull($g->invite_sent_at);
        $this->assertSame(0, $g->open_count);
        $this->get(route('guest.rsvp', $old))->assertStatus(410);
        $this->get(route('guest.rsvp', $g->invite_token))->assertOk();
    }

    public function test_a_revoked_guest_can_be_given_a_new_card_later(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event);
        $this->actingAs($admin)->post(route('delivery.revoke', $g));
        $this->actingAs($admin)->post(route('delivery.reissue', $g->fresh()))->assertRedirect();

        $this->assertNotNull($g->fresh()->invite_token);
        $this->assertNull($g->fresh()->invite_revoked_at);
    }

    public function test_sync_reports_revoked_cards(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event);
        $old = $g->invite_token;
        $this->actingAs($admin)->post(route('delivery.revoke', $g));

        $this->actingAs($admin)->postJson(route('checkin.sync'), ['scans' => [['token' => $old]]])
            ->assertJsonPath('results.0.status', 'unknown')->assertJsonPath('results.0.revoked', true);
    }

    public function test_rsvp_respond_is_rate_limited(): void
    {
        [$event] = $this->ecardEvent();
        $g = $this->guestCard($event);

        for ($i = 0; $i < 30; $i++) {
            $this->post(route('guest.rsvp.respond', $g->invite_token), ['response' => 'attending'])->assertRedirect();
        }
        $this->post(route('guest.rsvp.respond', $g->invite_token), ['response' => 'attending'])->assertStatus(429);
    }

    // ---- Card design ----------------------------------------------------------------------

    public function test_templates_offered_per_event_type_are_at_least_three(): void
    {
        $svc = app(\App\Services\CardTemplateService::class);

        foreach (\App\Models\Event::TYPES as $type) {
            $this->assertGreaterThanOrEqual(3, count($svc->suitedFor($type)), $type);
            foreach ($svc->forType($type) as $key) {
                $this->assertArrayHasKey($key, \App\Services\CardTemplateService::TEMPLATES);
            }
        }
    }

    public function test_every_template_renders_a_card(): void
    {
        [$event] = $this->ecardEvent();
        $g = $this->guestCard($event);

        foreach (array_keys(\App\Services\CardTemplateService::TEMPLATES) as $key) {
            $event->update(['card_template' => $key]);
            $this->get(route('guest.rsvp', $g->invite_token))->assertOk()->assertSee('tpl-'.$key);
        }
    }

    public function test_card_can_be_viewed_in_swahili_and_english(): void
    {
        [$event] = $this->ecardEvent(['card_default_lang' => 'sw']);
        $g = $this->guestCard($event);

        $this->get(route('guest.rsvp', $g->invite_token))->assertSee('Umealikwa kwenye')->assertSee('Ndiyo, nitakuwepo');
        $this->get(route('guest.rsvp', [$g->invite_token, 'lang' => 'en']))->assertSee('re invited to', false);
    }

    public function test_style_settings_validate_and_save(): void
    {
        [$event, $admin] = $this->ecardEvent();

        $this->actingAs($admin)->patch(route('design.card'), ['card_template' => 'bogus', 'card_default_lang' => 'en'])->assertSessionHasErrors('card_template');
        $this->actingAs($admin)->patch(route('design.card'), ['card_template' => 'floral', 'card_default_lang' => 'sw', 'card_text_sw' => 'Karibuni', 'card_video_url' => 'javascript:alert(1)'])->assertSessionHasErrors('card_video_url');
        $this->actingAs($admin)->patch(route('design.card'), ['card_template' => 'floral', 'card_default_lang' => 'sw', 'card_text_sw' => 'Karibuni', 'card_video_url' => 'https://youtu.be/dQw4w9WgXcQ'])->assertRedirect();

        $this->assertSame('floral', $event->fresh()->card_template);
        $g = $this->guestCard($event);
        $this->get(route('guest.rsvp', $g->invite_token))->assertSee('Karibuni')->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_music_upload_is_served_with_range_support_and_removable(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event);
        $file = UploadedFile::fake()->createWithContent('song.mp3', str_repeat('ID3-audio-bytes', 100))->mimeType('audio/mpeg');

        $this->actingAs($admin)->post(route('design.music.upload'), ['music' => $file])->assertSessionHasNoErrors();
        $this->assertTrue($event->fresh()->card_has_music);

        $this->post('/logout');
        $full = $this->get(route('guest.rsvp.music', $g->invite_token))->assertOk();
        $this->assertSame(1500, strlen($full->getContent()));

        $part = $this->get(route('guest.rsvp.music', $g->invite_token), ['Range' => 'bytes=0-9'])->assertStatus(206);
        $this->assertSame(10, strlen($part->getContent()));
        $this->assertSame('bytes 0-9/1500', $part->headers->get('Content-Range'));

        $this->actingAs($admin)->delete(route('design.music.remove'))->assertRedirect();
        $this->assertFalse($event->fresh()->card_has_music);
        $this->assertSame(0, EventAsset::where('kind', 'music')->count());
    }

    public function test_non_audio_files_are_rejected_as_music(): void
    {
        [, $admin] = $this->ecardEvent();
        $file = UploadedFile::fake()->createWithContent('x.mp3', '<?php echo 1;')->mimeType('text/plain');

        $this->actingAs($admin)->post(route('design.music.upload'), ['music' => $file])->assertSessionHasErrors('music');
    }

    public function test_custom_design_upload_layout_and_guest_rendering(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event, ['name' => 'Latifa Hassan']);
        $img = UploadedFile::fake()->createWithContent('card.jpg', $this->fakeJpeg(1600, 900));

        $this->actingAs($admin)->post(route('design.custom.upload'), ['design' => $img])->assertSessionHasNoErrors();

        $event->refresh();
        $this->assertTrue($event->card_has_custom_design);
        $this->assertSame(1080, $event->custom_design_width); // shrunk to fit
        $this->assertSame(608, $event->custom_design_height);

        $layout = ['name' => ['show' => true, 'x' => 30, 'y' => 20, 'size' => 8, 'color' => '#ff0000'], 'qr' => ['show' => true, 'x' => 70, 'y' => 70, 'size' => 25, 'color' => '#000000'],
            'table' => ['show' => false, 'x' => 1, 'y' => 1, 'size' => 5, 'color' => 'not-a-color'], 'code' => ['show' => true, 'x' => 500, 'y' => -5, 'size' => 99, 'color' => '#000000']];
        $this->actingAs($admin)->patch(route('design.custom.layout'), ['layout' => json_encode($layout), 'use_custom_design' => 1])->assertRedirect();

        $saved = json_decode($event->fresh()->custom_design_layout, true);
        $this->assertSame('#000000', $saved['table']['color']);   // invalid colour replaced
        $this->assertSame(100.0, (float) $saved['code']['x']);     // clamped
        $this->assertSame(0.0, (float) $saved['code']['y']);
        $this->assertSame(60.0, (float) $saved['code']['size']);

        $this->post('/logout');
        $this->get(route('guest.rsvp', $g->invite_token))->assertOk()->assertSee('customCard')->assertSee('Latifa Hassan')->assertSee('color:#ff0000', false);
        $this->get(route('guest.rsvp.design', $g->invite_token))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_layout_cannot_be_saved_without_a_design_and_removal_restores_standard_card(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $this->actingAs($admin)->patch(route('design.custom.layout'), ['layout' => '{}'])->assertNotFound();

        $this->actingAs($admin)->post(route('design.custom.upload'), ['design' => UploadedFile::fake()->createWithContent('c.jpg', $this->fakeJpeg())]);
        $this->actingAs($admin)->delete(route('design.custom.remove'))->assertRedirect();

        $this->assertFalse($event->fresh()->card_has_custom_design);
        $this->assertSame(0, EventAsset::where('kind', 'design')->count());
    }

    public function test_big_assets_are_not_loaded_with_the_event_row(): void
    {
        [$event] = $this->ecardEvent();
        EventAsset::create(['event_id' => $event->id, 'kind' => 'music', 'mime' => 'audio/mpeg', 'data' => 'x']);

        $this->assertArrayNotHasKey('data', $event->fresh()->getAttributes());
    }

    // ---- Venue, calendar ------------------------------------------------------------------

    public function test_venue_pin_is_saved_and_used_for_directions(): void
    {
        [$event, $admin] = $this->ecardEvent(['place' => 'Mlimani City']);
        $g = $this->guestCard($event);

        $this->actingAs($admin)->patch(route('design.venue'), [
            'event_time' => '14:30', 'venue_name' => 'Hall A', 'venue_lat' => '-6.7712', 'venue_lng' => '39.2400', 'landmark_note_en' => 'Next to the bank',
        ])->assertRedirect();

        $event->refresh();
        $this->assertTrue($event->hasMapPin());
        $this->assertStringContainsString('destination=-6.7712000,39.2400000', $event->mapsUrl());

        $this->post('/logout');
        $this->get(route('guest.rsvp', $g->invite_token))->assertSee('destination=-6.7712', false)->assertSee('Next to the bank')->assertSee('14:30');
    }

    public function test_a_pin_needs_both_coordinates(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $this->actingAs($admin)->patch(route('design.venue'), ['venue_lat' => '-6.7', 'venue_lng' => ''])->assertRedirect();
        $this->assertFalse($event->fresh()->hasMapPin());
        $this->actingAs($admin)->patch(route('design.venue'), ['venue_lat' => '95', 'venue_lng' => '39'])->assertSessionHasErrors('venue_lat');
    }

    public function test_calendar_file_contains_the_event_details(): void
    {
        [$event] = $this->ecardEvent(['name' => 'Asha & Juma, Wedding', 'event_time' => '15:00', 'venue_name' => 'Hall A']);
        $g = $this->guestCard($event);

        $res = $this->get(route('guest.rsvp.calendar', $g->invite_token))->assertOk();
        $body = $res->getContent();

        $this->assertStringContainsString('BEGIN:VEVENT', $body);
        $this->assertStringContainsString('SUMMARY:Asha & Juma\, Wedding', $body);
        $this->assertStringContainsString('DTSTART;TZID=Africa/Dar_es_Salaam:'.$event->event_date->format('Ymd').'T150000', $body);
        $this->assertStringContainsString("\r\n", $body);
    }
}
