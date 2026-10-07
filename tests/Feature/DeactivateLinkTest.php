<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Pledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\EventTestHelpers;
use Tests\TestCase;

/** Hosts can switch a guest's invitation link off from the guest list, and switch it back on with a fresh link. */
class DeactivateLinkTest extends TestCase
{
    use EventTestHelpers, RefreshDatabase;

    public function test_contributions_list_offers_deactivate_then_reactivate(): void
    {
        $event = Event::factory()->create(['mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(10)->toDateString(), 'package' => 'full']);
        $admin = $this->memberOf($event, 'admin');
        $p = Pledge::factory()->create(['event_id' => $event->id, 'name' => 'Linked Lina', 'pay_token' => Str::random(32), 'invite_token' => Str::random(32), 'invite_sent_at' => now()]);
        $old = $p->invite_token;

        $this->actingAs($admin)->get(route('guests.index'))->assertOk()->assertSee('Deactivate link')->assertSee(route('delivery.revoke', $p), false);

        $this->actingAs($admin)->post(route('delivery.revoke', $p))->assertSessionHas('status');
        $this->assertNull($p->fresh()->invite_token);
        $this->assertNotSame(200, $this->get(route('guest.rsvp', $old))->getStatusCode());

        $this->actingAs($admin)->get(route('guests.index'))->assertOk()
            ->assertSee('Deactivated')->assertSee('Reactivate')->assertSee(route('delivery.reissue', $p), false)->assertDontSee('Send invite');

        $this->actingAs($admin)->post(route('delivery.reissue', $p))->assertSessionHas('status');
        $p->refresh();
        $this->assertNotNull($p->invite_token);
        $this->assertNotSame($old, $p->invite_token);
        $this->get(route('guest.rsvp', $p->invite_token))->assertOk();
        $this->assertNotSame(200, $this->get(route('guest.rsvp', $old))->getStatusCode());
    }

    public function test_ecard_guest_list_has_the_same_controls(): void
    {
        [$event, $admin] = $this->ecardEvent();
        $g = $this->guestCard($event, ['name' => 'Card Carol']);

        $this->actingAs($admin)->get(route('guests.index'))->assertOk()->assertSee('Deactivate link');

        $this->actingAs($admin)->post(route('delivery.revoke', $g));
        $this->actingAs($admin)->get(route('guests.index'))->assertOk()->assertSee('Link deactivated')->assertSee('Reactivate');

        $this->actingAs($admin)->post(route('delivery.reissue', $g));
        $this->actingAs($admin)->get(route('guests.index'))->assertOk()->assertSee('Deactivate link')->assertDontSee('Link deactivated');
    }

    public function test_viewers_see_no_deactivate_controls_and_cannot_use_them(): void
    {
        [$event] = $this->ecardEvent();
        $viewer = $this->memberOf($event, 'viewer');
        $g = $this->guestCard($event);

        $this->actingAs($viewer)->get(route('guests.index'))->assertOk()->assertDontSee('Deactivate link');
        $this->actingAs($viewer)->post(route('delivery.revoke', $g))->assertForbidden();
        $this->assertNotNull($g->fresh()->invite_token);
    }

    public function test_the_contributions_package_has_no_link_to_deactivate(): void
    {
        $event = Event::factory()->create(['mode' => 'contributions', 'event_type' => 'Wedding', 'event_date' => now()->addDays(10)->toDateString(), 'package' => 'sms']);
        $admin = $this->memberOf($event, 'admin');
        Pledge::factory()->create(['event_id' => $event->id, 'pay_token' => Str::random(32), 'invite_token' => Str::random(32)]);

        $this->actingAs($admin)->get(route('guests.index'))->assertOk()->assertDontSee('Deactivate link');
    }
}
