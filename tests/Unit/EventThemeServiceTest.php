<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Services\EventThemeService;
use PHPUnit\Framework\TestCase;

class EventThemeServiceTest extends TestCase
{
    public function test_custom_color_becomes_the_primary_with_derived_shades(): void
    {
        $theme = (new EventThemeService)->fromPrimary('#3D5A99');

        $this->assertSame('#3d5a99', $theme['primary']);
        $this->assertSame('#2b3f6b', $theme['primary_dark']);
        $this->assertSame('#a8b5d1', $theme['accent']);
    }

    public function test_event_without_a_custom_color_uses_its_type_default(): void
    {
        $service = new EventThemeService;
        $event = new Event(['event_type' => 'Wedding']);

        $this->assertSame($service->for('Wedding'), $service->forEvent($event));
    }

    public function test_event_with_a_custom_color_uses_it(): void
    {
        $event = new Event(['event_type' => 'Wedding', 'theme_color' => '#6B3FA0']);

        $this->assertSame('#6b3fa0', (new EventThemeService)->forEvent($event)['primary']);
    }

    public function test_invalid_stored_color_falls_back_to_the_default(): void
    {
        $service = new EventThemeService;
        $event = new Event(['event_type' => 'Wedding', 'theme_color' => 'red']);

        $this->assertSame($service->for('Wedding'), $service->forEvent($event));
    }

    public function test_color_validation_and_readability(): void
    {
        $service = new EventThemeService;

        $this->assertTrue($service->isValidColor('#1F3A52'));
        $this->assertFalse($service->isValidColor('#abc'));
        $this->assertTrue($service->isDarkEnough('#1F3A52'));
        $this->assertFalse($service->isDarkEnough('#ffff00'));
    }

    public function test_every_preset_is_readable_with_white_text(): void
    {
        $service = new EventThemeService;

        foreach (EventThemeService::PRESETS as $name => $hex) {
            $this->assertTrue($service->isDarkEnough($hex), "{$name} is too light");
        }
    }
}
