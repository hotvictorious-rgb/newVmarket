<?php

namespace App\Enums;

enum FulfillmentType: string
{
    case DoorstepDelivery = 'doorstep_delivery';
    case InStorePickup = 'in_store_pickup';
    case InShopPickup = 'in_shop_pickup';
}
