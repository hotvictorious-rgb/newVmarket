<?php

namespace App\Enums;

enum ReturnCaseStatus: string
{
    case Requested = 'requested';
    case Pending = 'pending';
    case InTransit = 'in_transit';
    case InTransitBackToVendor = 'in_transit_back_to_vendor';
    case Inspected = 'inspected';
    case ApprovedForReturn = 'approved_for_return';
    case RefundPending = 'refund_pending';
    case RefundDispatchedAwaitingReconciliation = 'refund_dispatched_awaiting_reconciliation';
    case Refunded = 'refunded';
    case Rejected = 'rejected';
    case Closed = 'closed';

    /**
     * Determine if case is eligible for physical hub inspection.
     */
    public function canBeInspected(): bool
    {
        return in_array($this, [
            self::Requested,
            self::Pending,
            self::InTransit,
            self::InTransitBackToVendor,
        ], true);
    }

    /**
     * Determine if case is eligible for financial disbursement.
     */
    public function canDisburseRefund(): bool
    {
        return in_array($this, [
            self::RefundPending,
            self::Inspected,
            self::ApprovedForReturn,
        ], true);
    }
}
