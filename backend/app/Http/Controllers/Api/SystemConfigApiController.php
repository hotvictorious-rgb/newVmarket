<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Models\Shop;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SystemConfigApiController extends Controller
{
    /**
     * Return remote system configuration and mobile control flags.
     * Cached with 3600s TTL; auto-invalidated on setting mutation.
     */
    public function show(): JsonResponse
    {
        $config = Cache::remember('vmarket_api_remote_config_v1', 3600, function () {
            // Dynamically resolve active corridors (LGAs with registered active shops or default corridors)
            $shopLgaIds = Shop::where('is_active', true)->pluck('lga_id')->filter()->unique()->toArray();
            $corridorIds = !empty($shopLgaIds) ? $shopLgaIds : [69, 70, 71, 72];

            $activeCorridors = Lga::whereIn('id', $corridorIds)
                ->select('id', 'name', 'state_id')
                ->orderBy('name')
                ->get();

            return [
                'app_name'            => SystemSetting::get('app_name', config('app.name', 'Victorious MARKET')),
                'app_slogan'          => SystemSetting::get('app_slogan', 'Akwa Ibom Sovereign Marketplace'),
                'support_phone'       => SystemSetting::get('support_phone', env('SUPPORT_PHONE', '09118949035')),
                'support_email'       => SystemSetting::get('support_email', env('SUPPORT_EMAIL', 'support@victoriousmarket.com.ng')),
                'call_to_order_phone' => SystemSetting::get('call_to_order_phone', env('CALL_TO_ORDER_PHONE', '+2349118949035')),
                'currency_symbol'     => SystemSetting::get('currency_symbol', '₦'),
                'currency_code'       => SystemSetting::get('currency_code', 'NGN'),
                'cashback_percentage' => (float) SystemSetting::get('customer_cashback_percent', 5.0),
                'active_state'        => [
                    'id'   => 1,
                    'name' => 'Akwa Ibom',
                ],
                'active_corridors'    => $activeCorridors,
                'version_control'     => [
                    'min_version'    => SystemSetting::get('mobile_min_version', '1.0.0'),
                    'latest_version' => SystemSetting::get('mobile_latest_version', '1.0.0'),
                    'force_update'   => (bool) SystemSetting::get('mobile_force_update', false),
                    'update_url'     => SystemSetting::get('mobile_update_url', 'https://shop.victoriousmarket.com.ng/download'),
                ],
                'maintenance_mode'    => [
                    'is_active' => (bool) SystemSetting::get('mobile_maintenance_mode', false),
                    'message'   => SystemSetting::get('mobile_maintenance_message', null),
                ],
                'features'            => [
                    'in_shop_pickup'    => true,
                    'doorstep_delivery' => true,
                    'escrow_protection' => true,
                    'dual_otp_handshake'=> true,
                ],
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => $config,
        ]);
    }
}
