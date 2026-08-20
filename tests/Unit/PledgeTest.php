<?php

namespace Tests\Unit;

use App\Models\Pledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_is_amount_minus_paid(): void
    {
        $pledge = Pledge::factory()->create(['amount' => 100000, 'paid' => 30000]);

        $this->assertEquals(70000, $pledge->remaining());
    }

    public function test_remaining_is_zero_when_fully_paid(): void
    {
        $pledge = Pledge::factory()->create(['amount' => 100000, 'paid' => 100000]);

        $this->assertEquals(0, $pledge->remaining());
    }

    public function test_remaining_when_nothing_paid_yet(): void
    {
        $pledge = Pledge::factory()->create(['amount' => 50000, 'paid' => 0]);

        $this->assertEquals(50000, $pledge->remaining());
    }

    public function test_is_paid_in_full_true_when_fully_paid(): void
    {
        $pledge = Pledge::factory()->create(['amount' => 100000, 'paid' => 100000]);

        $this->assertTrue($pledge->isPaidInFull());
    }

    public function test_is_paid_in_full_false_when_balance_remains(): void
    {
        $pledge = Pledge::factory()->create(['amount' => 100000, 'paid' => 99999]);

        $this->assertFalse($pledge->isPaidInFull());
    }

    public function test_is_paid_in_full_false_when_amount_is_zero(): void
    {
        // A zero-amount pledge should never count as "paid in full" — guards
        // against accidentally unlocking an invite link for a $0 record.
        $pledge = Pledge::factory()->create(['amount' => 0, 'paid' => 0]);

        $this->assertFalse($pledge->isPaidInFull());
    }
}
