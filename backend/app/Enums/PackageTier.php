<?php

namespace App\Enums;

enum PackageTier: string
{
    case Bike = 'bike';
    case Keke = 'keke';
    case Fragile = 'fragile';
    case Bulky = 'bulky';

    /**
     * Determine if tier requires keke/cargo vehicle dispatch.
     */
    public function requiresCargoDispatch(): bool
    {
        return in_array($this, [self::Keke, self::Fragile, self::Bulky], true);
    }
}
