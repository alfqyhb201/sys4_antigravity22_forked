<?php

namespace Tests\Unit;

use App\Models\Contract;
use Tests\TestCase;

class ContractCalculationTest extends TestCase
{
    /**
     * Test the custom calculation of monthly designs count based on weekly count.
     */
    public function test_calculate_monthly_designs_count(): void
    {
        $this->assertEquals(0, Contract::calculateMonthlyDesignsCount(0));
        $this->assertEquals(4, Contract::calculateMonthlyDesignsCount(1));
        $this->assertEquals(8, Contract::calculateMonthlyDesignsCount(2));
        $this->assertEquals(12, Contract::calculateMonthlyDesignsCount(3));
        $this->assertEquals(17, Contract::calculateMonthlyDesignsCount(4)); // 4 * 4 + 1
        $this->assertEquals(21, Contract::calculateMonthlyDesignsCount(5)); // 5 * 4 + 1
        $this->assertEquals(26, Contract::calculateMonthlyDesignsCount(6)); // 6 * 4 + 2
        $this->assertEquals(30, Contract::calculateMonthlyDesignsCount(7)); // 7 * 4 + 2
        $this->assertEquals(32, Contract::calculateMonthlyDesignsCount(8)); // 8 * 4 + 0
    }
}
