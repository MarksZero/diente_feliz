<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Services\Clinic;
class FinanceTest extends TestCase {
    public function test_integer_clp_discount_rounding(): void { $this->assertSame(90000,Clinic::discount(100000,10)); $this->assertSame(0,Clinic::discount(100,100)); $this->assertSame(91,Clinic::discount(101,10)); }
}
