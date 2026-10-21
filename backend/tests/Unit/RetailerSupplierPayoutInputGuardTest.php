<?php

namespace Tests\Unit;

use App\Services\RetailerSupplierPayoutService;
use PHPUnit\Framework\TestCase;

class RetailerSupplierPayoutInputGuardTest extends TestCase
{
    private function service(): RetailerSupplierPayoutService
    {
        return new RetailerSupplierPayoutService();
    }

    public function test_processing_rejects_missing_admin_recipient_or_weak_token_before_database_access(): void
    {
        $missingAdmin = $this->service()->markProcessing(1, 0, 'recipient_123', 'SUPPAYOUT-123456789012');
        $missingRecipient = $this->service()->markProcessing(1, 5, ' ', 'SUPPAYOUT-123456789012');
        $weakToken = $this->service()->markProcessing(1, 5, 'recipient_123', 'short');

        $this->assertFalse($missingAdmin['success']);
        $this->assertFalse($missingRecipient['success']);
        $this->assertFalse($weakToken['success']);
        $this->assertTrue($missingAdmin['protected']);
    }

    public function test_uncertain_transition_requires_a_bounded_reason_and_valid_status_before_database_access(): void
    {
        $emptyReason = $this->service()->markUncertain(1, '  ', 'uncertain');
        $invalidStatus = $this->service()->markUncertain(1, 'Gateway response was not authoritative.', 'unknown status with spaces');

        $this->assertFalse($emptyReason['success']);
        $this->assertFalse($invalidStatus['success']);
        $this->assertTrue($emptyReason['protected']);
    }

    public function test_reversal_rejects_empty_reason_before_database_access(): void
    {
        $result = $this->service()->reverse(1, 'not_submitted', '  ');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['protected']);
    }

    public function test_reversal_rejects_unsupported_gateway_outcome_before_database_access(): void
    {
        $result = $this->service()->reverse(1, 'timeout_unknown', 'Transfer outcome requires reconciliation.');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['protected']);
    }
}
