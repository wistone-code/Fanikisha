<?php

namespace Tests\Unit;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventSmsQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlimited_when_no_quota_set(): void
    {
        $event = Event::factory()->create(['sms_quota' => null, 'sms_sent_count' => 500]);

        $this->assertNull($event->smsRemaining());
        $this->assertTrue($event->hasSmsCapacity(1000));
    }

    public function test_has_capacity_when_under_quota(): void
    {
        $event = Event::factory()->create(['sms_quota' => 100, 'sms_sent_count' => 40]);

        $this->assertTrue($event->hasSmsCapacity(60));
        $this->assertEquals(60, $event->smsRemaining());
    }

    public function test_refuses_when_send_would_exceed_quota(): void
    {
        $event = Event::factory()->create(['sms_quota' => 100, 'sms_sent_count' => 40]);

        $this->assertFalse($event->hasSmsCapacity(61));
    }

    public function test_exactly_at_the_limit_is_allowed(): void
    {
        // A send that lands EXACTLY on the quota should succeed, not be
        // treated as "over" — off-by-one is the classic bug here.
        $event = Event::factory()->create(['sms_quota' => 100, 'sms_sent_count' => 40]);

        $this->assertTrue($event->hasSmsCapacity(60));
    }

    public function test_remaining_never_goes_negative_if_overshot(): void
    {
        // sms_sent_count could theoretically exceed sms_quota if the quota was
        // lowered after some sends already happened — remaining should floor at 0.
        $event = Event::factory()->create(['sms_quota' => 50, 'sms_sent_count' => 80]);

        $this->assertEquals(0, $event->smsRemaining());
        $this->assertFalse($event->hasSmsCapacity(1));
    }

    public function test_zero_quota_blocks_all_sending(): void
    {
        $event = Event::factory()->create(['sms_quota' => 0, 'sms_sent_count' => 0]);

        $this->assertFalse($event->hasSmsCapacity(1));
        $this->assertEquals(0, $event->smsRemaining());
    }
}
