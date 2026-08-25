<?php

namespace Tests\Unit;

use App\Services\BusinessCalculator;
use PHPUnit\Framework\TestCase;

class BusinessCalculatorTest extends TestCase
{
    public function test_optional_commission_is_calculated_precisely(): void
    {
        $this->assertSame('80000.00', (new BusinessCalculator)->commission('1000000', '8'));
    }

    public function test_salary_with_percentage_irsa(): void
    {
        $this->assertSame(['irsa'=>'50000.00','net'=>'950000.00'], (new BusinessCalculator)->salary('1000000','pourcentage','5'));
    }

    public function test_salary_with_fixed_irsa(): void
    {
        $this->assertSame(['irsa'=>'75000.00','net'=>'925000.00'], (new BusinessCalculator)->salary('1000000','fixe','75000'));
    }
}
