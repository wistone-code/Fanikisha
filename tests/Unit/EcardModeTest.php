<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Services\NavLabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcardModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_default_to_the_full_contributions_mode(): void
    {
        $event = Event::factory()->create();

        $this->assertFalse($event->fresh()->isEcard());
    }

    public function test_ecard_mode_is_detected(): void
    {
        $event = Event::factory()->create(['mode' => Event::MODE_ECARD]);

        $this->assertTrue($event->isEcard());
    }

    public function test_ecard_types_exclude_funeral_only(): void
    {
        $types = Event::ecardTypes();

        $this->assertNotContains('Funeral', $types);
        $this->assertContains('Wedding', $types);
        $this->assertCount(count(Event::TYPES) - 1, $types);
    }

    public function test_ecard_menu_is_limited_to_guest_cards_team_photos_and_settings(): void
    {
        $event = Event::factory()->create(['mode' => Event::MODE_ECARD]);
        $ids = array_column(app(NavLabelService::class)->itemsFor($event, true), 'id');

        $this->assertSame(['home', 'invitations', 'team', 'photos', 'settings'], $ids);
    }

    public function test_viewers_of_an_ecard_event_do_not_see_settings(): void
    {
        $event = Event::factory()->create(['mode' => Event::MODE_ECARD]);
        $ids = array_column(app(NavLabelService::class)->itemsFor($event, false), 'id');

        $this->assertSame(['home', 'invitations'], $ids);
    }
}
