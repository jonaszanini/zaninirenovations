<?php

namespace Tests\Unit\Services;

use App\Services\QuotationPaymentSchedule;
use PHPUnit\Framework\TestCase;

class QuotationPaymentScheduleTest extends TestCase
{
    public function test_default_percentages(): void
    {
        $this->assertEquals([50.0, 50.0], QuotationPaymentSchedule::percentages(2));
        $this->assertEquals([30.0, 30.0, 40.0], QuotationPaymentSchedule::percentages(3));
        $this->assertEquals([20.0, 20.0, 30.0, 30.0], QuotationPaymentSchedule::percentages(4));
    }

    public function test_custom_percentages(): void
    {
        $custom = [40, 60];
        $this->assertEquals([40.0, 60.0], QuotationPaymentSchedule::percentages(2, $custom));

        $terms = QuotationPaymentSchedule::terms(2, $custom);
        $this->assertEquals('40% Secure Booking Deposit', $terms[0][0]);
        $this->assertEquals('60% Completion Payment', $terms[1][0]);
    }

    public function test_invalid_custom_percentages_fallbacks_to_default(): void
    {
        // Sum does not equal 100
        $invalidSum = [30, 50];
        $this->assertEquals([50.0, 50.0], QuotationPaymentSchedule::percentages(2, $invalidSum));

        // Wrong count
        $invalidCount = [20, 30, 50];
        $this->assertEquals([50.0, 50.0], QuotationPaymentSchedule::percentages(2, $invalidCount));
    }

    public function test_validation_rules_support_nested_percentages(): void
    {
        $rules = QuotationPaymentSchedule::validationRules();
        $this->assertArrayHasKey('payment_percentages', $rules);
        $this->assertArrayHasKey('payment_percentages.*', $rules);
        $this->assertArrayHasKey('payment_percentages.*.*', $rules);
    }
}
