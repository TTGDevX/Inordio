<?php

namespace Tests\Unit;

use App\Support\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_sum_avoids_floating_point_drift(): void
    {
        // Raw float addition drifts...
        $this->assertNotSame(0.3, 0.1 + 0.1 + 0.1);

        // ...the helper sums in cents, so it doesn't.
        $this->assertSame(30, Money::cents(Money::sum([0.1, 0.1, 0.1])));
    }

    public function test_cents_and_round(): void
    {
        $this->assertSame(1300, Money::cents(13.0));
        $this->assertSame(1995, Money::cents(19.95));
        $this->assertSame(1995, Money::cents('19.95'));   // decimal:2 cast returns strings
        $this->assertSame(0, Money::cents(0));
    }

    public function test_sum_of_rounded_line_totals(): void
    {
        // 2 @ 50.00 + 3 @ 10.00 = 130.00
        $this->assertSame(13000, Money::cents(Money::sum([100.0, 30.0])));
    }
}
