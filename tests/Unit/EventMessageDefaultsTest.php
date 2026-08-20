<?php

namespace Tests\Unit;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventMessageDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_surface_falls_back_to_default_when_blank(): void
    {
        $event = Event::factory()->create(['provider_message' => null]);

        $this->assertStringContainsString('confirming your booking', $event->messageOrDefault('provider'));
    }

    public function test_reminder_surface_falls_back_to_default_when_blank(): void
    {
        $event = Event::factory()->create(['reminder_message' => null]);

        $this->assertStringContainsString('friendly reminder', $event->messageOrDefault('reminder'));
    }

    public function test_saved_message_overrides_the_default(): void
    {
        $event = Event::factory()->create(['reminder_message' => 'Custom text {name}!']);

        $this->assertEquals('Custom text {name}!', $event->messageOrDefault('reminder'));
    }

    public function test_broadcast_surface_has_no_starter_text(): void
    {
        // Deliberate design choice: broadcast/meeting/schedule must NOT silently
        // default to placeholder content — the admin has to write their own first.
        $event = Event::factory()->create(['broadcast_message' => null]);

        $this->assertEquals('', $event->messageOrDefault('broadcast'));
    }

    public function test_meeting_surface_has_no_starter_text(): void
    {
        $event = Event::factory()->create(['meeting_message' => null]);

        $this->assertEquals('', $event->messageOrDefault('meeting'));
    }

    public function test_schedule_surface_has_no_starter_text(): void
    {
        $event = Event::factory()->create(['schedule_message' => null]);

        $this->assertEquals('', $event->messageOrDefault('schedule'));
    }
}
