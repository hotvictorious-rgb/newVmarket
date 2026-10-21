<?php

namespace Tests\Unit;

use App\Models\LogisticsCompanyCorridorRate;
use App\Services\CustomerDeliveryPricingService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DeliveryCorridorTariffTest extends TestCase
{
    public function test_customer_delivery_fee_is_flat_per_order_plus_bulky_surcharge_per_physical_unit(): void
    {
        $this->assertSame('500.00', CustomerDeliveryPricingService::calculateFee('500.00', '250.00', 0));
        $this->assertSame('1250.00', CustomerDeliveryPricingService::calculateFee('500.00', '250.00', 3));
    }

    public function test_company_payable_uses_its_own_corridor_tariff_not_customer_fee_percentage(): void
    {
        $customerFee = CustomerDeliveryPricingService::calculateFee('500.00', '250.00', 2);
        $companyRate = new LogisticsCompanyCorridorRate([
            'base_payable' => '1600.00',
            'bulky_surcharge_per_unit' => '400.00',
        ]);
        $companyPayable = $companyRate->calculatePayable(2);

        $this->assertSame('1000.00', $customerFee);
        $this->assertSame('2400.00', $companyPayable);
        $this->assertNotSame($customerFee, $companyPayable);
    }

    public function test_negative_bulky_quantity_is_rejected_for_both_tariffs(): void
    {
        $rate = new LogisticsCompanyCorridorRate([
            'base_payable' => '1600.00',
            'bulky_surcharge_per_unit' => '400.00',
        ]);

        try {
            CustomerDeliveryPricingService::calculateFee('500.00', '250.00', -1);
            $this->fail('Negative customer bulky quantity must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Bulky quantity cannot be negative.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $rate->calculatePayable(-1);
    }
}
