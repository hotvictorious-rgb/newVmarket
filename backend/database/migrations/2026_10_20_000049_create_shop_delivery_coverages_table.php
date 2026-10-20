<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shop_delivery_coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('state_id')->constrained('states')->cascadeOnDelete();
            $table->foreignId('lga_id')->nullable()->constrained('lgas')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Compound index for lightning-fast lookup during checkout
            $table->index(['shop_id', 'is_active', 'lga_id'], 'idx_shop_delivery_coverage_lookup');
            $table->index(['shop_id', 'is_active', 'state_id'], 'idx_shop_delivery_state_lookup');
        });

        // Backward compatibility seed: ensure every existing physical shop starts
        // with delivery coverage for its own physical origin LGA so existing shops
        // can deliver locally until the vendor customizes their coverage.
        $shops = DB::table('shops')->select('id', 'state_id', 'lga_id')->get();
        $now = now();
        $initialCoverages = [];

        foreach ($shops as $shop) {
            if ($shop->state_id && $shop->lga_id) {
                $initialCoverages[] = [
                    'shop_id' => $shop->id,
                    'state_id' => $shop->state_id,
                    'lga_id' => $shop->lga_id,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($initialCoverages)) {
            DB::table('shop_delivery_coverages')->insert($initialCoverages);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_delivery_coverages');
    }
};
