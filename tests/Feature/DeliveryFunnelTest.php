<?php

namespace Tests\Feature;

use App\Models\Pledge;
use App\Services\CardTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class DeliveryFunnelTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private const HUMAN = 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36';

    private function beem(): void
    {
        config(['services.beem.api_key' => 'k', 'services.beem.secret_key' => 's']);
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'valid' => 1, 'invalid' => 0, 'request_id' => 1])]);
    }

    public function test_opening_a_card_records_first_open_and_count(): void
    {
        [$event] = $this->ecardEvent();
        $g = $this->guestCard($event);

        $this->withHeader('User-Agent', self::HUMAN)->get(route('guest.rsvp', $g->invite_token))->assertOk();
        $this->withHeader('User-Agent', self::HUMAN)->get(route('guest.rsvp', $g->invite_token))->assertOk();

        $g->refresh();
        $this->assertNotNull($g->first_opened_at);
        $this->assertSame(2, $g->open_count);
    }

    public function test_link_preview_bots_and_logged_in_users_do_not_count_as_opens(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event);

        $this->withHeader('User-Agent', 'WhatsApp/2.23 A')->get(route('guest.rsvp', $g->invite_token))->assertOk();
        $this->actingAs($admin)->withHeader('User-Agent', self::HUMAN)->get(route('guest.rsvp', $g->invite_token))->assertOk();

        $this->assertNull($g->fresh()->first_opened_at);
        $this->assertSame(0, $g->fresh()->open_count);
    }

    public function test_funnel_stage_progresses(): void
    {
        [$event] = $this->ecardEvent();
        $g = $this->guestCard($event);
        $this->assertSame('not_sent', $g->funnelStage());
        $g->invite_sent_at = now();
        $this->assertSame('sent', $g->funnelStage());
        $g->first_opened_at = now();
        $this->assertSame('opened', $g->funnelStage());
        $g->rsvp_status = 'attending';
        $this->assertSame('responded', $g->funnelStage());
        $g->checked_in_at = now();
        $this->assertSame('arrived', $g->funnelStage());
    }

    public function test_delivery_page_shows_counts_and_admin_actions(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $this->guestCard($event, ['invite_sent_at' => now()]);
        $this->guestCard($event);

        $this->actingAs($admin)->get(route('delivery.index'))
            ->assertOk()->assertSee('Card delivery')->assertSee('Send to all not yet sent (1)');
    }

    public function test_send_all_texts_unsent_guests_and_marks_them_sent(): void
    {
        $this->beem();
        [$event, $admin] = $this->ecardEvent();
        $a = $this->guestCard($event, ['phone' => '255712345678']);
        $b = $this->guestCard($event, ['phone' => '255712345679', 'invite_sent_at' => now()]);

        $this->actingAs($admin)->post(route('delivery.send-all'))->assertRedirect();

        $this->assertNotNull($a->fresh()->invite_sent_at);
        $this->assertSame('sms', $a->fresh()->invite_channel);
        Http::assertSentCount(1);
        $this->assertSame(1, $event->fresh()->sms_sent_count);
    }

    public function test_send_all_respects_sms_quota(): void
    {
        $this->beem();
        [$event, $admin] = $this->ecardEvent(['sms_quota' => 1]);
        $this->guestCard($event, ['phone' => '255712345678']);
        $this->guestCard($event, ['phone' => '255712345679']);

        $this->actingAs($admin)->post(route('delivery.send-all'))->assertSessionHas('error');
        Http::assertNothingSent();
    }

    public function test_remind_unopened_only_texts_sent_but_unopened(): void
    {
        $this->beem();
        [$event, $admin] = $this->ecardEvent();
        $unopened = $this->guestCard($event, ['phone' => '255712345678', 'invite_sent_at' => now()]);
        $this->guestCard($event, ['phone' => '255712345679', 'invite_sent_at' => now(), 'first_opened_at' => now()]);
        $this->guestCard($event, ['phone' => '255712345670']);

        $this->actingAs($admin)->post(route('delivery.remind-unopened'))->assertRedirect();

        Http::assertSentCount(1);
        $this->assertNotNull($unopened->fresh()->unopened_reminded_at);
    }

    public function test_auto_reminder_command_runs_once_per_guest_in_the_window(): void
    {
        $this->beem();
        $this->travelTo(now()->setTime(10, 0));
        [$event] = $this->ecardEvent(['auto_remind_unopened' => true, 'auto_remind_unopened_days' => 3, 'event_date' => now()->addDays(2)->toDateString()]);
        $g = $this->guestCard($event, ['phone' => '255712345678', 'invite_sent_at' => now()->subDay()]);

        $this->artisan('cards:remind-unopened')->assertSuccessful();
        $this->artisan('cards:remind-unopened')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertNotNull($g->fresh()->unopened_reminded_at);
    }

    public function test_auto_reminder_not_sent_when_event_is_further_away_than_the_setting(): void
    {
        $this->beem();
        $this->travelTo(now()->setTime(10, 0));
        [$event] = $this->ecardEvent(['auto_remind_unopened' => true, 'auto_remind_unopened_days' => 3, 'event_date' => now()->addDays(9)->toDateString()]);
        $this->guestCard($event, ['phone' => '255712345678', 'invite_sent_at' => now()]);

        $this->artisan('cards:remind-unopened')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_mark_sent_and_whatsapp_link_mark_a_guest_sent(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $a = $this->guestCard($event, ['phone' => '255712345678']);
        $b = $this->guestCard($event, ['phone' => '255712345679']);

        $this->actingAs($admin)->post(route('delivery.mark-sent', $a))->assertRedirect();
        $this->actingAs($admin)->get(route('guests.whatsapp', $b))->assertRedirect();

        $this->assertSame('manual', $a->fresh()->invite_channel);
        $this->assertSame('whatsapp', $b->fresh()->invite_channel);
    }

    public function test_csv_export_neutralises_spreadsheet_formulas_and_includes_details(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $this->guestCard($event, ['name' => '=HYPERLINK("http://x")', 'rsvp_status' => 'attending', 'plus_ones' => 2, 'meal_choice' => 'Chicken']);

        $csv = $this->actingAs($admin)->get(route('guests.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('Chicken', $csv);
        $this->assertStringContainsString('Total people', $csv);
    }

    public function test_viewer_can_see_delivery_but_not_act(): void
    {
        [$event] = $this->ecardEvent();
        $viewer = $this->memberOf($event, 'viewer');
        $g = $this->guestCard($event);

        $this->actingAs($viewer)->get(route('delivery.index'))->assertOk()->assertDontSee('Send to all not yet sent');
        $this->actingAs($viewer)->post(route('delivery.mark-sent', $g))->assertForbidden();
    }

    public function test_cannot_touch_another_events_guest(): void
    {
        [, $admin] = $this->ecardEvent();
        [$other] = $this->ecardEvent();
        $foreign = $this->guestCard($other);

        $this->actingAs($admin)->post(route('delivery.revoke', $foreign))->assertNotFound();
        $this->assertNotNull($foreign->fresh()->invite_token);
    }

    public function test_card_opened_on_many_devices_is_flagged_as_possibly_forwarded(): void
    {
        [$event] = $this->ecardEvent();
        $g = $this->guestCard($event);
        foreach (['a', 'b', 'c'] as $d) {
            DB::table('card_views')->insert(['pledge_id' => $g->id, 'device_hash' => $d, 'created_at' => now()]);
        }
        DB::table('card_views')->insert(['pledge_id' => $g->id, 'device_hash' => 'old', 'created_at' => now()->subDays(60)]);

        $shared = app(CardTracker::class)->sharedCards($event->id);
        $this->assertSame([$g->id => 3], $shared);

        $this->assertSame(1, app(CardTracker::class)->prune());
    }

    public function test_repeat_scan_after_check_in_is_counted(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event, ['checked_in_at' => now()]);

        $this->actingAs($admin)->postJson(route('checkin.verify'), ['token' => $g->invite_token])->assertJsonPath('already', true);
        $this->assertSame(1, $g->fresh()->scan_attempts);
    }
}
