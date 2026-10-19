<?php

namespace App\Enums;

enum OrderStatus: string
{
    // ==========================================
    // 1. DOORSTEP DELIVERY LIFECYCLE
    // ==========================================
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case ReadyForDispatch = 'ready_for_dispatch';
    case ReadyForPickup = 'ready_for_pickup'; // Legacy/staged courier dispatch alias
    case Shipped = 'shipped';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case DeliveryAttemptFailed = 'delivery_attempt_failed';
    case DoorstepRejected = 'doorstep_rejected';
    case ReturnedToVendor = 'returned_to_vendor';
    case Disputed = 'disputed';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
    case PaidStockUnavailable = 'paid_stock_unavailable';

    // ==========================================
    // 2. IN-SHOP PICKUP LIFECYCLE (NEVER MERGES WITH DELIVERY)
    // ==========================================
    case PickupReserved = 'pickup_reserved';
    case ReadyForCounterPickup = 'ready_for_counter_pickup';
    case CounterInspecting = 'counter_inspecting';
    case PickedUp = 'picked_up'; // Authoritative terminal completion for in-shop pickup
    case CounterRejected = 'counter_rejected';
    case PickupExpired = 'pickup_expired';
    case PickupCancelled = 'pickup_cancelled';

    /**
     * Determine if status belongs exclusively to in-shop pickup lifecycle.
     */
    public function isPickupStatus(): bool
    {
        return in_array($this, [
            self::PickupReserved,
            self::ReadyForCounterPickup,
            self::CounterInspecting,
            self::PickedUp,
            self::CounterRejected,
            self::PickupExpired,
            self::PickupCancelled,
        ], true);
    }

    /**
     * Determine if status belongs to doorstep delivery lifecycle.
     */
    public function isDeliveryStatus(): bool
    {
        return in_array($this, [
            self::Pending,
            self::Confirmed,
            self::Processing,
            self::ReadyForDispatch,
            self::ReadyForPickup,
            self::Shipped,
            self::OutForDelivery,
            self::Delivered,
            self::DeliveryAttemptFailed,
            self::DoorstepRejected,
            self::ReturnedToVendor,
            self::Disputed,
            self::Returned,
            self::Cancelled,
            self::PaidStockUnavailable,
        ], true);
    }

    /**
     * Determine if order is in terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Delivered,
            self::PickedUp,
            self::Cancelled,
            self::PickupCancelled,
            self::PickupExpired,
            self::Returned,
            self::ReturnedToVendor,
            self::CounterRejected,
        ], true);
    }

    /**
     * Determine if order is active / in-flight.
     */
    public function isInFlight(): bool
    {
        return in_array($this, [
            self::Confirmed,
            self::Processing,
            self::ReadyForDispatch,
            self::ReadyForPickup,
            self::Shipped,
            self::OutForDelivery,
            self::PickupReserved,
            self::ReadyForCounterPickup,
            self::CounterInspecting,
        ], true);
    }
}
