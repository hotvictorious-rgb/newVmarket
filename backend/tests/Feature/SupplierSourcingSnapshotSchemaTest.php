<?php

namespace Tests\Feature;

use App\Models\OrderItem;
use App\Models\SupplierOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SupplierSourcingSnapshotSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_items_have_nullable_supplier_snapshot_fields_for_legacy_compatibility(): void
    {
        $this->assertTrue(Schema::hasColumns('order_items', [
            'supplier_offer_id',
            'supplier_vendor_id',
            'supplier_shop_id',
            'supplier_name_snapshot',
            'supplier_shop_name_snapshot',
            'supplier_cost_snapshot',
            'supplier_cost_currency',
            'supplier_snapshot_at',
        ]));

        $this->assertTrue(Schema::hasColumns('order_items', [
            'fulfillment_supplier_offer_id',
            'fulfillment_supplier_vendor_id',
            'fulfillment_supplier_shop_id',
            'fulfillment_supplier_name_snapshot',
            'fulfillment_supplier_shop_name_snapshot',
            'fulfillment_supplier_cost_snapshot',
            'fulfillment_supplier_snapshot_at',
        ]));
        $this->assertTrue(Schema::hasColumn('orders', 'fulfillment_supplier_cost_total_snapshot'));
        $this->assertContains('supplier_offer_id', (new OrderItem())->getFillable());
        $this->assertContains('supplier_cost_snapshot', (new OrderItem())->getFillable());
        $this->assertContains('fulfillment_supplier_vendor_id', (new OrderItem())->getFillable());
        $this->assertContains('fulfillment_supplier_cost_snapshot', (new OrderItem())->getFillable());
        $this->assertContains('fulfillment_supplier_cost_total_snapshot', (new Order())->getFillable());
        $this->assertContains('wholesale_cost', (new SupplierOffer())->getFillable());
        $this->assertTrue(Schema::hasColumns('supplier_offers', [
            'availability_confirmed_at',
            'availability_confirmed_by',
            'availability_expires_at',
        ]));
        $this->assertContains('availability_confirmed_at', (new SupplierOffer())->getFillable());
        $this->assertContains('availability_expires_at', (new SupplierOffer())->getFillable());
    }
}
