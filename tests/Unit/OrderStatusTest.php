<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_transitions(): void
    {
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Accepted));
        $this->assertFalse(OrderStatus::Pending->canTransitionTo(OrderStatus::Ready));
        $this->assertFalse(OrderStatus::Completed->canTransitionTo(OrderStatus::Cancelled));
        $this->assertFalse(OrderStatus::Preparing->canTransitionTo(OrderStatus::Cancelled));
    }

    public function test_malaysian_5_sen_rounding(): void
    {
        $this->assertSame(1725, Money::roundTo5Sen(1726));
        $this->assertSame(1730, Money::roundTo5Sen(1728));
        $this->assertSame(1725, Money::roundTo5Sen(1723));
        $this->assertSame('RM 17.25', Money::format(1725));
    }
}
