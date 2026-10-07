<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventMember;
use App\Models\EventPhoto;
use App\Models\Pledge;
use App\Models\SeatingTable;
use App\Models\User;
use App\Services\BeemSmsService;
use App\Services\PhoneNumberService;
use App\Support\ImportRows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

/** One test per defect found in the feature-by-feature audit. */
class AuditFixesTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function contributionEvent(array $attrs = []): array
    {
        $event = Event::factory()->create(array_merge(['mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(10)->toDateString(), 'package' => 'full'], $attrs));

        return [$event, $this->memberOf($event, 'admin')];
    }

    public function test_a_team_admin_cannot_reset_the_event_owners_password_but_the_owner_can_reset_their_own(): void
    {
        $event = Event::factory()->create(['event_type' => 'Wedding', 'event_date' => now()->addDays(10)->toDateString()]);
        $owner = $this->memberOf($event, 'admin');
        $event->forceFill(['created_by' => $owner->id])->save();
        $helper = $this->memberOf($event, 'admin');
        $ownerMember = EventMember::where('user_id', $owner->id)->first();
        $before = $owner->fresh()->password;

        $this->actingAs($helper)->post(route('team.reset-password', $ownerMember))->assertForbidden();
        $this->assertSame($before, $owner->fresh()->password);

        $this->actingAs($owner)->post(route('team.reset-password', $ownerMember))->assertRedirect();
        $this->assertNotSame($before, $owner->fresh()->password);
    }

    public function test_broadcast_sms_is_only_for_funeral_events_on_money_packages(): void
    {
        [, $ecardAdmin] = $this->ecardEvent();
        $this->actingAs($ecardAdmin)->post(route('guests.broadcast-sms'), ['phones' => ['0712345678']])->assertNotFound();

        [, $weddingAdmin] = $this->contributionEvent();
        $this->actingAs($weddingAdmin)->post(route('guests.broadcast-sms'), ['phones' => ['0712345678']])->assertNotFound();
    }

    public function test_the_login_limit_cannot_be_dodged_with_spaces_or_invisible_characters(): void
    {
        User::factory()->create(['username' => 'mary']);

        foreach (['mary', 'mary ', ' mary', "mary\u{200B}", "mary\u{00A0}", 'MARY'] as $variant) {
            $this->post(route('login.attempt'), ['username' => $variant, 'password' => 'nope']);
        }

        $this->post(route('login.attempt'), ['username' => 'mary  ', 'password' => 'nope'])->assertStatus(429);
    }

    public function test_each_guest_is_flagged_as_soon_as_their_sms_is_sent_and_quota_counts_per_message(): void
    {
        config(['services.beem.api_key' => 'k', 'services.beem.secret_key' => 's']);
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'valid' => 1, 'invalid' => 0, 'request_id' => 1])]);
        [$event] = $this->ecardEvent(['sms_quota' => 100, 'sms_sent_count' => 0]);

        $flagged = [];
        $result = app(BeemSmsService::class)->forEvent($event)->sendPersonalised(collect([
            ['key' => 1, 'phone' => '+255712345678', 'message' => 'a'],
            ['key' => 2, 'phone' => '+255712345679', 'message' => 'b'],
        ]), function ($key) use (&$flagged, $event) {
            $flagged[] = $key;
            // The count is already up to date when the callback runs.
            $this->assertSame(count($flagged), (int) $event->fresh()->sms_sent_count);
        });

        $this->assertSame([1, 2], $flagged);
        $this->assertSame(2, $result['sent']);
        $this->assertSame(2, (int) $event->fresh()->sms_sent_count);
    }

    public function test_scheduled_sms_jobs_cannot_overlap(): void
    {
        $this->artisan('schedule:list')->assertExitCode(0);
        $source = file_get_contents(base_path('routes/console.php'));
        $this->assertSame(4, substr_count($source, 'withoutOverlapping'));
    }

    public function test_reset_and_new_link_clear_old_reminder_flags_and_a_new_event_date_does_too(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event, ['rsvp_status' => 'attending', 'unopened_reminded_at' => now(), 'event_day_reminder_sent_at' => now(), 'thank_you_sent_at' => now()]);

        $this->actingAs($admin)->post(route('delivery.reissue', $g))->assertRedirect();
        $g->refresh();
        $this->assertNull($g->unopened_reminded_at);
        $this->assertNull($g->event_day_reminder_sent_at);

        $g->update(['unopened_reminded_at' => now(), 'event_day_reminder_sent_at' => now()]);
        $this->actingAs($admin)->post(route('rsvp.reset', $g))->assertRedirect();
        $g->refresh();
        $this->assertNull($g->unopened_reminded_at);
        $this->assertNull($g->event_day_reminder_sent_at);

        $g->update(['event_day_reminder_sent_at' => now(), 'thank_you_sent_at' => now()]);
        $this->actingAs($admin)->patch(route('event.settings.update'), [
            'name' => $event->name, 'event_type' => 'Wedding', 'place' => $event->place ?: 'Hall', 'event_date' => now()->addDays(40)->toDateString(),
        ])->assertRedirect();
        $g->refresh();
        $this->assertNull($g->event_day_reminder_sent_at);
        $this->assertNull($g->thank_you_sent_at);
    }

    public function test_overpayment_never_shows_a_negative_balance_or_hides_what_others_owe(): void
    {
        [$event, $admin] = $this->contributionEvent();
        $a = Pledge::factory()->create(['event_id' => $event->id, 'amount' => 100000, 'paid' => 150000, 'pay_token' => Str::random(32)]);
        Pledge::factory()->create(['event_id' => $event->id, 'amount' => 100000, 'paid' => 0, 'pay_token' => Str::random(32)]);

        $this->assertSame(0.0, $a->remaining());
        $this->assertSame(100000.0, $event->stats()['remain']);
    }

    public function test_adding_a_payment_adds_to_the_latest_saved_total(): void
    {
        [$event, $admin] = $this->contributionEvent();
        $p = Pledge::factory()->create(['event_id' => $event->id, 'amount' => 50000, 'paid' => 0, 'pay_token' => Str::random(32), 'name' => 'Pay Pat']);
        $stale = Pledge::find($p->id);
        $p->update(['paid' => 10000]); // someone else just recorded 10,000

        $this->actingAs($admin)->patch(route('pledges.update', $stale), ['name' => 'Pay Pat', 'amount' => 50000, 'phone' => '', 'add_payment' => 10000])->assertRedirect();

        $this->assertSame(20000.0, (float) $p->fresh()->paid);
    }

    public function test_pasted_lists_keep_thousands_amounts_and_handle_semicolons_tabs_and_bom(): void
    {
        $this->assertSame(['John', '0712345678', '1000000'], ImportRows::splitLine('John,0712345678,1,000,000', 3, true));
        $this->assertSame(['Asha', '0712345678', '50000'], ImportRows::splitLine('Asha;0712345678;50000', 3, true));
        $this->assertSame(['Neema', '0712345678', '20,000'], ImportRows::splitLine("Neema\t0712345678\t20,000", 3, true));
        $this->assertSame(['Name', 'Phone'], ImportRows::stripBom(["\xEF\xBB\xBFName", 'Phone']));
    }

    public function test_importing_the_same_pledge_list_twice_does_not_double_it_and_keeps_big_amounts(): void
    {
        [$event, $admin] = $this->contributionEvent();
        $text = "John,0712345678,1,000,000\nAsha;0712345679;50000";

        $this->actingAs($admin)->post(route('pledges.import'), ['import_text' => $text])->assertRedirect();
        $this->actingAs($admin)->post(route('pledges.import'), ['import_text' => $text])->assertRedirect();

        $this->assertSame(2, $event->pledges()->count());
        $this->assertSame(1000000.0, (float) $event->pledges()->where('name', 'John')->value('amount'));
    }

    public function test_importing_the_same_guest_list_twice_does_not_double_it(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $csv = UploadedFile::fake()->createWithContent('g.csv', "\xEF\xBB\xBFAnna,0712345678\nBen,0712345679\n");

        $this->actingAs($admin)->post(route('guests.import'), ['import_file' => $csv])->assertRedirect();
        $this->actingAs($admin)->post(route('guests.import'), ['import_text' => "Anna,0712345678\nBen,0712345679"])->assertRedirect();

        $this->assertSame(['Anna', 'Ben'], $event->pledges()->orderBy('name')->pluck('name')->all());
    }

    public function test_door_search_finds_a_guest_by_the_full_local_number_and_scanners_get_no_money_figures(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $door = $this->memberOf($event, 'scanner');
        $g = $this->guestCard($event, ['name' => 'Local Lulu', 'phone' => '+255712345678', 'amount' => 90000, 'paid' => 10000]);

        $json = $this->actingAs($door)->getJson(route('checkin.search', ['q' => '0712345678']))->assertOk()->json();
        $this->assertCount(1, $json['guests'] ?? $json);

        $verify = $this->actingAs($door)->postJson(route('checkin.verify'), ['token' => $g->invite_token])->assertOk()->json();
        $this->assertArrayNotHasKey('amount', $verify['guest'] ?? $verify);
        $this->assertArrayNotHasKey('paid', $verify['guest'] ?? $verify);
        $this->assertArrayNotHasKey('remain', $verify['guest'] ?? $verify);
    }

    public function test_door_page_escapes_names_inside_attributes(): void
    {
        $this->assertStringContainsString("escapeAttr(name) + '\\'s check-in?", file_get_contents(resource_path('views/event/checkin/index.blade.php')));
    }

    public function test_phone_numbers_with_double_zero_or_a_stray_zero_after_the_country_code_are_fixed(): void
    {
        $n = app(PhoneNumberService::class);

        $this->assertSame('+255712345678', $n->normalize('00255712345678'));
        $this->assertSame('+255712345678', $n->normalize('+2550712345678'));
        $this->assertSame('+255712345678', $n->normalize('2550712345678'));
        $this->assertSame('+255712345678', $n->normalize('0712 345 678'));
        $this->assertSame('+447700900123', $n->normalize('+447700900123'));
    }

    public function test_sms_sending_uses_the_same_phone_format_as_opt_outs(): void
    {
        $m = new \ReflectionMethod(BeemSmsService::class, 'normalizePhone');
        $m->setAccessible(true);
        $beem = app(BeemSmsService::class);

        $this->assertSame('255712345678', $m->invoke($beem, '0712345678'));
        $this->assertSame('255712345678', $m->invoke($beem, '+2550712345678'));
        $this->assertSame('447700900123', $m->invoke($beem, '+447700900123'));
    }

    public function test_seating_refuses_a_full_table_and_a_taken_seat(): void
    {
        [$event, $admin] = $this->ecardEvent(['seating_mode' => 'tables']);
        $table = SeatingTable::create(['event_id' => $event->id, 'name' => 'T1', 'capacity' => 2]);
        $a = $this->guestCard($event, ['name' => 'A', 'seating_table_id' => $table->id, 'seat_number' => 1]);
        $b = $this->guestCard($event, ['name' => 'B']);
        $c = $this->guestCard($event, ['name' => 'C', 'card_type' => 'double']);

        $this->actingAs($admin)->patch(route('seating.assign', $b), ['seating_table_id' => $table->id, 'seat_number' => 1])->assertSessionHas('error');
        $this->assertNull($b->fresh()->seating_table_id);

        $this->actingAs($admin)->patch(route('seating.assign', $c), ['seating_table_id' => $table->id])->assertSessionHas('error'); // a couple needs 2, only 1 left
        $this->assertNull($c->fresh()->seating_table_id);

        $this->actingAs($admin)->patch(route('seating.assign', $b), ['seating_table_id' => $table->id, 'seat_number' => 2])->assertSessionMissing('error');
        $this->assertSame($table->id, $b->fresh()->seating_table_id);
    }

    public function test_guests_with_a_cancelled_card_do_not_use_up_table_seats(): void
    {
        [$event] = $this->ecardEvent();
        $table = SeatingTable::create(['event_id' => $event->id, 'name' => 'T1', 'capacity' => 4]);
        $this->guestCard($event, ['seating_table_id' => $table->id]);
        $this->guestCard($event, ['seating_table_id' => $table->id, 'invite_token' => null]);

        $this->assertSame(1, $table->fresh()->seatsTaken());
    }

    public function test_photo_reports_follow_the_wall_pin_count_once_per_person_and_skip_hidden_photos(): void
    {
        [$event] = $this->ecardEvent(['photo_wall_enabled' => true, 'photo_wall_token' => 'AUDITWALL', 'photo_wall_open_mode' => 'always', 'photo_wall_pin' => '1234']);
        $p = EventPhoto::create(['event_id' => $event->id, 'uploader_key' => 'k', 'thumb' => 'x', 'image' => 'x']);

        $this->postJson(route('wall.report', ['AUDITWALL', $p->id]))->assertForbidden();
        $this->assertSame(0, (int) $p->fresh()->reports);

        $this->withSession(["wall_pin_{$event->id}" => true]);
        foreach (range(1, 5) as $i) {
            $this->postJson(route('wall.report', ['AUDITWALL', $p->id]))->assertOk();
        }
        $this->assertSame(1, (int) $p->fresh()->reports, 'one person counts once');
        $this->assertFalse((bool) $p->fresh()->hidden);
    }

    public function test_an_account_email_is_not_reported_as_sent_when_only_the_log_mailer_is_set(): void
    {
        config(['mail.default' => 'log', 'services.resend.key' => null]);
        $user = User::factory()->create(['email' => 'x@example.com']);

        $this->assertFalse(app(\App\Services\AccountMailer::class)->sendWelcome($user, 'pass-123'));
    }

    public function test_auto_reminders_repeat_on_the_calendar_day_even_if_the_last_send_was_a_few_seconds_later(): void
    {
        $this->assertStringContainsString('startOfDay()->diffInDays', file_get_contents(app_path('Console/Commands/SendDueAutoReminders.php')));
    }

    public function test_env_example_lists_the_sms_keys(): void
    {
        $env = file_get_contents(base_path('.env.example'));
        foreach (['BEEM_API_KEY', 'BEEM_SECRET_KEY', 'BEEM_SENDER_ID', 'GEMINI_API_KEY', 'COMPANY_WHATSAPP'] as $key) {
            $this->assertStringContainsString($key.'=', $env);
        }
    }
}
