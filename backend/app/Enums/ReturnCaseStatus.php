<?php

namespace App\Enums;

enum ReturnCaseStatus: string
{
    case Requested = 'requested';
    case UnderReview = 'under_review';
    case Pending = 'pending';
    case ApprovedForReturn = 'approved_for_return';
    case CollectionFailed = 'collection_failed';
    case InTransit = 'in_transit';
    case InTransitBackToVendor = 'in_transit_back_to_vendor';
    case ReceivedAtHub = 'received_at_hub';
    case Inspected = 'inspected';
    case SupplierHandoverFailed = 'supplier_handover_failed';
    case ReturnedToVendor = 'returned_to_vendor';
    case DisputedByVendor = 'disputed_by_vendor';
    case RefundPending = 'refund_pending';
    case RefundDispatchedAwaitingReconciliation = 'refund_dispatched_awaiting_reconciliation';
    case Refunded = 'refunded';
    case ReplacementCompleted = 'replacement_completed';
    case Rejected = 'rejected';
    case Closed = 'closed';

    /**
     * Determine if case is eligible for physical hub inspection.
     */
    public function canBeInspected(): bool
    {
        return in_array($this, [
            self::Requested,
            self::UnderReview,
            self::Pending,
            self::ApprovedForReturn,
            self::InTransit,
            self::InTransitBackToVendor,
            self::ReceivedAtHub,
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
            self::ReturnedToVendor,
        ], true);
    }
}
