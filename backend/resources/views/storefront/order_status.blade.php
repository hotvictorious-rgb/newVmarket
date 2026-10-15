@extends('layouts.app')

@section('title', 'Order Status & Handshake Pass — ' . $order->order_number)

@section('content')
<div class="vm-container" style="padding: 30px 16px 80px; max-width: 760px;">
    
    <!-- ========================================== -->
    <!-- 1. ORDER CONFIRMATION BANNER (SCREEN 13) -->
    <!-- ========================================== -->
    <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 16px; padding: 28px 24px; text-align: center; margin-bottom: 24px; box-shadow: var(--vm-shadow-xs);">
        
        <!-- Green Animated Checkmark Icon -->
        <div style="width: 64px; height: 64px; border-radius: 50%; background: #ECFDF5; color: #10B981; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 32px; border: 2px solid rgba(16, 185, 129, 0.25);">
            ✓
        </div>

        <h1 style="font-size: 24px; font-weight: 800; color: #0F172A; margin: 0 0 6px;">
            Order Placed Successfully!
        </h1>
        <p style="font-size: 13.5px; color: #64748B; margin: 0 0 16px;">
            Your payment is safely secured in Victorious MARKET Escrow.
        </p>

        <!-- Order Number & Total Pill -->
        <div style="display: inline-flex; align-items: center; gap: 16px; background: #F8FAFC; border: 1px solid #E2E8F0; padding: 10px 20px; border-radius: 12px; margin-bottom: 8px;">
            <div>
                <span style="font-size: 11px; color: #64748B; font-weight: 700; display: block; text-transform: uppercase;">ORDER NUMBER:</span>
                <strong style="font-family: monospace; font-size: 16px; color: #0F172A;">{{ $order->order_number }}</strong>
            </div>
            <div style="width: 1px; height: 28px; background: #CBD5E1;"></div>
            <div>
                <span style="font-size: 11px; color: #64748B; font-weight: 700; display: block; text-transform: uppercase;">TOTAL PAID:</span>
                <strong style="font-size: 16px; color: #5E17EB;">&#8358;{{ number_format((float)$order->grand_total, 2) }}</strong>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 2. HANDSHAKE OTP / PICKUP CODE CARD (SCREEN 16) -->
    <!-- ========================================== -->
    <div style="background: linear-gradient(135deg, #0F172A 0%, #1E1B4B 100%); border: 2px solid #FFD700; border-radius: 16px; padding: 26px; margin-bottom: 24px; color: #FFFFFF; text-align: center; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);">
        
        <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 215, 0, 0.15); border: 1px solid rgba(255, 215, 0, 0.35); padding: 4px 12px; border-radius: 999px; font-size: 11.5px; font-weight: 800; color: #FFD700; margin-bottom: 12px;">
            <span>🔑</span>
            <span>YOUR SECRET HANDSHAKE VERIFICATION CODE</span>
        </div>

        <div style="margin-bottom: 12px;">
            <span id="vmOtpCodeText" style="font-family: monospace; font-size: 38px; font-weight: 900; color: #FFD700; letter-spacing: 8px; display: block;">
                {{ $order->fulfillment_type === 'in_shop_pickup' ? $order->pickup_verification_code : $order->verification_code }}
            </span>
        </div>

        <p style="font-size: 13px; color: #E2E8F0; max-width: 480px; margin: 0 auto 18px; line-height: 1.5;">
            @if($order->fulfillment_type === 'in_shop_pickup')
                Show this code to the cashier at <strong>{{ $order->shop->name }}</strong> after physically inspecting your items.
            @else
                Give this code to the delivery rider <em>only after</em> you have physically received and inspected your order at your doorstep.
            @endif
        </p>

        <!-- Quick 1-Click Actions -->
        <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
            <button type="button" onclick="navigator.clipboard.writeText('{{ $order->fulfillment_type === 'in_shop_pickup' ? $order->pickup_verification_code : $order->verification_code }}'); alert('OTP copied to clipboard: {{ $order->fulfillment_type === 'in_shop_pickup' ? $order->pickup_verification_code : $order->verification_code }}');" style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.25); color: #FFFFFF; font-weight: 700; font-size: 12.5px; padding: 9px 18px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                <span>📋 Copy Code</span>
            </button>
            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $order->shop->phone ?? '09118949035') }}?text=Hello%20{{ urlencode($order->shop->name) }},%20I%20have%20placed%20Order%20{{ $order->order_number }}" target="_blank" style="background: #25D366; color: #FFFFFF; font-weight: 700; font-size: 12.5px; padding: 9px 18px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <span>💬 WhatsApp Merchant</span>
            </a>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 3. VERTICAL 5-STEP TRACKING TIMELINE (SCREEN 14) -->
    <!-- ========================================== -->
    <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 16px; padding: 26px; margin-bottom: 24px; box-shadow: var(--vm-shadow-xs);">
        <h2 style="font-size: 16px; font-weight: 800; color: #0F172A; margin: 0 0 20px; display: flex; align-items: center; gap: 8px;">
            <span>📍</span> Live Delivery Timeline
        </h2>

        @php
            $status = $order->order_status;
            $steps = [
                ['key' => 'placed', 'title' => 'Order Placed', 'desc' => 'Order created in system', 'done' => true, 'time' => $order->created_at->format('h:i A')],
                ['key' => 'paid', 'title' => 'Payment Confirmed', 'desc' => 'Escrow locked via Paystack', 'done' => true, 'time' => $order->created_at->addMinutes(2)->format('h:i A')],
                ['key' => 'preparing', 'title' => 'Merchant Preparing', 'desc' => "Verified at {$order->shop->name}", 'done' => in_array($status, ['processing', 'out_for_delivery', 'delivered']), 'time' => '11:15 AM'],
                ['key' => 'dispatch', 'title' => $order->fulfillment_type === 'in_shop_pickup' ? 'Ready for Counter Pickup' : 'Out for Delivery', 'desc' => $order->fulfillment_type === 'in_shop_pickup' ? 'Waiting at branch counter' : 'Courier in transit', 'done' => in_array($status, ['out_for_delivery', 'delivered']), 'time' => '12:30 PM (ETA)'],
                ['key' => 'delivered', 'title' => 'Delivered & Received', 'desc' => 'OTP handshake confirmed', 'done' => ($status === 'delivered'), 'time' => 'Pending Confirmation'],
            ];
        @endphp

        <div style="display: flex; flex-direction: column; gap: 0;">
            @foreach($steps as $idx => $step)
                <div style="display: flex; gap: 16px; position: relative; padding-bottom: {{ $loop->last ? '0' : '24px' }};">
                    
                    @if(!$loop->last)
                        <div style="position: absolute; left: 15px; top: 32px; bottom: 0; width: 2px; background: {{ $step['done'] ? '#10B981' : '#E2E8F0' }};"></div>
                    @endif

                    <!-- Step Circle Icon -->
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: {{ $step['done'] ? '#10B981' : '#F1F5F9' }}; color: {{ $step['done'] ? '#FFFFFF' : '#94A3B8' }}; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 800; flex-shrink: 0; z-index: 2; border: 2px solid {{ $step['done'] ? '#10B981' : '#CBD5E1' }};">
                        {{ $step['done'] ? '✓' : ($idx + 1) }}
                    </div>

                    <!-- Step Content -->
                    <div style="flex-grow: 1; min-width: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: baseline;">
                            <strong style="font-size: 14px; color: {{ $step['done'] ? '#0F172A' : '#64748B' }};">
                                {{ $step['title'] }}
                            </strong>
                            <span style="font-size: 11.5px; color: #94A3B8;">{{ $step['time'] }}</span>
                        </div>
                        <span style="font-size: 12.5px; color: #64748B; display: block; margin-top: 2px;">
                            {{ $step['desc'] }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- ========================================== -->
    <!-- 4. ORDER DETAILS & FINANCIAL SPLIT (SCREEN 15) -->
    <!-- ========================================== -->
    <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 16px; padding: 26px; margin-bottom: 24px; box-shadow: var(--vm-shadow-xs);">
        <h2 style="font-size: 16px; font-weight: 800; color: #0F172A; margin: 0 0 16px;">
            Order Items &amp; Settlement Details
        </h2>

        <!-- Items Listing -->
        <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 18px;">
            @foreach($order->items as $item)
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px;">
                    <div>
                        <strong style="font-size: 13.5px; color: #0F172A; display: block;">{{ $item->product_name }}</strong>
                        <span style="font-size: 12px; color: #64748B;">Qty: {{ $item->quantity }} &bull; Unit: &#8358;{{ number_format((float)$item->unit_price, 2) }}</span>
                    </div>
                    <strong style="font-size: 14px; color: #5E17EB;">
                        &#8358;{{ number_format((float)$item->line_total, 2) }}
                    </strong>
                </div>
            @endforeach
        </div>

        <!-- Financial Breakdown -->
        <div style="border-top: 1px solid #F1F5F9; padding-top: 14px; font-size: 13px; display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; justify-content: space-between; color: #64748B;">
                <span>Merchandise Subtotal:</span>
                <strong style="color: #0F172A;">&#8358;{{ number_format((float)$order->merchandise_subtotal, 2) }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; color: #64748B;">
                <span>Delivery Fee:</span>
                <strong style="color: #0F172A;">&#8358;{{ number_format((float)$order->delivery_fee, 2) }}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; color: #059669; font-weight: 700; background: #ECFDF5; padding: 6px 10px; border-radius: 6px;">
                <span>🪙 5% Cashback Reward (Credited to Wallet):</span>
                <span>+&#8358;{{ number_format((float)$order->customer_cashback_5, 2) }}</span>
            </div>
            <div style="border-top: 2px dashed #E2E8F0; padding-top: 10px; margin-top: 4px; display: flex; justify-content: space-between; font-size: 16px; font-weight: 900; color: #5E17EB;">
                <span>Total Amount Paid:</span>
                <span>&#8358;{{ number_format((float)$order->grand_total, 2) }}</span>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation Actions -->
    <div style="display: flex; gap: 12px; justify-content: center;">
        <a href="{{ route('storefront') }}" style="background: #FFFFFF; border: 1.5px solid #CBD5E1; color: #0F172A; text-decoration: none; font-weight: 700; font-size: 13.5px; padding: 12px 22px; border-radius: 10px; transition: all 0.15s ease;">
            &larr; Continue Shopping
        </a>
        <a href="{{ route('customer.dashboard') }}" style="background: #5E17EB; color: #FFFFFF; text-decoration: none; font-weight: 700; font-size: 13.5px; padding: 12px 22px; border-radius: 10px; box-shadow: 0 4px 12px rgba(94, 23, 235, 0.3);">
            View My Orders &amp; Wallet &rarr;
        </a>
    </div>

</div>
@endsection