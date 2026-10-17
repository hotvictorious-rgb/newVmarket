<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Disputed = 'disputed';
    case Returned = 'returned';

    /**
     * Determine if order is in terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled, self::Returned], true);
    }

    /**
     * Determine if order is active / in-flight.
     */
    public function isInFlight(): bool
    {
        return in_array($this, [self::Confirmed, self::Processing, self::Shipped], true);
    }
}
