<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Pledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

class PledgeFilterTest extends TestCase
{
    use EventTestHelpers;
    use RefreshDatabase;

    private function pledge(Event $event, array $attrs): Pledge
    {
        return Pledge::factory()->create(array_merge(['event_id' => $event->id, 'pay_token' => Str::random(32)], $attrs));
    }

    public function test_pledge_list_has_filter_chips_with_counts_and_tagged_rows(): void
    {
        $this->withoutVite();
        $event = Event::factory()->create(['mode' => 'full', 'event_type' => 'Wedding']);
        $admin = $this->memberOf($event, 'admin');
        $this->pledge($event, ['name' => 'Full Fatma', 'amount' => 100000, 'paid' => 100000, 'phone' => '+255712000001']);
        $this->pledge($event, ['name' => 'Part Peter', 'amount' => 100000, 'paid' => 40000, 'phone' => '+255712000002']);
        $this->pledge($event, ['name' => 'None Neema', 'amount' => 100000, 'paid' => 0, 'phone' => null]);

        $html = $this->actingAs($admin)->get(route('pledges.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="pledgeFilters"', $html);
        foreach (['paid', 'part', 'none', 'nophone'] as $f) {
            $this->assertStringContainsString('data-filter="'.$f.'"', $html);
        }
        $this->assertSame(1, substr_count($html, 'data-pay="paid"'));
        $this->assertSame(1, substr_count($html, 'data-pay="part"'));
        $this->assertSame(1, substr_count($html, 'data-pay="none"'));
        $this->assertSame(1, substr_count($html, 'data-phone="no"'));
    }

    public function test_funeral_lists_have_no_payment_filters(): void
    {
        $this->withoutVite();
        $event = Event::factory()->create(['mode' => 'full', 'event_type' => 'Funeral']);
        $admin = $this->memberOf($event, 'admin');
        $this->pledge($event, ['name' => 'A', 'amount' => 0, 'paid' => 5000]);
        $this->pledge($event, ['name' => 'B', 'amount' => 0, 'paid' => 7000]);

        $this->actingAs($admin)->get(route('pledges.index'))->assertOk()->assertDontSee('id="pledgeFilters"', false);
    }
}
