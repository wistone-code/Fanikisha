<?php

namespace Tests\Unit;

use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_is_budget_minus_paid(): void
    {
        $provider = Provider::factory()->create(['budget' => 1000000, 'paid' => 400000]);

        $this->assertEquals(600000, $provider->remaining());
    }

    public function test_remaining_is_zero_when_fully_paid(): void
    {
        $provider = Provider::factory()->create(['budget' => 500000, 'paid' => 500000]);

        $this->assertEquals(0, $provider->remaining());
    }

    public function test_remaining_when_nothing_paid_yet(): void
    {
        $provider = Provider::factory()->create(['budget' => 750000, 'paid' => 0]);

        $this->assertEquals(750000, $provider->remaining());
    }
}
