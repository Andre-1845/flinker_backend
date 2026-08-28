<?php

namespace Tests\Unit\Domain\Flink;

use App\Domain\Flink\Services\PricingService;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    public function test_calculates_margin_and_total_from_net_value(): void
    {
        config(['flinker.platform_margin_percent' => 7.0]);

        $result = (new PricingService)->calculate(200.0);

        $this->assertSame(200.0, $result['net_value']);
        $this->assertSame(14.0, $result['platform_margin']);
        $this->assertSame(214.0, $result['total_value']);
        $this->assertSame(7.0, $result['margin_percent']);
    }

    public function test_rounds_to_two_decimal_places(): void
    {
        config(['flinker.platform_margin_percent' => 7.0]);

        // 99.99 * 0.07 = 6.9993 -> deve arredondar pra 7.00
        $result = (new PricingService)->calculate(99.99);

        $this->assertSame(7.0, $result['platform_margin']);
        $this->assertSame(106.99, $result['total_value']);
    }

    public function test_respects_a_different_configured_margin(): void
    {
        config(['flinker.platform_margin_percent' => 10.0]);

        $result = (new PricingService)->calculate(100.0);

        $this->assertSame(10.0, $result['platform_margin']);
        $this->assertSame(110.0, $result['total_value']);
    }

    public function test_zero_net_value_produces_zero_margin_and_total(): void
    {
        config(['flinker.platform_margin_percent' => 7.0]);

        $result = (new PricingService)->calculate(0.0);

        $this->assertSame(0.0, $result['net_value']);
        $this->assertSame(0.0, $result['platform_margin']);
        $this->assertSame(0.0, $result['total_value']);
    }
}
