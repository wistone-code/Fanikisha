<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventMember;
use App\Models\Pledge;
use App\Models\PasswordResetCode;
use App\Models\SeatingArea;
use App\Models\SeatingTable;
use App\Models\User;
use App\Services\BeemSmsService;
use App\Services\MessageTemplateService;
use App\Services\OptOutService;
use App\Support\SessionCleaner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

/** Second round of the audit: security hardening, offline check-in, seating, imports, exports and operations. */
class AuditFixesTwoTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function source(string $path): string
    {
        return file_get_contents(base_path($path));
    }

    // ---- speed ---------------------------------------------------------------------------

    public function test_the_signed_in_event_is_loaded_without_its_card_photo_but_the_photo_still_works(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $event->forceFill(['card_photo' => 'PHOTO-BYTES', 'card_photo_mime' => 'image/png'])->save();

        $loaded = $admin->currentEvent();
        $this->assertNull($loaded->getAttribute('card_photo'), 'the picture is not carried on every request');
        $this->assertTrue($loaded->hasCardPhoto());

        $this->actingAs($admin)->get(route('event.settings.card-photo.view'))->assertOk()->assertSee('PHOTO-BYTES', false);

        $guest = $this->guestCard($event);
        $this->get(route('guest.rsvp.photo', $guest->invite_token))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_the_server_runs_several_workers_and_scheduled_events_skip_the_photo(): void
    {
        $this->assertStringContainsString('PHP_CLI_SERVER_WORKERS=8', $this->source('Dockerfile'));
        foreach (['SendThankYous', 'SendEventDayReminders', 'SendUnopenedReminders', 'SendDueAutoReminders'] as $c) {
            $this->assertStringContainsString('Event::lean()', $this->source("app/Console/Commands/{$c}.php"));
        }
    }

    // ---- passwords and sessions ----------------------------------------------------------

    public function test_the_forced_password_screen_cannot_be_used_to_change_a_password_later(): void
    {
        $user = User::factory()->create(['must_change_password' => false, 'password' => Hash::make('Old-pass-1')]);

        $this->actingAs($user)->post(route('password.change.update'), ['password' => 'New-pass-12', 'password_confirmation' => 'New-pass-12'])->assertRedirect(route('dashboard'));
        $this->assertTrue(Hash::check('Old-pass-1', $user->fresh()->password));

        $user->update(['must_change_password' => true]);
        $this->actingAs($user)->post(route('password.change.update'), ['password' => 'New-pass-12', 'password_confirmation' => 'New-pass-12']);
        $this->assertTrue(Hash::check('New-pass-12', $user->fresh()->password));
    }

    public function test_changing_a_password_signs_out_other_devices_but_not_this_one(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['remember_token' => 'old-token']);
        $other = User::factory()->create();
        foreach (['mine' => $user->id, 'phone' => $user->id, 'else' => $other->id] as $id => $uid) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $uid, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        }

        SessionCleaner::endOthers($user, 'mine');

        $this->assertSame(['else', 'mine'], DB::table('sessions')->orderBy('id')->pluck('id')->all());
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
    }

    public function test_reset_codes_cannot_be_requested_without_limit_and_the_proof_expires(): void
    {
        $user = User::factory()->create(['username' => 'zed', 'email' => 'zed@example.com']);
        foreach (range(1, 5) as $i) {
            PasswordResetCode::create(['user_id' => $user->id, 'code' => '123456', 'expires_at' => now()->addMinutes(10), 'consumed_at' => now()]);
        }

        $this->post(route('password.forgot.identify.submit'), ['username' => 'zed', 'email' => 'zed@example.com', 'channel' => 'email'])
            ->assertSessionHasErrors('username');
        $this->assertSame(5, PasswordResetCode::where('user_id', $user->id)->count(), 'no sixth code was made');

        $this->withSession(['password_reset_user_id' => $user->id, 'password_reset_verified' => now()->subMinutes(30)->timestamp])
            ->get(route('password.forgot.reset'))->assertRedirect(route('password.forgot.identify'));
    }

    public function test_changing_username_email_or_phone_needs_the_current_password(): void
    {
        $event = Event::factory()->create(['event_type' => 'Wedding', 'event_date' => now()->addDays(10)->toDateString()]);
        $admin = $this->memberOf($event, 'admin');
        $admin->update(['password' => Hash::make('Right-pass-1'), 'email' => 'old@example.com']);

        $this->actingAs($admin)->patch(route('account.email.update'), ['email' => 'new@example.com'])->assertSessionHasErrors('current_password');
        $this->actingAs($admin)->patch(route('account.email.update'), ['email' => 'new@example.com', 'current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertSame('old@example.com', $admin->fresh()->email);

        $this->actingAs($admin)->patch(route('account.email.update'), ['email' => 'new@example.com', 'current_password' => 'Right-pass-1'])->assertSessionHasNoErrors();
        $this->assertSame('new@example.com', $admin->fresh()->email);

        $this->actingAs($admin)->patch(route('account.phone.update'), ['phone' => '0712345678'])->assertSessionHasErrors('current_password');
        $this->actingAs($admin)->patch(route('account.username.update'), ['username' => 'sneaky'])->assertSessionHasErrors('current_password');
    }

    public function test_sign_in_failures_do_not_reveal_whether_the_username_exists(): void
    {
        User::factory()->create(['username' => 'real-user', 'password' => Hash::make('Right-pass-1')]);

        $a = $this->post(route('login.attempt'), ['username' => 'real-user', 'password' => 'wrong'])->assertSessionHasErrors('username');
        $b = $this->post(route('login.attempt'), ['username' => 'nobody', 'password' => 'wrong'])->assertSessionHasErrors('username');

        $this->assertSame(session('errors')->first('username'), 'Incorrect username or password.');
        $this->assertStringContainsString('TRUSTED_PROXIES', $this->source('.env.example'));
    }

    // ---- super user: package and quota ---------------------------------------------------

    public function test_package_change_on_a_team_admin_applies_to_the_event_and_every_account_on_it(): void
    {
        $event = Event::factory()->create(['package' => 'full', 'mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);
        $owner = $this->memberOf($event, 'admin');
        $event->update(['created_by' => $owner->id]);
        $helper = $this->memberOf($event, 'admin');
        $viewer = $this->memberOf($event, 'viewer');
        $helper->update(['created_by' => $owner->id]);
        $super = User::factory()->create(['is_super_user' => true]);

        $this->actingAs($super)->patch(route('admin.users.package', $helper), ['package' => 'sms'])->assertSessionHas('status');

        $this->assertSame('sms', $event->fresh()->package);
        foreach ([$owner, $helper, $viewer] as $u) {
            $this->assertSame('sms', $u->fresh()->package);
        }
        $this->actingAs($viewer)->get(route('delivery.index'))->assertForbidden();

        $this->actingAs($super)->patch(route('admin.users.package', $owner), ['package' => 'full'])->assertSessionHas('status');
        $this->assertSame('full', $viewer->fresh()->package);
        $this->actingAs($viewer)->get(route('delivery.index'))->assertOk();

        $this->actingAs($super)->patch(route('admin.users.sms-quota', $helper), ['sms_quota' => 5])->assertNotFound();
        $this->assertNull($event->fresh()->sms_quota);
    }

    public function test_viewers_and_door_staff_have_no_change_package_option_and_cannot_be_changed_directly(): void
    {
        $event = Event::factory()->create(['package' => 'sms', 'mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);
        $owner = $this->memberOf($event, 'admin');
        $viewer = $this->memberOf($event, 'viewer');
        $scanner = $this->memberOf($event, 'scanner');
        $super = User::factory()->create(['is_super_user' => true]);

        $html = $this->actingAs($super)->get(route('admin.users.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="changePackage'.$owner->id.'"', $html);
        $this->assertStringNotContainsString('id="changePackage'.$viewer->id.'"', $html);
        $this->assertStringNotContainsString('id="changePackage'.$scanner->id.'"', $html);

        $this->actingAs($super)->patch(route('admin.users.package', $viewer), ['package' => 'full'])->assertForbidden();
        $this->actingAs($super)->patch(route('admin.users.package', $scanner), ['package' => 'full'])->assertForbidden();
        $this->assertSame('sms', $event->fresh()->package);
    }

    public function test_new_team_members_get_the_events_package(): void
    {
        $event = Event::factory()->create(['package' => 'sms', 'mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()]);
        $owner = $this->memberOf($event, 'admin');

        $this->actingAs($owner)->post(route('team.store'), ['name' => 'Vi Ewer', 'username' => 'viewer.one', 'email' => 'vi@example.com', 'role' => 'viewer'])->assertSessionHas('status');

        $this->assertSame('sms', User::where('username', 'viewer.one')->value('package'));
    }

    public function test_moving_from_ecard_to_a_money_package_turns_guests_into_invited_guests_not_zero_pledges(): void
    {
        [$event, $owner] = $this->ecardEvent(['package' => 'ecard']);
        $owner->update(['package' => 'ecard']);
        $g = $this->guestCard($event, ['guest_only' => false]);
        $super = User::factory()->create(['is_super_user' => true]);

        $this->actingAs($super)->patch(route('admin.users.package', $owner), ['package' => 'full'])->assertSessionHas('status');

        $this->assertTrue((bool) $g->fresh()->guest_only);
        $this->assertSame(0, $event->fresh()->pledges()->contributors()->count());
    }

    // ---- seating -------------------------------------------------------------------------

    public function test_auto_seating_never_overfills_a_table_or_moves_guests_placed_in_a_zone(): void
    {
        [$event, $admin] = $this->ecardEvent(['seating_mode' => 'tables']);
        $area = SeatingArea::create(['event_id' => $event->id, 'name' => 'VIP']);
        $t1 = SeatingTable::create(['event_id' => $event->id, 'name' => 'T1', 'capacity' => 2]);
        $t2 = SeatingTable::create(['event_id' => $event->id, 'name' => 'T2', 'capacity' => 2]);
        $zone = $this->guestCard($event, ['name' => 'Zone Zed', 'seating_area_id' => $area->id]);
        $family = collect(['A', 'B', 'C'])->map(fn ($n) => $this->guestCard($event, ['name' => "Fam {$n}", 'group_name' => 'Family']));
        $this->guestCard($event, ['name' => 'Fam D', 'group_name' => 'Family']);

        $this->actingAs($admin)->post(route('seating.auto-fill'))->assertRedirect();

        $this->assertNull($zone->fresh()->seating_table_id, 'a guest placed in a zone is not moved');
        $this->assertSame($area->id, $zone->fresh()->seating_area_id);
        $this->assertLessThanOrEqual(2, $t1->fresh()->seatsTaken());
        $this->assertLessThanOrEqual(2, $t2->fresh()->seatsTaken());
        $this->assertSame(4, $t1->fresh()->seatsTaken() + $t2->fresh()->seatsTaken(), 'the group is split instead of overfilling one table');
    }

    public function test_new_table_names_continue_after_a_delete(): void
    {
        [$event, $admin] = $this->ecardEvent(['seating_mode' => 'tables']);
        $this->actingAs($admin)->post(route('seating.tables.store'), ['name' => 'Table', 'capacity' => 8, 'count' => 3]);
        $event->seatingTables()->where('name', 'Table 2')->delete();
        $this->actingAs($admin)->post(route('seating.tables.store'), ['name' => 'Table', 'capacity' => 8, 'count' => 2]);

        $names = $event->seatingTables()->pluck('name');
        $this->assertSame($names->count(), $names->unique()->count());
    }

    // ---- check-in ------------------------------------------------------------------------

    public function test_the_door_warns_about_a_guest_who_said_no_and_undo_clears_who_checked_them_in(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $door = $this->memberOf($event, 'scanner');
        $g = $this->guestCard($event, ['rsvp_status' => 'not_attending']);

        $json = $this->actingAs($door)->postJson(route('checkin.verify'), ['token' => $g->invite_token])->assertOk()->json();
        $this->assertTrue($json['guest']['declined'] ?? $json['declined']);

        $this->actingAs($admin)->delete(route('checkin.undo', $g))->assertRedirect();
        $g->refresh();
        $this->assertNull($g->checked_in_at);
        $this->assertNull($g->checked_in_by);
    }

    public function test_offline_check_in_keeps_scans_per_event_refreshes_the_saved_list_and_survives_a_hung_connection(): void
    {
        $view = $this->source('resources/views/event/checkin/index.blade.php');
        $this->assertStringContainsString('eventId: currentEventId, name: guest.name', $view);
        $this->assertStringContainsString("q.eventId === currentEventId", $view);
        $this->assertStringContainsString('refreshListIfStale', $view);
        $this->assertStringContainsString('list saved', $view);

        $sw = $this->source('public/sw.js');
        $this->assertStringContainsString('withTimeout(fetch(request), 8000)', $sw);
    }

    // ---- delivery and cards --------------------------------------------------------------

    public function test_answering_a_card_does_not_count_as_a_second_open(): void
    {
        [$event] = $this->ecardEvent();
        $g = $this->guestCard($event);
        $ua = 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36';

        $this->withHeader('User-Agent', $ua)->get(route('guest.rsvp', $g->invite_token))->assertOk();
        $this->assertSame(1, (int) $g->fresh()->open_count);

        $this->withHeader('User-Agent', $ua)->followingRedirects()->post(route('guest.rsvp.respond', $g->invite_token), ['response' => 'attending'])->assertOk();
        $this->assertSame(1, (int) $g->fresh()->open_count, 'the page after answering is the same visit');
    }

    public function test_the_pay_page_exists_only_for_contribution_events(): void
    {
        [$event] = $this->ecardEvent();
        $g = $this->guestCard($event);
        $this->get(route('guest.pay', $g->pay_token))->assertNotFound();

        $money = Event::factory()->create(['mode' => 'contributions', 'package' => 'full', 'event_type' => 'Wedding', 'event_date' => now()->addDays(10)->toDateString()]);
        $p = Pledge::factory()->create(['event_id' => $money->id, 'pay_token' => Str::random(32), 'amount' => 1000]);
        $this->get(route('guest.pay', $p->pay_token))->assertOk();
    }

    // ---- photos --------------------------------------------------------------------------

    public function test_huge_pixel_images_are_refused_before_they_are_decoded(): void
    {
        $png = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('N', 12000).pack('N', 12000)."\x08\x02\x00\x00\x00".pack('N', 0);

        $this->assertNull(app(\App\Services\ImageResizer::class)->process($png));
        $this->assertNull(app(\App\Services\ImageResizer::class)->fit($png));
        $this->assertNotNull(app(\App\Services\ImageResizer::class)->process($this->fakeJpeg(200, 100)));
    }

    public function test_the_photo_zip_is_streamed_from_the_database_one_picture_at_a_time(): void
    {
        $this->assertStringContainsString('->cursor()', $this->source('app/Http/Controllers/PhotoWallController.php'));
    }

    // ---- SMS wording, opt-outs and quota -------------------------------------------------

    public function test_payment_texts_show_the_amount_just_paid_and_group_messages_never_show_raw_placeholders(): void
    {
        [$event] = $this->ecardEvent(['sms_language' => 'en', 'broadcast_message' => 'Hello {name}, meet at {place} on {date}. {link}']);
        $pledge = Pledge::factory()->create(['event_id' => $event->id, 'name' => 'Pat', 'amount' => 100000, 'paid' => 50000, 'pay_token' => Str::random(32)]);

        $text = app(MessageTemplateService::class)->forPledgePayment($event, $pledge, 10000);
        $this->assertStringContainsString('payment of 10,000', $text);
        $this->assertStringContainsString('50,000', $text); // the balance still left

        $broadcast = app(MessageTemplateService::class)->forBroadcast($event);
        $this->assertStringNotContainsString('{', $broadcast);
        $this->assertStringContainsString('meet at', $broadcast);
    }

    public function test_opted_out_people_are_skipped_marked_as_handled_and_not_counted(): void
    {
        config(['services.beem.api_key' => 'k', 'services.beem.secret_key' => 's']);
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'valid' => 1, 'invalid' => 0, 'request_id' => 1])]);
        [$event] = $this->ecardEvent(['sms_quota' => 10, 'sms_sent_count' => 0]);
        app(OptOutService::class)->add('+255712345679', 'test');

        $handled = [];
        $result = app(BeemSmsService::class)->forEvent($event)->sendPersonalised(collect([
            ['key' => 1, 'phone' => '+255712345678', 'message' => 'a'],
            ['key' => 2, 'phone' => '0712345679', 'message' => 'b'],
        ]), function ($k) use (&$handled) { $handled[] = $k; });

        $this->assertSame(1, $result['sent']);
        $this->assertEqualsCanonicalizing([1, 2], $handled);
        $this->assertSame(1, (int) $event->fresh()->sms_sent_count);
        Http::assertSentCount(1);
    }

    // ---- imports and exports -------------------------------------------------------------

    public function test_schedule_dates_are_read_day_first(): void
    {
        $event = Event::factory()->create(['mode' => 'contributions', 'package' => 'full', 'event_type' => 'Wedding', 'event_date' => now()->addDays(30)->toDateString()]);
        $admin = $this->memberOf($event, 'admin');

        $this->actingAs($admin)->post(route('schedule.import-text'), ['import_text' => 'Cake cutting, 12/03/2026, 10:00'])->assertRedirect();

        $this->assertSame('2026-03-12', $event->scheduleItems()->first()->date->toDateString());
    }

    public function test_exports_keep_phone_numbers_clean_and_providers_show_paid_and_remaining(): void
    {
        $trait = new class { use \App\Exports\Concerns\SanitizesExcelCells; public function t(?string $v) { return $this->sanitizeCell($v); } };
        $this->assertSame('+255712345678', $trait->t('+255712345678'));
        $this->assertSame("'=SUM(A1)", $trait->t('=SUM(A1)'));
        $this->assertSame("'+cmd", $trait->t('+cmd'));

        $event = Event::factory()->create(['event_type' => 'Wedding', 'event_date' => now()->addDays(10)->toDateString()]);
        $this->assertSame(['Name', 'Service', 'Budget', 'Paid', 'Remaining', 'Contact'], (new \App\Exports\ProvidersExport($event))->headings());
    }

    public function test_ai_import_keys_travel_in_a_header_not_the_address(): void
    {
        foreach (['ScheduleImportService', 'PledgeImportService'] as $f) {
            $src = $this->source("app/Services/{$f}.php");
            $this->assertStringContainsString('x-goog-api-key', $src);
            $this->assertStringNotContainsString('?key=', $src);
        }
    }
}
