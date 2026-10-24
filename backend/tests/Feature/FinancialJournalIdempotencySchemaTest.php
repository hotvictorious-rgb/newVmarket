<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinancialJournalIdempotencySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_generates_stable_idempotency_key_for_journal_legs(): void
    {
        $base = [
            'order_id' => null,
            'transaction_reference' => 'JRN-MODEL-REF-A',
            'event_id' => 'JRN-MODEL-EVENT-1',
            'beneficiary_type' => null,
            'beneficiary_id' => null,
            'account_name' => 'cash_in_escrow',
            'entry_type' => 'debit',
            'amount' => '100.00',
            'currency' => 'NGN',
            'description' => 'Model generated idempotency key',
        ];

        $entry = \App\Models\FinancialJournalEntry::create($base);
        $this->assertNotEmpty($entry->idempotency_key);
        $this->assertStringStartsWith('JRN-', $entry->idempotency_key);

        // A retry that changes descriptive/reference fields but reuses the same
        // business event and ledger leg must collide at the database boundary.
        $retry = $base;
        $retry['transaction_reference'] = 'JRN-MODEL-REF-B';
        $retry['description'] = 'Retry with changed reference/description';

        try {
            \App\Models\FinancialJournalEntry::create($retry);
            $this->fail('A repeated event/ledger leg must not create a second journal row.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
    }

    public function test_financial_journal_idempotency_key_is_unique_at_database_level(): void
    {
        $this->assertTrue(
            Schema::hasColumn('financial_journal_entries', 'idempotency_key'),
            'Financial journal schema must persist idempotency keys used by ledger writers.'
        );

        $this->assertTrue(
            Schema::hasColumn('financial_journal_entries', 'order_item_id'),
            'Supplier payable journal rows must retain their order-item identity.'
        );

        $base = [
            'order_id' => null,
            'transaction_reference' => 'SUPREC-SCHEMA-TEST-1',
            'event_id' => 'EVT-SUPREC-SCHEMA-TEST-1',
            'beneficiary_type' => null,
            'beneficiary_id' => null,
            'account_name' => 'retailer_sourcing_variance',
            'entry_type' => 'debit',
            'amount' => '1.00',
            'currency' => 'NGN',
            'description' => 'Schema idempotency regression test',
            'idempotency_key' => 'SUPREC-schema-idempotency-test',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('financial_journal_entries')->insert($base);

        $duplicate = $base;
        $duplicate['transaction_reference'] = 'SUPREC-SCHEMA-TEST-2';
        $duplicate['event_id'] = 'EVT-SUPREC-SCHEMA-TEST-2';

        try {
            DB::table('financial_journal_entries')->insert($duplicate);
            $this->fail('Duplicate financial journal idempotency keys must be rejected by the database.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
    }
}
