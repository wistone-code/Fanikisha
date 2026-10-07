<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Pledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

/** A pledger who has cleared the whole amount is thanked — never reminded and never in the broadcast. */
class PledgeClearedTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function beem(): void
    {
        config(['services.beem.api_key' => 'k', 'services.beem.secret_key' => 's']);
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true, 'valid' => 1, 'invalid' => 0, 'request_id' => 1])]);
    }

    private function contributionEvent(array $attrs = []): array
    {
        $event = Event::factory()->create(array_merge(['event_type' => 'Wedding', 'event_date' => now()->addDays(20)->toDateString()], $attrs));

        return [$event, $this->memberOf($event, 'admin')];
    }

    private function pledge(Event $event, array $attrs = []): Pledge
    {
        return Pledge::factory()->create(array_merge(['event_id' => $event->id, 'name' => 'Asha', 'phone' => '255712345678', 'amount' => 100000, 'paid' => 0, 'pay_token' => \Illuminate\Support\Str::random(32)], $attrs));
    }

    private function sentMessages(): array
    {
        return Http::recorded()->map(fn ($pair) => $pair[0]['message'])->values()->all();
    }

    public function test_the_payment_that_clears_the_pledge_sends_a_thank_you_not_a_balance_message(): void
    {
        $this->beem();
        [$event, $admin] = $this->contributionEvent();
        $p = $this->pledge($event, ['paid' => 60000]);

        $this->actingAs($admin)->patch(route('pledges.update', $p), ['name' => 'Asha', 'amount' => 100000, 'phone' => '255712345678', 'add_payment' => 40000])
            ->assertSessionHas('status');

        $messages = $this->sentMessages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('thank you for your contribution of 100,000', $messages[0]);
        $this->assertStringContainsString('fully paid', $messages[0]);
        $this->assertStringNotContainsString('Remaining balance', $messages[0]);
        $this->assertStringNotContainsString('remind', strtolower($messages[0]));
    }

    public function test_a_part_payment_still_shows_the_balance(): void
    {
        $this->beem();
        [$event, $admin] = $this->contributionEvent();
        $p = $this->pledge($event);

        $this->actingAs($admin)->patch(route('pledges.update', $p), ['name' => 'Asha', 'amount' => 100000, 'phone' => '255712345678', 'add_payment' => 30000]);

        $this->assertStringContainsString('Remaining balance: 70,000', $this->sentMessages()[0]);
    }

    public function test_the_thank_you_follows_the_event_sms_language(): void
    {
        $this->beem();
        [$event, $admin] = $this->contributionEvent(['sms_language' => 'sw']);
        $p = $this->pledge($event);

        $this->actingAs($admin)->patch(route('pledges.update', $p), ['name' => 'Asha', 'amount' => 100000, 'phone' => '255712345678', 'add_payment' => 100000]);

        $this->assertStringContainsString('asante sana kwa mchango wako wa 100,000', $this->sentMessages()[0]);
        $this->assertStringContainsString('Umekamilisha mchango wako wote', $this->sentMessages()[0]);
    }

    public function test_the_reminder_button_thanks_someone_who_has_already_paid_in_full(): void
    {
        $this->beem();
        [$event, $admin] = $this->contributionEvent();
        $cleared = $this->pledge($event, ['paid' => 100000]);
        $owing = $this->pledge($event, ['name' => 'Baraka', 'phone' => '255712345679', 'paid' => 20000]);

        $this->actingAs($admin)->post(route('pledges.remind.sms', $cleared))->assertSessionHas('status');
        $this->actingAs($admin)->post(route('pledges.remind.sms', $owing))->assertSessionHas('status');

        [$first, $second] = $this->sentMessages();
        $this->assertStringContainsString('fully paid', $first);
        $this->assertStringNotContainsString('friendly reminder', $first);
        $this->assertStringContainsString('friendly reminder', $second);
    }

    public function test_the_broadcast_goes_only_to_people_who_still_owe(): void
    {
        $this->beem();
        [$event, $admin] = $this->contributionEvent(['broadcast_message' => 'Please complete your contribution.']);
        $this->pledge($event, ['name' => 'Cleared', 'phone' => '255700000001', 'paid' => 100000]);
        $this->pledge($event, ['name' => 'Overpaid', 'phone' => '255700000002', 'paid' => 150000]);
        $this->pledge($event, ['name' => 'Owing', 'phone' => '255700000003', 'paid' => 10000]);

        $this->actingAs($admin)->post(route('pledges.remind-all.sms'))->assertSessionHas('status');

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $numbers = collect($request['recipients'])->pluck('dest_addr')->all();

            return $numbers === ['255700000003'];
        });
    }

    public function test_the_reminder_tab_lists_only_outstanding_pledges(): void
    {
        [$event, $admin] = $this->contributionEvent();
        $this->pledge($event, ['name' => 'Cleared Person', 'paid' => 100000]);
        $this->pledge($event, ['name' => 'Owing Person', 'phone' => '255712345679', 'paid' => 1000]);

        $this->actingAs($admin)->get(route('pledges.index', ['tab' => 'remind']))->assertOk()
            ->assertSee('Owing Person')->assertDontSee('Cleared Person');
    }

    public function test_the_automatic_reminder_skips_people_who_have_cleared_their_pledge(): void
    {
        $this->beem();
        $this->travelTo(now()->setTime(9, 0));
        [$event] = $this->contributionEvent(['reminder_auto_enabled' => true, 'reminder_auto_time' => '09:00:00', 'reminder_auto_frequency_days' => 1, 'broadcast_message' => 'Please complete your contribution.']);
        $this->pledge($event, ['phone' => '255700000001', 'paid' => 100000]);
        $this->pledge($event, ['phone' => '255700000003', 'paid' => 5000]);

        $this->artisan('reminders:send-due')->assertSuccessful();

        Http::assertSent(fn ($request) => collect($request['recipients'])->pluck('dest_addr')->all() === ['255700000003']);
    }
}
