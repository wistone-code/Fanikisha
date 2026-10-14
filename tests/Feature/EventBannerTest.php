<?php

namespace Tests\Feature;

use App\Models\AdminMessage;
use App\Models\User;
use App\Services\EventBanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class EventBannerTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    private function banner($event, $name = 'Amina Juma'): ?array
    {
        return app(EventBanner::class)->for($event->fresh(), $name);
    }

    public function test_no_banner_on_an_ordinary_day_with_plenty_of_sms(): void
    {
        [$event] = $this->ecardEvent(['sms_quota' => 500, 'sms_sent_count' => 10]);

        $this->assertNull($this->banner($event));
    }

    public function test_priority_is_finished_quota_then_admin_message_then_wishes_then_low_quota(): void
    {
        [$event] = $this->ecardEvent(['event_date' => today()->toDateString(), 'sms_quota' => 150, 'sms_sent_count' => 100]);

        // Event day, low quota: the wishes win over "running low".
        $this->assertSame('wishes', $this->banner($event)['kind']);

        // An admin message wins over the wishes.
        AdminMessage::create(['event_id' => $event->id, 'body' => 'Check-in opens at 3pm.']);
        $this->assertSame('admin', $this->banner($event)['kind']);
        $this->assertSame('Check-in opens at 3pm.', $this->banner($event)['text']);

        // A finished quota wins over everything.
        $event->update(['sms_sent_count' => 150]);
        $this->assertSame('quota_finished', $this->banner($event)['kind']);
    }

    public function test_low_quota_shows_when_nothing_else_applies(): void
    {
        [$event] = $this->ecardEvent(['sms_quota' => 150, 'sms_sent_count' => 100]);

        $this->assertSame('quota_low', $this->banner($event)['kind']);
        $this->assertFalse($this->banner($event)['dismissible']);
    }

    public function test_wishes_are_only_for_the_event_day_and_use_the_host_first_name(): void
    {
        [$event] = $this->ecardEvent(['event_date' => today()->toDateString(), 'name' => 'Amina & Juma Wedding']);
        $b = $this->banner($event, 'Amina Juma');

        $this->assertSame('wishes', $b['kind']);
        $this->assertStringContainsString('Amina', $b['text']);
        $this->assertStringContainsString('Amina & Juma Wedding', $b['text']);
        $this->assertStringNotContainsString('Juma Good', $b['text']);

        $event->update(['event_date' => today()->addDay()->toDateString()]);
        $this->assertNull($this->banner($event));
    }

    public function test_funeral_wishes_are_gentle_and_swahili_is_used_when_the_event_is_in_swahili(): void
    {
        [$event] = $this->ecardEvent(['event_type' => 'Funeral', 'event_date' => today()->toDateString()]);
        $this->assertStringContainsString('peace and comfort', $this->banner($event)['text']);
        $this->assertStringNotContainsString('joyful', $this->banner($event)['text']);

        $event->update(['sms_language' => 'sw']);
        $this->assertStringContainsString('Pole sana', $this->banner($event)['text']);
    }

    public function test_the_host_can_dismiss_the_admin_message_and_then_todays_wishes(): void
    {
        [$event, $host] = $this->ecardEvent(['event_date' => today()->toDateString()]);
        AdminMessage::create(['event_id' => $event->id, 'body' => 'Hello host']);

        $this->actingAs($host)->get(route('dashboard'))->assertSee('Hello host');
        $this->actingAs($host)->post(route('banner.dismiss'))->assertRedirect();
        $this->assertNotNull(AdminMessage::first()->dismissed_at);

        // Now the wishes show; dismissing them hides them for the rest of the day.
        $this->actingAs($host)->get(route('dashboard'))->assertSee('Good morning');
        $this->actingAs($host)->post(route('banner.dismiss'));
        $this->actingAs($host)->get(route('dashboard'))->assertDontSee('Good morning')->assertDontSee('Got it');
    }

    public function test_admin_sends_a_message_to_the_owner_and_it_replaces_an_unread_one(): void
    {
        Http::fake(['*' => Http::response(['successful' => true, 'valid' => 1])]);
        [$event, $host] = $this->ecardEvent();
        $super = User::factory()->create(['is_super_user' => true]);

        $this->actingAs($super)->post(route('admin.users.message', $host), ['body' => 'First note'])->assertSessionHas('status');
        $this->actingAs($super)->post(route('admin.users.message', $host), ['body' => 'Second note'])->assertSessionHas('status');

        $this->assertSame('Second note', $this->banner($event)['text']);
        $this->assertSame(1, AdminMessage::whereNull('dismissed_at')->count());
    }

    public function test_only_the_system_admin_can_send_and_the_message_has_a_length_limit(): void
    {
        [$event, $host] = $this->ecardEvent();
        $super = User::factory()->create(['is_super_user' => true]);

        $this->actingAs($host)->post(route('admin.users.message', $host), ['body' => 'hi'])->assertForbidden();
        $this->actingAs($super)->post(route('admin.users.message', $host), ['body' => str_repeat('a', 301)])->assertSessionHasErrors('body');
        $this->assertSame(0, AdminMessage::count());
    }
}
