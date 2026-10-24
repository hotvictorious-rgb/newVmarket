<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Country;
use App\Models\DeliveryLane;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\Order;
use App\Models\OrderSupplierAssignment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\CheckoutExecutionService;
use App\Services\OrderSettlementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SupplierFailoverConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function runDatabaseMigrations()
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->artisan('migrate:fresh');
            $this->beforeApplicationDestroyed(function () {
                $this->artisan('migrate:rollback');
            });
        }
    }


    public function test_two_processes_competing_for_one_failed_assignment_create_one_recovery_only(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('True simultaneous failover row-lock proof runs against PostgreSQL only.');
        }

        $country = Country::firstOrCreate(['iso_code' => 'NG'], ['name' => 'Nigeria', 'phone_code' => '+234', 'currency_code' => 'NGN', 'is_active' => true]);
        $state = State::firstOrCreate(['name' => 'Akwa Ibom'], ['country_id' => $country->id, 'state_code' => 'AK', 'is_active' => true]);
        $uyo = Lga::firstOrCreate(['name' => 'Uyo', 'state_id' => $state->id], ['is_active' => true]);
        $admin = User::factory()->create(['user_type' => 'super_admin', 'status' => 'active']);
        $customer = User::factory()->create(['user_type' => 'customer', 'status' => 'active']);
        $category = Category::create(['name' => 'Concurrency Test', 'slug' => 'concurrency-test-' . uniqid(), 'is_active' => true]);

        $createSupplier = function (string $label) use ($country, $state, $uyo): array {
            $user = User::factory()->create(['user_type' => 'vendor', 'status' => 'active']);
            $vendor = VendorProfile::create([
                'user_id' => $user->id,
                'business_name' => 'Concurrency ' . $label,
                'kyc_status' => 'verified',
                'marketplace_status' => 'active',
            ]);
            $shop = Shop::create([
                'vendor_id' => $vendor->id,
                'country_id' => $country->id,
                'state_id' => $state->id,
                'lga_id' => $uyo->id,
                'name' => 'Concurrency ' . $label . ' Shop',
                'slug' => 'concurrency-' . strtolower($label) . '-' . uniqid(),
                'address' => '10 Aka Road, Uyo',
                'is_active' => true,
                'is_verified' => true,
            ]);
            return [$user, $vendor, $shop];
        };

        [$primaryUser, $primaryVendor, $primaryShop] = $createSupplier('Primary');
        [$backupUser, $backupVendor, $backupShop] = $createSupplier('Backup');

        $product = Product::create([
            'vendor_id' => null,
            'shop_id' => null,
            'category_id' => $category->id,
            'name' => 'Failover Concurrency Product',
            'slug' => 'failover-concurrency-product-' . uniqid(),
            'unit_price' => '32000.00',
            'cost_price' => '0.00',
            'is_in_stock' => true,
            'inventory_quantity' => 0,
            'is_approved' => true,
            'listing_status' => 'listed',
            'listing_confirmed_at' => Carbon::now()->subMinute(),
            'freshness_confirmed_at' => Carbon::now()->subMinute(),
        ]);
        $primaryOffer = SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $primaryVendor->id,
            'shop_id' => $primaryShop->id,
            'wholesale_cost' => '25000.00',
            'is_available' => true,
            'availability_confirmed_at' => now()->subMinute(),
            'availability_confirmed_by' => $admin->id,
            'availability_expires_at' => now()->addHours(24),
        ]);
        SupplierOffer::create([
            'product_id' => $product->id,
            'vendor_id' => $backupVendor->id,
            'shop_id' => $backupShop->id,
            'wholesale_cost' => '27000.00',
            'is_available' => true,
            'availability_confirmed_at' => now()->subMinute(),
            'availability_confirmed_by' => $admin->id,
            'availability_expires_at' => now()->addHours(24),
        ]);

        DeliveryLane::create([
            'origin_lga_id' => $uyo->id,
            'destination_lga_id' => $uyo->id,
            'base_fee' => '500.00',
            'bulky_surcharge' => '0.00',
            'logistics_company_base_payable' => '450.00',
            'logistics_company_bulky_surcharge' => '0.00',
            'estimated_delivery_hours_min' => 2,
            'estimated_delivery_hours_max' => 4,
            'is_active' => true,
        ]);

        $checkout = app(CheckoutExecutionService::class)->execute($customer, [
            'fulfillment_type' => 'doorstep_delivery',
            'destination_lga_id' => $uyo->id,
            '_checkout_idempotency_key' => 'IDEMP-CONCURRENT-FAILOVER-' . uniqid(),
            '_checkout_request_hash' => hash('sha256', 'concurrent supplier failover proof'),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $this->assertTrue($checkout['success'], $checkout['message'] ?? 'Checkout failed.');
        $order = $checkout['order'];
        $settlement = app(OrderSettlementService::class)->settlePaystackOrder($order, [
            'status' => 'success',
            'currency' => 'NGN',
            'amount' => (int) bcmul((string) $order->grand_total, '100', 0),
            'reference' => (string) $order->payment_reference,
            'id' => 'TX-CONCURRENT-FAILOVER-' . $order->id,
            'channel' => 'card',
        ], $customer);
        $this->assertTrue($settlement['success'] ?? false, $settlement['message'] ?? 'Settlement failed.');

        $order->refresh();
        $item = $order->items()->firstOrFail();
        $failedAssignment = OrderSupplierAssignment::where('order_id', $order->id)
            ->where('supplier_vendor_id', $item->fulfillment_supplier_vendor_id)
            ->firstOrFail();
        $this->assertSame((int) $primaryVendor->id, (int) $item->fulfillment_supplier_vendor_id);
        $primaryOffer->forceFill(['is_available' => false])->save();
        $failedAssignment->forceFill(['status' => 'supplier_rejected'])->save();
        $order->forceFill(['sourcing_status' => 'supplier_rejected'])->save();

        $processes = [];
        for ($worker = 0; $worker < 2; $worker++) {
            $process = new Process([
                PHP_BINARY,
                'artisan',
                'suppliers:attempt-automatic-failover',
                (string) $order->id,
                (string) $failedAssignment->id,
            ], base_path());
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        }

        $order->refresh();
        $item->refresh();
        $this->assertSame((int) $backupVendor->id, (int) $item->fulfillment_supplier_vendor_id);
        $this->assertSame('27000.00', (string) $item->fulfillment_supplier_cost_snapshot);
        $this->assertSame(2, OrderSupplierAssignment::where('order_id', $order->id)->count());
        $this->assertSame(1, AdminAuditLog::where('target_type', Order::class)->where('target_id', $order->id)
            ->where('action', 'automatic_supplier_failover_applied')->count());

        $oldSupplierNet = '0.00';
        foreach (FinancialJournalEntry::where('order_id', $order->id)->where('account_name', 'supplier_payable')
            ->where('beneficiary_type', VendorProfile::class)->where('beneficiary_id', $primaryVendor->id)->get() as $entry) {
            $oldSupplierNet = $entry->entry_type === 'credit'
                ? bcadd($oldSupplierNet, (string) $entry->amount, 2)
                : bcsub($oldSupplierNet, (string) $entry->amount, 2);
        }
        $newSupplierNet = '0.00';
        foreach (FinancialJournalEntry::where('order_id', $order->id)->where('account_name', 'supplier_payable')
            ->where('beneficiary_type', VendorProfile::class)->where('beneficiary_id', $backupVendor->id)->get() as $entry) {
            $newSupplierNet = $entry->entry_type === 'credit'
                ? bcadd($newSupplierNet, (string) $entry->amount, 2)
                : bcsub($newSupplierNet, (string) $entry->amount, 2);
        }
        $this->assertSame('0.00', $oldSupplierNet);
        $this->assertSame('27000.00', $newSupplierNet);

        // DatabaseMigrations rolls the schema back after this test. Restore legacy
        // non-null ownership only after all behavioural assertions, so the migration's
        // safety guard is not bypassed and no test fixture leaves invalid legacy rows.
        $order->forceFill(['vendor_id' => $primaryVendor->id, 'shop_id' => $primaryShop->id])->save();
        $product->forceFill(['vendor_id' => $primaryVendor->id, 'shop_id' => $primaryShop->id])->save();
    }
}
