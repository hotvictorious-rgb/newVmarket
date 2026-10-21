<?php

use App\Models\OrderItem;
use App\Models\ReturnItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('financial_journal_entries') || !Schema::hasTable('order_items')) {
            return;
        }

        if (!Schema::hasColumn('financial_journal_entries', 'order_item_id')) {
            Schema::table('financial_journal_entries', function (Blueprint $table) {
                $table->foreignId('order_item_id')->nullable()->after('order_id')
                    ->constrained('order_items')->nullOnDelete();
                $table->index(['order_item_id', 'account_name'], 'fje_order_item_account_index');
            });
        }

        // Best-effort backfill of historical item-level supplier entries. Any row
        // that cannot be unambiguously linked remains null and is reported by audit.
        DB::table('financial_journal_entries')
            ->where('account_name', 'supplier_payable')
            ->whereNull('order_item_id')
            ->orderBy('id')
            ->chunkById(200, function ($entries): void {
                foreach ($entries as $entry) {
                    $itemId = null;
                    $eventId = (string) ($entry->event_id ?? '');
                    $description = (string) ($entry->description ?? '');

                    if (preg_match('/-ITEM-(\d+)-/', $eventId, $matches) === 1) {
                        $itemId = (int) $matches[1];
                    } elseif (preg_match('/item\s+#(\d+)/i', $description, $matches) === 1) {
                        $itemId = (int) $matches[1];
                    } elseif (preg_match('/^EVT-RETAILER-REFUND-\d+-SUPPLIER-PAYABLE-(\d+)$/', $eventId, $matches) === 1) {
                        $itemId = (int) ReturnItem::whereKey((int) $matches[1])->value('order_item_id');
                    }

                    if (!$itemId || !OrderItem::whereKey($itemId)->where('order_id', $entry->order_id)->exists()) {
                        continue;
                    }

                    DB::table('financial_journal_entries')
                        ->where('id', $entry->id)
                        ->whereNull('order_item_id')
                        ->update(['order_item_id' => $itemId]);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('financial_journal_entries') || !Schema::hasColumn('financial_journal_entries', 'order_item_id')) {
            return;
        }

        Schema::table('financial_journal_entries', function (Blueprint $table) {
            $table->dropForeign(['order_item_id']);
            $table->dropIndex('fje_order_item_account_index');
            $table->dropColumn('order_item_id');
        });
    }
};
