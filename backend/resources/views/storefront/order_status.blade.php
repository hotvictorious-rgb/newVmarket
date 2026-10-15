@extends('layouts.app')

@section('title', 'Order Status — ' . $order->order_number)

@section('content')
<div class="vm-container" style="padding: 40px 16px 80px; max-width: 720px;">
    
    <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 16px; overflow: hidden; box-shadow: var(--vm-shadow-sm); padding: 30px;">
        
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 24px; border-bottom: 1px solid #F1F5F9; padding-bottom: 16px;">
            <div>
                <span style="font-size: 12px; color: #64748B; font-weight: 600; display: block;">ORDER TRACKING:</span>
                <h1 style="font-size: 22px; font-weight: 900; color: #0F172A; margin: 0; font-family: monospace;">
                    {{ $order->order_number }}
                </h1>
            </div>
            <span style="background: #ECFDF5; color: #059669; border: 1px solid rgba(5, 150, 105, 0.25); font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 999px;">
                ● PAID VIA ESCROW
            </span>
        </div>

        <!-- SECRET 6-DIGIT DOORSTEP DELIVERY OTP BANNER -->
        <div style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border: 2px solid var(--vm-gold); border-radius: 14px; padding: 22px; text-align: center; margin-bottom: 28px; color: #FFF;">
            <div style="font-size: 12px; font-weight: 700; color: var(--vm-gold); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">
                YOUR SECRET 6-DIGIT DOORSTEP DELIVERY OTP
            </div>
            <div style="font-family: monospace; font-size: 34px; font-weight: 900; color: var(--vm-gold); letter-spacing: 6px; margin-bottom: 8px;">
                {{ $order->verification_code }}
            </div>
            <p style="font-size: 12px; color: #CBD5E1; margin: 0; line-height: 1.5;">
                ⚠️ <strong>Security Rule:</strong> Only give this code to the delivery rider <em>after</em> you have physically inspected your goods at your doorstep. Once submitted, the 24-hour return window begins.
            </p>
        </div>

        <!-- 4-Step Progress Stepper -->
        <div style="margin-bottom: 28px;">
            <div style="display: flex; justify-content: space-between; position: relative;">
                <div style="text-align: center; flex: 1;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #059669; color: #FFF; display: flex; align-items: center; justify-content: center; margin: 0 auto 6px; font-size: 13px; font-weight: 700;">✓</div>
                    <span style="font-size: 11.5px; font-weight: 700; color: #0F172A;">Order Placed</span>
                </div>
                <div style="text-align: center; flex: 1;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: {{ in_array($order->order_status, ['processing', 'out_for_delivery', 'delivered']) ? '#059669' : '#5E17EB' }}; color: #FFF; display: flex; align-items: center; justify-content: center; margin: 0 auto 6px; font-size: 13px; font-weight: 700;">
                        {{ in_array($order->order_status, ['processing', 'out_for_delivery', 'delivered']) ? '✓' : '2' }}
                    </div>
                    <span style="font-size: 11.5px; font-weight: 700; color: #0F172A;">Counter Pickup</span>
                </div>
                <div style="text-align: center; flex: 1;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: {{ in_array($order->order_status, ['out_for_delivery', 'delivered']) ? '#059669' : '#E2E8F0' }}; color: {{ in_array($order->order_status, ['out_for_delivery', 'delivered']) ? '#FFF' : '#64748B' }}; display: flex; align-items: center; justify-content: center; margin: 0 auto 6px; font-size: 13px; font-weight: 700;">
                        {{ in_array($order->order_status, ['delivered']) ? '✓' : '3' }}
                    </div>
                    <span style="font-size: 11.5px; font-weight: 700; color: #0F172A;">In Transit</span>
                </div>
                <div style="text-align: center; flex: 1;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: {{ $order->order_status === 'delivered' ? '#059669' : '#E2E8F0' }}; color: {{ $order->order_status === 'delivered' ? '#FFF' : '#64748B' }}; display: flex; align-items: center; justify-content: center; margin: 0 auto 6px; font-size: 13px; font-weight: 700;">
                        {{ $order->order_status === 'delivered' ? '✓' : '4' }}
                    </div>
                    <span style="font-size: 11.5px; font-weight: 700; color: #0F172A;">Delivered</span>
                </div>
            </div>
        </div>

        <!-- Rider & Shop Details -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 24px;">
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 14px;">
                <span style="font-size: 11.5px; color: #64748B; font-weight: 600; display: block; margin-bottom: 4px;">MERCHANT SHOP:</span>
                <strong style="font-size: 13.5px; color: #0F172A; display: block;">{{ $order->shop->name }}</strong>
                <span style="font-size: 12px; color: #64748B;">{{ $order->shop->address }}</span>
            </div>
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 14px;">
                <span style="font-size: 11.5px; color: #64748B; font-weight: 600; display: block; margin-bottom: 4px;">DISPATCH COURIER:</span>
                <strong style="font-size: 13.5px; color: #0F172A; display: block;">{{ $order->rider->user->name ?? 'Edidiong Udoh (Rider Alpha)' }}</strong>
                <span style="font-size: 12px; color: #64748B;">Akwa Express Logistics</span>
            </div>
        </div>

        <!-- 90/5/5 Mathematical Financial Breakdown -->
        <div style="background: #FFF; border: 1px solid var(--vm-border); border-radius: 10px; padding: 16px; font-size: 13px; margin-bottom: 24px;">
            <div style="font-weight: 700; color: #0F172A; margin-bottom: 10px;">Platform Sovereign Mathematical Split (Δ = ₦0.00):</div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; color: #64748B;">
                <span>Total Paid by You:</span>
                <strong style="color: #0F172A;">&#8358;{{ number_format((float)$order->grand_total, 2) }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; color: #64748B;">
                <span>Merchant Net (90% held in escrow):</span>
                <span>&#8358;{{ number_format((float)$order->vendor_net_90, 2) }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; color: #059669;">
                <span>Your 5% Cashback Reward (Matures in 24h):</span>
                <strong>+&#8358;{{ number_format((float)$order->customer_cashback_5, 2) }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; color: #64748B;">
                <span>Delivery Fee (Segregated to Rider):</span>
                <span>&#8358;{{ number_format((float)$order->delivery_fee, 2) }}</span>
            </div>
        </div>

        <div style="text-align: center;">
            <a href="{{ route('storefront') }}" style="display: inline-block; background: var(--vm-primary); color: #FFF; text-decoration: none; font-weight: 700; font-size: 13.5px; padding: 12px 28px; border-radius: 8px;">
                &larr; Return to Shopping
            </a>
        </div>
    </div>
</div>
@endsection