<?php

namespace App\Console\Commands;

use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\Country;
use App\Models\FinancialJournalEntry;
use App\Models\Lga;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSupplierAssignment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\State;
use App\Models\SupplierOffer;
use App\Models\User;
use App\Models\VendorProfile;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class StressTestSettlementConcurrencyCommand extends Command
{
    protected $signature = 'finance:stress-settlement {--workers=50} {--stock=10} {--cleanup}';
    protected $description = 'Dispatches parallel OS processes against separate database connections to stress-test settlement concurrency';

    public function handle(): int
    {
        $numWorkers = (int) $this->option('workers');
        $initialStock = (int) $this->option('stock');

        $this->info("===============================================================");
        $this->info("STARTING REAL CONCURRENCY STRESS TEST");
        $this->info("Workers (Concurrent Processes): {$numWorkers}");
        $this->info("Initial Catalog Inventory:      {$initialStock}");
        $this->info("===============================================================");

        // 1. Setup entities
        $country = Country::firstOrCreate(['iso_code' => 'NG'], ['name' => 'Nigeria', 'phone_code' => '+234', 'currency_code' => 'NGN', 'is_active' => true]);
        $state = State::firstOrCreate(['name' => 'Akwa Ibom', 'country_id' => $country->id], ['state_code' => 'AK', 'is_active' => true]);
        $uyoLga = Lga::firstOrCreate(['name' => 'Uyo', 'state_id' => $state->id], ['is_active' => true]);

        $category = Category::firstOrCreate(['slug' => 'stress-test-cat'], ['name' => 'Stress Test Category', 'is_active' => true]);

        $customer = User::firstOrCreate(
            ['email' => 'stress_customer@vmarket.ng'],
            ['name' => 'Stress Shopper', 'phone' => '08090002222', 'password' => bcrypt('Pass123!'), 'user_type' => 'customer', 'status' => 'active']
        );

        $testRunId = 'STRESS_' . time();
        $product = Product::create([
            'category_id' => $category->id,
            'name' => "Stress Scarce SKU {$testRunId}",
            'slug' => "stress-scarce-sku-{$testRunId}",
            'unit_price' => '50000.00',
            'package_tier' => 'bike',
            'condition' => 'brand_new',
            'inventory_quantity' => $initialStock,
            'status' => 'active',
            'is_verified' => true,
            'is_approved' => true,
            'listing_status' => 'listed',
            'is_in_stock' => true,
            'listing_confirmed_at' => Carbon::now()->subMinute(),
        ]);

        // Each supplier vendor is constrained by the platform rule: >= 5 pending handovers cap.
        // Therefore, to support fulfilling $initialStock units under test, create max(1, ceil($initialStock / 3)) suppliers.
        $numSuppliers = (int) max(1, ceil($initialStock / 3));
        $suppliers = [];
        for ($s = 1; $s <= $numSuppliers; $s++) {
            $vUser = User::firstOrCreate(
                ['email' => "stress_vendor_{$testRunId}_{$s}@vmarket.ng"],
                ['name' => "Stress Vendor User {$s}", 'phone' => "0807777" . str_pad((string)$s, 4, '0', STR_PAD_LEFT), 'password' => bcrypt('Pass123!'), 'user_type' => 'vendor', 'status' => 'active']
            );
            $vProfile = VendorProfile::firstOrCreate(
                ['user_id' => $vUser->id],
                ['business_name' => "Stress Vendor Ltd {$s}", 'kyc_status' => 'verified', 'marketplace_status' => 'active']
            );
            $vShop = Shop::firstOrCreate(
                ['slug' => "stress-vendor-depot-{$testRunId}-{$s}"],
                ['vendor_id' => $vProfile->id, 'country_id' => $country->id, 'state_id' => $state->id, 'lga_id' => $uyoLga->id, 'name' => "Stress Depot {$s}", 'address' => "10 Abak Road, Uyo Depot {$s}", 'phone' => "0807777" . str_pad((string)$s, 4, '0', STR_PAD_LEFT), 'is_active' => true, 'pickup_enabled' => true]
            );
            $vOffer = SupplierOffer::create([
                'product_id' => $product->id,
                'vendor_id' => $vProfile->id,
                'shop_id' => $vShop->id,
                'wholesale_cost' => '40000.00',
                'is_available' => true,
                'availability_confirmed_at' => Carbon::now()->subMinute(),
                'availability_confirmed_by' => $vUser->id,
                'availability_expires_at' => Carbon::now()->addDays(7),
            ]);
            $suppliers[] = ['vendor' => $vProfile, 'shop' => $vShop, 'offer' => $vOffer];
        }

        // 2. Create orders
        $this->info("Creating {$numWorkers} competing orders across {$numSuppliers} suppliers (5-handover cap per supplier)...");
        $orders = [];
        for ($i = 1; $i <= $numWorkers; $i++) {
            $assignedSupplier = $suppliers[($i - 1) % $numSuppliers];
            $vendor = $assignedSupplier['vendor'];
            $shop = $assignedSupplier['shop'];
            $offer = $assignedSupplier['offer'];

            $order = Order::create([
                'order_number' => "VM-{$testRunId}-" . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'vendor_id' => $vendor->id,
                'shop_id' => $shop->id,
                'origin_lga_id' => $uyoLga->id,
                'destination_lga_id' => $uyoLga->id,
                'retailer_model_order' => true,
                'order_status' => 'pending',
                'payment_status' => 'pending',
                'sourcing_status' => 'awaiting_payment',
                'fulfillment_type' => 'doorstep_delivery',
                'delivery_fee' => '1500.00',
                'vendor_net_90' => '0.00',
                'customer_cashback_5' => '0.00',
                'platform_margin_5' => '0.00',
                'merchandise_subtotal' => '50000.00',
                'grand_total' => '51500.00',
                'supplier_cost_total_snapshot' => '40000.00',
                'retailer_merchandise_margin' => '10000.00',
                'payment_reference' => "REF-{$testRunId}-" . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => 1,
                'unit_price' => '50000.00',
                'line_total' => '50000.00',
                'supplier_offer_id' => $offer->id,
                'supplier_vendor_id' => $vendor->id,
                'supplier_shop_id' => $shop->id,
                'supplier_cost_snapshot' => '40000.00',
                'package_tier' => 'bike',
            ]);

            OrderSupplierAssignment::create([
                'order_id' => $order->id,
                'supplier_vendor_id' => $vendor->id,
                'supplier_shop_id' => $shop->id,
                'status' => 'awaiting_payment',
            ]);

            $orders[] = $order;
        }

        $this->info("All {$numWorkers} orders created. Launching parallel worker processes...");
        $startTime = microtime(true);

        // 3. Dispatch parallel worker processes
        /** @var Process[] $processes */
        $processes = [];
        foreach ($orders as $order) {
            $cmd = [PHP_BINARY, 'artisan', 'finance:settle-single-order', (string) $order->id];
            $p = new Process($cmd, base_path());
            $p->start();
            $processes[] = $p;
        }

        $this->info("Spawned {$numWorkers} parallel processes. Awaiting completion...");

        // 4. Wait for all processes to complete
        $completed = 0;
        while ($completed < count($processes)) {
            $completed = 0;
            foreach ($processes as $p) {
                if (!$p->isRunning()) {
                    $completed++;
                }
            }
            usleep(20000); // 20ms check
        }

        $duration = microtime(true) - $startTime;
        $this->info(sprintf("All %d processes finished in %.2f seconds.", count($processes), $duration));

        // Debug failed processes
        foreach ($processes as $idx => $p) {
            if ($p->getExitCode() !== 0) {
                $this->warn("Worker #{$idx} exited with code " . $p->getExitCode());
                $this->line("STDOUT: " . trim($p->getOutput()));
                $this->line("STDERR: " . trim($p->getErrorOutput()));
            }
        }

        // 5. Mathematical & Ledger Reconciliation
        $this->info("===============================================================");
        $this->info("RECONCILIATION VERIFICATION");
        $this->info("===============================================================");

        $orderIds = array_map(fn($o) => $o->id, $orders);
        $refreshedOrders = Order::whereIn('id', $orderIds)->get();

        $awaitingConfirmationCount = $refreshedOrders->where('sourcing_status', 'awaiting_supplier_confirmation')->count();
        $stockoutHoldCount = $refreshedOrders->where('sourcing_status', 'stockout_revalidation_hold')->count();
        $paidCount = $refreshedOrders->where('payment_status', 'paid')->count();

        $product->refresh();
        $finalStock = (int) $product->inventory_quantity;

        $journalEntries = FinancialJournalEntry::whereIn('order_id', $orderIds)->get();
        $supplierPayables = $journalEntries->where('account_name', 'supplier_payable')->count();
        $platformMargins = $journalEntries->where('account_name', 'retailer_merchandise_margin')->count();

        $holdAuditLogs = AdminAuditLog::whereIn('target_id', $orderIds)
            ->where('action', 'supplier_stockout_revalidation_hold')
            ->count();
        $paymentAuditLogs = AdminAuditLog::whereIn('target_id', $orderIds)
            ->where('action', 'paystack_payment_verified')
            ->count();

        $this->table(
            ['Metric / Invariant', 'Expected', 'Actual', 'Status'],
            [
                ['Total Competing Orders', $numWorkers, count($refreshedOrders), count($refreshedOrders) === $numWorkers ? 'PASS' : 'FAIL'],
                ['Total Paid Orders', $numWorkers, $paidCount, $paidCount === $numWorkers ? 'PASS' : 'FAIL'],
                ['Successful Allocations', $initialStock, $awaitingConfirmationCount, $awaitingConfirmationCount === $initialStock ? 'PASS' : 'FAIL'],
                ['Stockout Revalidation Holds', $numWorkers - $initialStock, $stockoutHoldCount, $stockoutHoldCount === ($numWorkers - $initialStock) ? 'PASS' : 'FAIL'],
                ['Final Catalog Inventory', 0, $finalStock, $finalStock === 0 ? 'PASS' : 'FAIL'],
                ['Supplier Payable Journal Entries', $initialStock, $supplierPayables, $supplierPayables === $initialStock ? 'PASS' : 'FAIL'],
                ['Platform Margin Journal Entries', $initialStock, $platformMargins, $platformMargins === $initialStock ? 'PASS' : 'FAIL'],
                ['Stockout Hold Audit Trail Logs', $numWorkers - $initialStock, $holdAuditLogs, $holdAuditLogs === ($numWorkers - $initialStock) ? 'PASS' : 'FAIL'],
                ['Payment Verification Audit Logs', $numWorkers, $paymentAuditLogs, $paymentAuditLogs === $numWorkers ? 'PASS' : 'FAIL'],
            ]
        );

        $passed = ($awaitingConfirmationCount === $initialStock)
            && ($stockoutHoldCount === ($numWorkers - $initialStock))
            && ($finalStock === 0)
            && ($supplierPayables === $initialStock)
            && ($platformMargins === $initialStock)
            && ($paidCount === $numWorkers);

        if ($this->option('cleanup')) {
            $this->info("Cleaning up stress test records...");
            FinancialJournalEntry::whereIn('order_id', $orderIds)->delete();
            AdminAuditLog::whereIn('target_id', $orderIds)->delete();
            OrderSupplierAssignment::whereIn('order_id', $orderIds)->delete();
            OrderItem::whereIn('order_id', $orderIds)->delete();
            Order::whereIn('id', $orderIds)->delete();
            SupplierOffer::where('product_id', $product->id)->delete();
            $product->delete();
            $this->info("Cleanup complete.");
        }

        if ($passed) {
            $this->info("REAL CONCURRENCY STRESS TEST PASSED: Zero negative stock, zero double allocations, zero ledger drift.");
            return 0;
        }

        $this->error("REAL CONCURRENCY STRESS TEST FAILED: Ledger or stock invariants violated under concurrency.");
        return 1;
    }
}
