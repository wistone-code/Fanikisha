<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MessageOptOut;
use App\Models\Pledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

/** A sweep over every guest-management feature: adding, importing, editing, sending, tracking, security and permissions. */
class GuestManagementAuditTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function beem(int $valid = 1): void
    {
        config(['services.beem.api_key' => 'k', 'services.beem.secret_key' => 's']);
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'valid' => $valid, 'invalid' => 0, 'request_id' => 1])]);
    }

    private function fullEvent(array $attrs = []): array
    {
        $event = Event::factory()->create(array_merge(['mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()], $attrs));

        return [$event, $this->memberOf($event, 'admin')];
    }

    private function pledger(Event $event, array $attrs = []): Pledge
    {
        return Pledge::factory()->create(array_merge(['event_id' => $event->id, 'amount' => 100000, 'paid' => 0, 'pay_token' => Str::random(32)], $attrs));
    }

    // ---- E-card account: add, import, edit, remove ----------------------------------------

    public function test_ecard_guest_is_added_with_live_link_and_validated(): void
    {
        [$event, $admin] = $this->ecardEvent();

        $this->actingAs($admin)->post(route('guests.store'), ['name' => 'Neema', 'phone' => '0754111222', 'card_type' => 'double'])->assertSessionHas('status');
        $g = Pledge::where('name', 'Neema')->first();
        $this->assertSame('+255754111222', $g->phone);
        $this->assertSame('double', $g->card_type);
        $this->assertNotNull($g->invite_token);
        $this->assertNotNull($g->pay_token);

        $this->actingAs($admin)->post(route('guests.store'), ['name' => '', 'card_type' => 'single'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('guests.store'), ['name' => 'X', 'card_type' => 'vip'])->assertSessionHasErrors('card_type');
        $this->assertSame(1, $event->pledges()->count());
    }

    public function test_ecard_guest_routes_are_closed_to_viewers_and_to_other_account_types(): void
    {
        [$event] = $this->ecardEvent();
        $viewer = $this->memberOf($event, 'viewer');
        $this->actingAs($viewer)->post(route('guests.store'), ['name' => 'Nope', 'card_type' => 'single'])->assertForbidden();

        [, $fullAdmin] = $this->fullEvent();
        $this->actingAs($fullAdmin)->post(route('guests.store'), ['name' => 'Nope', 'card_type' => 'single'])->assertNotFound();
        $this->actingAs($fullAdmin)->post(route('guests.import'), ['import_text' => 'A'])->assertNotFound();
        $this->assertSame(0, Pledge::where('name', 'Nope')->count());
    }

    public function test_pasted_import_skips_header_and_blank_rows_and_reads_card_type(): void
    {
        [$event, $admin] = $this->ecardEvent();

        $this->actingAs($admin)->post(route('guests.import'), ['import_text' => "Name, Phone, Card\nAsha, 0712000111, double\n\n,0712000222\nBen\tNo-phone-here"])
            ->assertSessionHas('status');

        $this->assertSame(2, $event->pledges()->count());
        $this->assertSame('double', Pledge::where('name', 'Asha')->first()->card_type);
        $this->assertSame('single', Pledge::where('name', 'Ben')->first()->card_type);
        $this->assertStringContainsString('skipped 2', session('status'));
    }

    public function test_csv_file_import_and_limits(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $file = UploadedFile::fake()->createWithContent('guests.csv', "Juma,0713000111,single\nZawadi,0713000222,double\n");

        $this->actingAs($admin)->post(route('guests.import'), ['import_file' => $file])->assertSessionHas('status');
        $this->assertSame(2, $event->pledges()->count());

        $this->actingAs($admin)->post(route('guests.import'), [])->assertSessionHasErrors('import_file');

        $many = implode("\n", array_map(fn ($i) => "Guest {$i}", range(1, 501)));
        $this->actingAs($admin)->post(route('guests.import'), ['import_text' => $many])->assertSessionHasErrors('import_file');
        $this->assertSame(2, $event->pledges()->count());
    }

    public function test_ecard_guest_can_be_edited_and_removed_but_not_across_events(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event, ['name' => 'Old', 'phone' => null]);

        $this->actingAs($admin)->patch(route('guests.update', $g), ['name' => 'New Name', 'phone' => '0765000111', 'card_type' => 'double'])->assertSessionHas('status');
        $g->refresh();
        $this->assertSame('New Name', $g->name);
        $this->assertSame('+255765000111', $g->phone);

        [$other] = $this->ecardEvent();
        $foreign = $this->guestCard($other);
        $this->actingAs($admin)->patch(route('guests.update', $foreign), ['name' => 'Hacked', 'card_type' => 'single'])->assertStatus(404);
        $this->actingAs($admin)->delete(route('guests.destroy', $foreign))->assertStatus(404);
        $this->assertNotSame('Hacked', $foreign->fresh()->name);

        $this->actingAs($admin)->delete(route('guests.destroy', $g))->assertSessionHas('status');
        $this->assertNull(Pledge::find($g->id));
    }

    public function test_an_invited_guest_on_a_contributions_account_can_be_edited(): void
    {
        [$event, $admin] = $this->fullEvent();
        $guest = $this->pledger($event, ['guest_only' => true, 'amount' => 0, 'name' => 'Typo Namee', 'invite_token' => Str::random(32)]);
        $pledgerRow = $this->pledger($event, ['name' => 'Real Pledger']);

        $this->actingAs($admin)->get(route('guests.index'))->assertOk()->assertSee('id="editGuest'.$guest->id.'"', false)->assertDontSee('id="editGuest'.$pledgerRow->id.'"', false);
        $this->actingAs($admin)->patch(route('guests.update', $guest), ['name' => 'Typo Name', 'phone' => '0712999888', 'card_type' => 'single'])->assertSessionHas('status');
        $this->assertSame('Typo Name', $guest->fresh()->name);

        // A pledger's details are edited on the pledges page, never from the invitation list.
        $this->actingAs($admin)->patch(route('guests.update', $pledgerRow), ['name' => 'Changed', 'card_type' => 'single'])->assertNotFound();
        $this->assertSame('Real Pledger', $pledgerRow->fresh()->name);
    }

    // ---- Pages for each account type -------------------------------------------------------

    public function test_guest_pages_render_for_every_account_type_and_tab(): void
    {
        [$ecard, $ecardAdmin] = $this->ecardEvent();
        $this->guestCard($ecard, ['name' => 'Ecard Guest']);
        $this->actingAs($ecardAdmin)->get(route('guests.index'))->assertOk()->assertSee('Ecard Guest');
        $this->actingAs($ecardAdmin)->get(route('guests.index', ['tab' => 'rsvp']))->assertOk();

        [$full, $fullAdmin] = $this->fullEvent();
        $this->pledger($full, ['name' => 'Full Pledger']);
        $this->actingAs($fullAdmin)->get(route('guests.index'))->assertOk()->assertSee('Full Pledger');
        $this->actingAs($fullAdmin)->get(route('guests.index', ['tab' => 'meeting']))->assertOk();
        $this->actingAs($fullAdmin)->get(route('guests.index', ['tab' => 'rsvp']))->assertOk();

        [, $funeralAdmin] = $this->fullEvent(['event_type' => 'Funeral']);
        $this->actingAs($funeralAdmin)->get(route('guests.index'))->assertOk();
    }

    public function test_contributions_package_has_no_rsvp_tab(): void
    {
        [, $admin] = $this->fullEvent(['package' => 'sms']);
        $this->actingAs($admin)->get(route('guests.index', ['tab' => 'rsvp']))->assertForbidden();
        $this->actingAs($admin)->get(route('delivery.index'))->assertStatus(403);
    }

    // ---- Sending invitations ---------------------------------------------------------------

    public function test_whatsapp_invite_opens_wa_link_marks_sent_and_respects_opt_out(): void
    {
        [$event, $admin] = $this->fullEvent();
        $p = $this->pledger($event, ['phone' => '+255712345678', 'invite_token' => Str::random(32)]);

        $res = $this->actingAs($admin)->get(route('guests.whatsapp', $p));
        $res->assertRedirectContains('https://wa.me/255712345678?text=');
        $this->assertNotNull($p->fresh()->invite_sent_at);
        $this->assertSame('whatsapp', $p->fresh()->invite_channel);

        $blocked = $this->pledger($event, ['phone' => '+255788000999', 'invite_token' => Str::random(32)]);
        MessageOptOut::create(['phone' => '255788000999', 'source' => 'test']);
        $this->actingAs($admin)->get(route('guests.whatsapp', $blocked))->assertRedirect();
        $this->assertNull($blocked->fresh()->invite_sent_at);
    }

    public function test_sms_invite_refuses_without_link_phone_or_for_opted_out(): void
    {
        $this->beem();
        [$event, $admin] = $this->fullEvent();

        $noLink = $this->pledger($event, ['phone' => '+255712345678', 'invite_token' => null]);
        $this->actingAs($admin)->post(route('guests.sms', $noLink))->assertForbidden();

        $noPhone = $this->pledger($event, ['phone' => null, 'invite_token' => Str::random(32)]);
        $this->actingAs($admin)->post(route('guests.sms', $noPhone))->assertSessionHas('status');
        $this->assertStringContainsString('failed', session('status'));
        $this->assertNull($noPhone->fresh()->invite_sent_at);

        $optOut = $this->pledger($event, ['phone' => '+255788000111', 'invite_token' => Str::random(32)]);
        MessageOptOut::create(['phone' => '255788000111', 'source' => 'test']);
        $this->actingAs($admin)->post(route('guests.sms', $optOut));
        $this->assertNull($optOut->fresh()->invite_sent_at);
        Http::assertNothingSent();
    }

    public function test_message_templates_save_with_validation(): void
    {
        [$event, $admin] = $this->fullEvent();

        $this->actingAs($admin)->patch(route('guests.message.invitation'), ['invitation_message' => 'Karibu {name}'])->assertSessionHas('status');
        $this->actingAs($admin)->patch(route('guests.message.meeting'), ['meeting_message' => 'Meeting {date}'])->assertSessionHas('status');
        $this->actingAs($admin)->patch(route('guests.message.announcement'), ['announcement_message' => 'Notice'])->assertSessionHas('status');
        $event->refresh();
        $this->assertSame('Karibu {name}', $event->invitation_message);
        $this->assertSame('Meeting {date}', $event->meeting_message);
        $this->assertSame('Notice', $event->announcement_message);

        $this->actingAs($admin)->patch(route('guests.message.invitation'), ['invitation_message' => ''])->assertSessionHasErrors('invitation_message');
        $this->actingAs($admin)->patch(route('guests.message.meeting'), ['meeting_message' => str_repeat('x', 5001)])->assertSessionHasErrors('meeting_message');
    }

    public function test_meeting_broadcast_goes_to_contributors_only_not_invited_guests(): void
    {
        $this->beem(2);
        [$event, $admin] = $this->fullEvent(['meeting_message' => 'Committee meeting {date} at {place}']);
        $this->pledger($event, ['phone' => '+255712000001']);
        $this->pledger($event, ['phone' => '+255712000002']);
        $this->pledger($event, ['phone' => '+255712000003', 'guest_only' => true, 'amount' => 0]);

        $this->actingAs($admin)->post(route('guests.meeting.broadcast-sms'))->assertSessionHas('status');

        Http::assertSent(function ($request) {
            $dests = collect($request['recipients'])->pluck('dest_addr')->all();

            return count($dests) === 2 && ! in_array('255712000003', $dests, true);
        });
    }

    public function test_meeting_broadcast_needs_a_message_and_phone_numbers(): void
    {
        $this->beem();
        [$event, $admin] = $this->fullEvent();
        $this->pledger($event, ['phone' => null]);

        $this->actingAs($admin)->post(route('guests.meeting.broadcast-sms'))->assertSessionHasErrors('meeting_message');
        Http::assertNothingSent();
    }

    public function test_funeral_broadcast_uses_picked_numbers_or_falls_back_to_saved_contacts(): void
    {
        $this->beem(2);
        [$event, $admin] = $this->fullEvent(['event_type' => 'Funeral']);
        $this->pledger($event, ['phone' => '+255712111111']);

        $this->actingAs($admin)->post(route('guests.broadcast-sms'), ['phones' => ['0713222222', '0714333333']])->assertSessionHas('status');
        Http::assertSent(fn ($r) => collect($r['recipients'])->pluck('dest_addr')->sort()->values()->all() === ['255713222222', '255714333333']);

        $this->actingAs($admin)->post(route('guests.broadcast-sms'), [])->assertSessionHas('status');
        Http::assertSentCount(2);

        [, $emptyAdmin] = $this->fullEvent(['event_type' => 'Funeral']);
        $this->actingAs($emptyAdmin)->post(route('guests.broadcast-sms'), [])->assertSessionHasErrors('phones');
    }

    // ---- Delivery funnel -------------------------------------------------------------------

    private function cardEvent(): array
    {
        [$event, $admin] = $this->ecardEvent(['package' => 'full']);

        return [$event, $admin];
    }

    public function test_delivery_filters_show_the_right_guests_for_each_stage(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $this->guestCard($event, ['name' => 'NotSentNina']);
        $this->guestCard($event, ['name' => 'SentSam', 'invite_sent_at' => now()]);
        $this->guestCard($event, ['name' => 'OpenedOlga', 'invite_sent_at' => now(), 'first_opened_at' => now()]);
        $this->guestCard($event, ['name' => 'RespondedRita', 'invite_sent_at' => now(), 'first_opened_at' => now(), 'rsvp_status' => 'attending']);
        $this->guestCard($event, ['name' => 'ArrivedAmos', 'invite_sent_at' => now(), 'first_opened_at' => now(), 'checked_in_at' => now()]);

        $see = function (string $stage, array $yes, array $no) use ($admin) {
            $r = $this->actingAs($admin)->get(route('delivery.index', ['stage' => $stage]))->assertOk();
            foreach ($yes as $n) { $r->assertSee($n); }
            foreach ($no as $n) { $r->assertDontSee($n); }
        };

        $see('not_sent', ['NotSentNina'], ['SentSam', 'OpenedOlga']);
        $see('unopened', ['SentSam'], ['NotSentNina', 'OpenedOlga']);
        $see('opened', ['OpenedOlga'], ['NotSentNina', 'RespondedRita']);
        $see('responded', ['RespondedRita'], ['NotSentNina', 'OpenedOlga']);
        $see('arrived', ['ArrivedAmos'], ['NotSentNina', 'RespondedRita']);
    }

    public function test_send_all_texts_only_unsent_guests_with_phones_and_marks_them(): void
    {
        $this->beem();
        [$event, $admin] = $this->ecardEvent();
        $a = $this->guestCard($event, ['phone' => '+255712000001']);
        $b = $this->guestCard($event, ['phone' => '+255712000002', 'invite_sent_at' => now()->subDay()]);
        $c = $this->guestCard($event, ['phone' => null]);

        $this->actingAs($admin)->post(route('delivery.send-all'))->assertSessionHas('status');

        $this->assertNotNull($a->fresh()->invite_sent_at);
        $this->assertSame('sms', $a->fresh()->invite_channel);
        $this->assertNull($c->fresh()->invite_sent_at);
        Http::assertSentCount(1);

        $this->actingAs($admin)->post(route('delivery.send-all'))->assertSessionHas('status');
        Http::assertSentCount(1); // nothing left to send
    }

    public function test_remind_unopened_nudges_only_those_who_have_not_opened(): void
    {
        $this->beem();
        [$event, $admin] = $this->ecardEvent();
        $unopened = $this->guestCard($event, ['phone' => '+255712000001', 'invite_sent_at' => now()->subDays(3)]);
        $opened = $this->guestCard($event, ['phone' => '+255712000002', 'invite_sent_at' => now()->subDays(3), 'first_opened_at' => now()]);

        $this->actingAs($admin)->post(route('delivery.remind-unopened'))->assertSessionHas('status');

        $this->assertNotNull($unopened->fresh()->unopened_reminded_at);
        $this->assertNull($opened->fresh()->unopened_reminded_at);
        Http::assertSentCount(1);
    }

    public function test_whatsapp_reminder_and_mark_sent(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event, ['phone' => '+255712345678', 'invite_sent_at' => now()->subDay()]);

        $this->actingAs($admin)->get(route('delivery.remind-wa', $g))->assertRedirectContains('https://wa.me/255712345678');
        $this->assertNotNull($g->fresh()->unopened_reminded_at);

        $fresh = $this->guestCard($event);
        $this->actingAs($admin)->post(route('delivery.mark-sent', $fresh))->assertSessionHas('status');
        $this->assertSame('manual', $fresh->fresh()->invite_channel);

        $revoked = $this->guestCard($event, ['invite_token' => null]);
        $this->actingAs($admin)->get(route('delivery.remind-wa', $revoked))->assertNotFound();
    }

    public function test_auto_reminder_settings_validate_range(): void
    {
        [$event, $admin] = $this->ecardEvent();

        $this->actingAs($admin)->patch(route('delivery.auto'), ['auto_remind_unopened' => 1, 'auto_remind_unopened_days' => 5, 'unopened_reminder_message' => 'Please open'])->assertSessionHas('status');
        $event->refresh();
        $this->assertTrue((bool) $event->auto_remind_unopened);
        $this->assertSame(5, (int) $event->auto_remind_unopened_days);

        $this->actingAs($admin)->patch(route('delivery.auto'), ['auto_remind_unopened_days' => 0])->assertSessionHasErrors('auto_remind_unopened_days');
        $this->actingAs($admin)->patch(route('delivery.auto'), ['auto_remind_unopened_days' => 31])->assertSessionHasErrors('auto_remind_unopened_days');
    }

    public function test_revoke_kills_the_link_and_reissue_makes_a_fresh_one(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event, ['invite_sent_at' => now(), 'first_opened_at' => now(), 'open_count' => 3]);
        $old = $g->invite_token;
        $this->get(route('guest.rsvp', $old))->assertOk();

        $this->actingAs($admin)->post(route('delivery.revoke', $g))->assertSessionHas('status');
        $this->assertNull($g->fresh()->invite_token);
        $this->assertNotSame(200, $this->get(route('guest.rsvp', $old))->getStatusCode());

        $this->actingAs($admin)->post(route('delivery.reissue', $g))->assertSessionHas('status');
        $g->refresh();
        $this->assertNotNull($g->invite_token);
        $this->assertNotSame($old, $g->invite_token);
        $this->assertNull($g->invite_sent_at);
        $this->assertSame(0, (int) $g->open_count);
        $this->assertNotSame(200, $this->get(route('guest.rsvp', $old))->getStatusCode()); // the old link stays dead
        $this->get(route('guest.rsvp', $g->invite_token))->assertOk();
    }

    public function test_csv_export_keeps_phone_numbers_clean_and_neutralises_formulas(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $this->guestCard($event, ['name' => '=HYPERLINK("http://evil")', 'phone' => '+255712345678']);

        $csv = $this->actingAs($admin)->get(route('guests.export'))->assertOk()->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString(',+255712345678,', $csv);
        $this->assertStringNotContainsString("'+255712345678", $csv);
    }

    // ---- Permissions and isolation ---------------------------------------------------------

    public function test_viewers_cannot_use_any_sending_or_delivery_action(): void
    {
        $this->beem();
        [$event] = $this->ecardEvent();
        $viewer = $this->memberOf($event, 'viewer');
        $g = $this->guestCard($event, ['phone' => '+255712345678']);

        foreach ([
            ['post', route('delivery.send-all')],
            ['post', route('delivery.remind-unopened')],
            ['post', route('delivery.revoke', $g)],
            ['post', route('delivery.reissue', $g)],
            ['post', route('delivery.mark-sent', $g)],
            ['post', route('guests.sms', $g)],
            ['get', route('guests.whatsapp', $g)],
            ['patch', route('delivery.auto')],
            ['patch', route('rsvp.settings')],
            ['get', route('guests.export')],
        ] as [$method, $url]) {
            $this->actingAs($viewer)->{$method}($url)->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_an_admin_cannot_touch_another_events_guests(): void
    {
        $this->beem();
        [, $admin] = $this->ecardEvent();
        [$otherEvent] = $this->ecardEvent();
        $foreign = $this->guestCard($otherEvent, ['phone' => '+255712345678']);

        foreach ([
            ['post', route('delivery.revoke', $foreign)],
            ['post', route('delivery.reissue', $foreign)],
            ['post', route('delivery.mark-sent', $foreign)],
            ['post', route('guests.sms', $foreign)],
            ['get', route('guests.whatsapp', $foreign)],
            ['post', route('guests.send-invite', $foreign)],
            ['get', route('delivery.remind-wa', $foreign)],
            ['patch', route('seating.assign', $foreign)],
        ] as [$method, $url]) {
            $this->actingAs($admin)->{$method}($url)->assertStatus(404);
        }
        $this->assertNotNull($foreign->fresh()->invite_token);
        Http::assertNothingSent();
    }

    // ---- RSVP settings, seating areas, public pages ----------------------------------------

    public function test_rsvp_settings_save_cap_meals_and_validate(): void
    {
        [$event, $admin] = $this->ecardEvent();

        $this->actingAs($admin)->patch(route('rsvp.settings'), [
            'rsvp_plus_ones_enabled' => 1, 'rsvp_max_plus_single' => 1, 'rsvp_max_plus_double' => 2,
            'rsvp_meal_enabled' => 1, 'rsvp_meal_options' => "Chicken\n\n  Beef  \nVegetarian", 'rsvp_cutoff_date' => '2026-12-01',
        ])->assertSessionHas('status');

        $event->refresh();
        $this->assertSame("Chicken\nBeef\nVegetarian", $event->rsvp_meal_options);
        $this->assertTrue($event->rsvp_plus_ones_enabled);

        $this->actingAs($admin)->patch(route('rsvp.settings'), ['rsvp_max_plus_single' => 11, 'rsvp_max_plus_double' => 0])->assertSessionHasErrors('rsvp_max_plus_single');
    }

    public function test_seating_area_can_be_removed_and_not_from_another_event(): void
    {
        [$event, $admin] = $this->ecardEvent(['seating_mode' => 'zone']);
        $this->actingAs($admin)->post(route('seating.areas.store'), ['name' => 'Family']);
        $area = $event->seatingAreas()->first();

        [$other] = $this->ecardEvent(['seating_mode' => 'zone']);
        $otherArea = $other->seatingAreas()->create(['name' => 'Theirs']);
        $this->actingAs($admin)->delete(route('seating.areas.destroy', $otherArea))->assertNotFound();

        $this->actingAs($admin)->delete(route('seating.areas.destroy', $area))->assertSessionHas('status');
        $this->assertNull($event->seatingAreas()->first());
    }

    public function test_pay_page_and_photo_endpoint_work_publicly_and_fail_safely(): void
    {
        [$event] = $this->fullEvent();
        $p = $this->pledger($event, ['phone' => '+255712345678']);

        $this->get(route('guest.pay', $p->pay_token))->assertOk();
        $this->get(route('guest.pay', 'does-not-exist'))->assertNotFound();
        $this->get(route('guest.rsvp.photo', 'does-not-exist'))->assertNotFound();
        $this->get(route('guest.rsvp', 'does-not-exist'))->assertStatus(404);
    }

    public function test_send_now_event_day_reminder_skips_decliners_and_runs_once(): void
    {
        $this->beem();
        [$event, $admin] = $this->ecardEvent(['package' => 'full']);
        $go = $this->guestCard($event, ['phone' => '+255712000001']);
        $declined = $this->guestCard($event, ['phone' => '+255712000002', 'rsvp_status' => 'not_attending']);

        $this->actingAs($admin)->post(route('design.day-reminder.send'))->assertSessionHas('status');
        $this->assertNotNull($go->fresh()->event_day_reminder_sent_at);
        $this->assertNull($declined->fresh()->event_day_reminder_sent_at);
        Http::assertSentCount(1);

        $this->actingAs($admin)->post(route('design.day-reminder.send'))->assertSessionHas('status');
        Http::assertSentCount(1);
    }
}
