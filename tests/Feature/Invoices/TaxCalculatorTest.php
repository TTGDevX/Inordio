<?php

namespace Tests\Feature\Invoices;

use App\Enums\Province;
use App\Services\TaxCalculator;
use Tests\TestCase;

class TaxCalculatorTest extends TestCase
{
    private function calc(): TaxCalculator
    {
        return app(TaxCalculator::class);
    }

    public function test_ontario_is_a_single_13_percent_hst_line(): void
    {
        $result = $this->calc()->calculate(Province::ON, 100.0);

        $this->assertCount(1, $result['lines']);
        $this->assertEqualsWithDelta(13.0, $result['total'], 0.001);
        $this->assertSame(13.0, $result['lines'][0]['rate']);
    }

    public function test_nova_scotia_uses_the_updated_14_percent_rate(): void
    {
        $result = $this->calc()->calculate(Province::NS, 100.0);

        $this->assertEqualsWithDelta(14.0, $result['total'], 0.001);
    }

    public function test_quebec_has_two_components_gst_and_qst(): void
    {
        $result = $this->calc()->calculate(Province::QC, 200.0);

        $this->assertCount(2, $result['lines']);
        // GST 5% of 200 = 10.00; QST 9.975% of 200 = 19.95; total 29.95.
        $this->assertEqualsWithDelta(29.95, $result['total'], 0.01);
    }

    public function test_alberta_is_gst_only(): void
    {
        $result = $this->calc()->calculate(Province::AB, 100.0);

        $this->assertCount(1, $result['lines']);
        $this->assertEqualsWithDelta(5.0, $result['total'], 0.001);
    }

    public function test_tax_exempt_yields_no_tax(): void
    {
        $result = $this->calc()->calculate(Province::ON, 100.0, exempt: true);

        $this->assertSame([], $result['lines']);
        $this->assertSame(0.0, $result['total']);
    }

    public function test_unknown_province_yields_no_tax(): void
    {
        $result = $this->calc()->calculate(null, 100.0);

        $this->assertSame(0.0, $result['total']);
    }
}
