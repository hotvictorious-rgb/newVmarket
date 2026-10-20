@extends('layouts.app')

@section('title', 'Order Status & Handshake Pass — ' . $order->order_number)

@section('content')
<div class="vm-container" style="padding: 30px 16px 80px; max-width: 760px;">

    <!-- Success & Error Flash Messages -->
    @if(session('success'))
        <div style="background: #ECFDF5; border: 1.5px solid #10B981; padding: 16px 20px; border-radius: 14px; margin-bottom: 24px; color: #065F46; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.15);">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <div>{{ session('success') }}</div>
        </div>
    @endif
    @if(session('error'))
        <div style="background: #FEF2F2; border: 1.5px solid #EF4444; padding: 16px 20px; border-radius: 14px; margin-bottom: 24px; color: #991B1B; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 12px;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
            <div>{{ session('error') }}</div>
        </div>
    @endif
    @if($errors->any())
        <div style="background: #FEF2F2; border: 1.5px solid #EF4444; padding: 16px 20px; border-radius: 14px; margin-bottom: 24px; color: #991B1B; font-size: 13.5px; font-weight: 600;">
            <strong style="display: block; margin-bottom: 6px; font-size: 14px;">Please review the following:</strong>
            <ul style="margin: 0; padding-left: 20px; line-height: 1.6;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    
    <!-- ========================================== -->
    <!-- 1. ORDER CONFIRMATION BANNER (SCREEN 13) -->
    <!-- ========================================== -->
    <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 16px; padding: 28px 24px; text-align: center; margin-bottom: 24px; box-shadow: var(--vm-shadow-xs);">
        
        @if($order->order_status === 'delivered')
            <!-- Green Delivered Checkmark Icon -->
            <div style="width: 68px; height: 68px; border-radius: 50%; background: #ECFDF5; color: #10B981; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; border: 2.5px solid #10B981; box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.15);">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0F172A; margin: 0 0 6px;">
                Package Delivered &amp; Confirmed!
            </h1>
            <p style="font-size: 13.5px; color: #059669; font-weight: 600; margin: 0 0 16px;">
                Receipt confirmed on your phone. Your 24-hour buyer protection window is now active.
            </p>
        @else
            <!-- Blue/Purple Placed Icon -->
            <div style="width: 64px; height: 64px; border-radius: 50%; background: #EEF2FF; color: #4F46E5; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; border: 2px solid rgba(79, 70, 229, 0.25);">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line>
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </div>
            <h1 style="font-size: 24px; font-weight: 800; color: #0F172A; margin: 0 0 6px;">
                Order Placed Successfully!
            </h1>
            <p style="font-size: 13.5px; color: #64748B; margin: 0 0 16px;">
                Your payment is safely secured in Victorious MARKET Escrow.
            </p>
        @endif

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

    @if($order->collectionPoint)
        <!-- ========================================== -->
        <!-- DESIGNATED LGA COLLECTION POINT CARD       -->
        <!-- ========================================== -->
        <div style="background: linear-gradient(135deg, #1E1B4B 0%, #312E81 100%); border: 2px solid #6366F1; border-radius: 16px; padding: 22px; margin-bottom: 24px; color: #FFFFFF; box-shadow: 0 4px 14px rgba(99, 102, 241, 0.2);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#818CF8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <strong style="font-size: 16px; color: #FFFFFF;">Designated LGA Collection Point</strong>
                </div>
                <span class="dash-badge dash-badge-purple" style="font-size: 11px; padding: 4px 10px; font-weight: 800; font-family: monospace;">
                    {{ $order->collectionPoint->code }}
                </span>
            </div>
            <div style="font-size: 15px; font-weight: 800; color: #E0E7FF; margin-bottom: 4px;">
                {{ $order->collectionPoint->name }}
            </div>
            <div style="font-size: 13px; color: #C7D2FE; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                <span>{{ $order->collectionPoint->address }}</span>
                @if($order->collectionPoint->landmark)
                    <span style="opacity: 0.6;">&bull;</span>
                    <span>Landmark: {{ $order->collectionPoint->landmark }}</span>
                @endif
            </div>
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap; font-size: 12px; color: #A5B4FC; background: rgba(0,0,0,0.25); padding: 10px 14px; border-radius: 8px;">
                <div style="display: flex; align-items: center; gap: 5px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    <span>Phone: <strong style="color: #FFF;">{{ $order->collectionPoint->contact_phone }}</strong></span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span>Hours: <strong style="color: #FFF;">{{ $order->collectionPoint->operating_hours }}</strong></span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    <span>Logistics: <strong style="color: #FFF;">{{ $order->collectionPoint->logisticsCompany->company_name ?? 'Accredited Partner' }}</strong></span>
                </div>
            </div>
            <div style="margin-top: 12px; font-size: 12px; color: #D1FAE5; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); padding: 9px 12px; border-radius: 8px; display: flex; align-items: center; gap: 8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <polyline points="9 12 11 14 15 10"/>
                </svg>
                <span><strong>Collection Point Protocol:</strong> When transit delivers your package to this station, visit with your secret 6-digit PIN below to inspect and collect your package.</span>
            </div>
        </div>
    @endif

    @if($order->delivery_attempt_photo_path)
        <!-- ATTEMPT FAILURE PROOF CARD -->
        <div style="background: #FFFBEB; border: 2px solid #F59E0B; border-radius: 16px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(245, 158, 11, 0.15);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <strong style="font-size: 15px; color: #92400E;">Courier Delivery Attempt Logged</strong>
                </div>
                <span style="background: #FEF3C7; color: #B45309; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 999px; border: 1px solid #FDE68A;">
                    ATTEMPT #{{ $order->delivery_attempt_count ?? 1 }} &bull; {{ $order->last_delivery_attempt_at ? $order->last_delivery_attempt_at->format('h:i A') : 'Today' }}
                </span>
            </div>
            <p style="font-size: 13px; color: #78350F; margin: 0 0 12px; line-height: 1.5;">
                Our dispatch courier arrived at your address but could not complete handover (e.g., gate locked or recipient unreachable).
            </p>
            <div style="background: #FFFFFF; border: 1px solid #FDE68A; border-radius: 10px; padding: 12px; display: flex; align-items: center; gap: 14px;">
                <img src="{{ asset($order->delivery_attempt_photo_path) }}" alt="Attempt Proof" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid #FCD34D;">
                <div>
                    <span style="font-size: 12px; font-weight: 700; color: #92400E; display: flex; align-items: center; gap: 5px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                        <span>Premises Arrival Photo Proof</span>
                    </span>
                    <span style="font-size: 11.5px; color: #78350F; display: block; margin-top: 2px;">Live photo captured on courier device at doorstep arrival.</span>
                    <div style="margin-top: 6px;">
                        <a href="{{ asset($order->delivery_attempt_photo_path) }}" target="_blank" style="font-size: 11px; font-weight: 700; color: #B45309; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                            <span>View Full Photo</span>
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- ========================================================================= -->
    <!-- 2. TAMPER-PROOF CUSTOMER-SIDE HANDSHAKE (PHONE-ONLY CONFIRMATION) -->
    <!-- ========================================================================= -->
    @if($order->fulfillment_type === 'doorstep_delivery')
        @if($order->order_status === 'out_for_delivery')
            @if($order->hasRiderArrived())
                <!-- RIDER HAS ARRIVED: CUSTOMER PHONE INPUT BOX -->
                <div id="vmCustomerHandshakeBox" style="background: linear-gradient(135deg, #064E3B 0%, #065F46 100%); border: 3px solid #10B981; border-radius: 18px; padding: 26px 22px; margin-bottom: 24px; color: #FFFFFF; box-shadow: 0 10px 30px rgba(16, 185, 129, 0.25);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
                        <span style="display: inline-flex; align-items: center; gap: 7px; background: #10B981; color: #FFFFFF; padding: 5px 14px; border-radius: 999px; font-size: 11.5px; font-weight: 800; letter-spacing: 0.5px;">
                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #FFFFFF; animation: pulse 1.5s infinite;"></span>
                            <span>RIDER IS OUTSIDE AT YOUR DOORSTEP</span>
                        </span>
                        <span style="font-size: 12px; color: #A7F3D0; font-weight: 600;">
                            Arrived at: {{ $order->rider_arrived_at ? $order->rider_arrived_at->format('h:i A') : 'Just now' }}
                        </span>
                    </div>

                    <h2 style="font-size: 20px; font-weight: 800; color: #FFFFFF; margin: 0 0 8px;">
                        Inspect Parcel &amp; Confirm Delivery
                    </h2>
                    <p style="font-size: 13.5px; color: #D1FAE5; margin: 0 0 20px; line-height: 1.5;">
                        Your courier <strong>{{ $order->rider->user->name ?? 'Dispatch Rider' }}</strong> is waiting outside. Only you (the recipient) can confirm this delivery on your phone.
                    </p>

                    <form method="POST" action="{{ route('order.customer_confirm_delivery', $order->order_number) }}" enctype="multipart/form-data" style="background: rgba(0, 0, 0, 0.2); border: 1.5px solid rgba(255, 255, 255, 0.15); border-radius: 14px; padding: 20px;">
                        @csrf

                        <!-- Step 1: Inspection Note -->
                        <div style="margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.15);">
                            <span style="display: block; font-size: 11px; font-weight: 800; color: #A7F3D0; text-transform: uppercase; letter-spacing: 0.5px;">STEP 1: PHYSICAL CHECK</span>
                            <p style="font-size: 13px; color: #FFFFFF; margin: 4px 0 0;">
                                Please inspect the package box and tamper-evident seal before entering your code.
                            </p>
                        </div>

                        <!-- Step 2: Optional Photo Snapping -->
                        <div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.15);">
                            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px;">
                                <span style="font-size: 11px; font-weight: 800; color: #A7F3D0; text-transform: uppercase; letter-spacing: 0.5px;">STEP 2: SNAP CONDITION (OPTIONAL)</span>
                                <span style="font-size: 11.5px; color: #FCD34D; font-weight: 700;">OPTIONAL</span>
                            </div>
                            <p style="font-size: 12.5px; color: #E2E8F0; margin: 0 0 10px;">
                                Snap a quick photo of the parcel condition. Snapping is strictly optional, but protects your return rights.
                            </p>
                            
                            <!-- Hidden Camera/File Input -->
                            <input type="file" id="proof_photo" name="proof_photo" accept="image/*" capture="environment" style="display: none;" onchange="previewProofImage(this)">
                            
                            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                                <button type="button" onclick="document.getElementById('proof_photo').click()" style="background: rgba(255, 255, 255, 0.15); border: 1.5px dashed rgba(255, 255, 255, 0.4); color: #FFFFFF; padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                        <circle cx="12" cy="13" r="4"></circle>
                                    </svg>
                                    <span id="snapButtonText">Tap to Snap / Upload Photo</span>
                                </button>
                                <span style="font-size: 11.5px; color: #93C5FD;">No photo required to proceed</span>
                            </div>

                            <!-- Preview Thumbnail (revealed on selection) -->
                            <div id="proofPhotoPreviewContainer" style="display: none; margin-top: 12px; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 10px; max-width: 260px;">
                                <img id="proofPhotoPreviewImg" src="" alt="Proof Preview" style="width: 100%; height: 140px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,0.3); display: block; margin-bottom: 6px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 11px; color: #A7F3D0; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                        Photo Attached
                                    </span>
                                    <button type="button" onclick="removeProofPhoto()" style="background: transparent; border: none; color: #F87171; font-size: 11.5px; font-weight: 700; cursor: pointer;">Remove</button>
                                </div>
                                <div id="customerProofOptBadge" style="display: none; margin-top: 6px; font-size: 10.5px; color: #6EE7B7; font-weight: 700;">
                                    Optimized on-device (<span id="customerProofOptDetails"></span>)
                                </div>
                            </div>
                            <div id="customerProofCompressing" style="display: none; margin-top: 6px; font-size: 11.5px; color: #93C5FD;">
                                Compressing photo &amp; protecting privacy...
                            </div>
                        </div>

                        <!-- Step 3: Secret 6-Digit PIN Entry -->
                        <div style="margin-bottom: 22px;">
                            <span style="display: block; font-size: 11px; font-weight: 800; color: #A7F3D0; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">STEP 3: ENTER YOUR SECRET PIN</span>
                            <label for="delivery_otp" style="display: block; font-size: 13px; color: #FFFFFF; margin-bottom: 8px;">
                                Input your 6-digit Delivery PIN (shown below or received via SMS):
                            </label>

                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="text" id="delivery_otp" name="delivery_otp" maxlength="6" pattern="[0-9]{6}" required class="form-control" placeholder="••••••" style="width: 100%; max-width: 260px; padding: 12px 16px; font-size: 26px; font-weight: 900; letter-spacing: 6px; text-align: center; font-family: monospace; border: 2px solid #10B981; border-radius: 12px; background: #FFFFFF; color: #0F172A; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);">
                                
                                <div style="background: rgba(255, 215, 0, 0.2); border: 1px solid #FFD700; padding: 8px 14px; border-radius: 10px;">
                                    <span style="font-size: 10px; color: #FFD700; font-weight: 800; display: block; text-transform: uppercase;">YOUR PIN:</span>
                                    <strong style="font-family: monospace; font-size: 18px; color: #FFD700; letter-spacing: 2px;">{{ $order->verification_code }}</strong>
                                </div>
                            </div>
                            <span style="font-size: 11.5px; color: #D1FAE5; display: inline-flex; align-items: center; gap: 5px; margin-top: 8px;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                <span>Tamper-Proof Guarantee: This code only unlocks this specific order and cannot mark any other delivery completed.</span>
                            </span>
                        </div>

                        <!-- Confirm Delivery Button -->
                        <button type="submit" style="width: 100%; background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: #FFFFFF; border: none; padding: 16px 24px; border-radius: 12px; font-size: 15.5px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4); transition: transform 0.15s ease;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <span>Confirm Package Received &amp; Complete Delivery</span>
                        </button>
                    </form>
                </div>
            @else
                <!-- RIDER EN ROUTE: LIVE AUTO-POLLING WAITING CARD -->
                <div id="vmWaitingRiderArrivalCard" style="background: linear-gradient(135deg, #1E1B4B 0%, #312E81 100%); border: 2px solid #6366F1; border-radius: 16px; padding: 22px 20px; margin-bottom: 24px; color: #FFFFFF; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(99, 102, 241, 0.25); border: 2px solid #818CF8; display: flex; align-items: center; justify-content: center; color: #A5B4FC; flex-shrink: 0;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="5.5" cy="17.5" r="3.5"></circle>
                                <circle cx="18.5" cy="17.5" r="3.5"></circle>
                                <path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm-3 11.5V14l-3-3 4-3 2 3h4"></path>
                            </svg>
                        </div>
                        <div>
                            <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(99, 102, 241, 0.3); padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; color: #A5B4FC; margin-bottom: 4px;">
                                <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #38BDF8;"></span>
                                <span>COURIER EN ROUTE TO YOUR DOORSTEP</span>
                            </div>
                            <h3 style="font-size: 15.5px; font-weight: 800; margin: 0 0 2px; color: #FFFFFF;">
                                Dispatch Rider is in Transit
                            </h3>
                            <p style="font-size: 12.5px; color: #C7D2FE; margin: 0;">
                                Keep this screen open. When rider reaches your gate and marks arrived, your handshake input box will spring open automatically.
                            </p>
                        </div>
                    </div>
                    <div style="background: rgba(255, 255, 255, 0.1); padding: 8px 14px; border-radius: 10px; font-size: 11.5px; font-weight: 700; color: #E0E7FF; border: 1px solid rgba(255, 255, 255, 0.2); white-space: nowrap; display: inline-flex; align-items: center; gap: 6px;">
                        <span style="width: 7px; height: 7px; border-radius: 50%; background: #10B981;"></span>
                        <span>Live Screen Sync Active</span>
                    </div>
                </div>
            @endif
        @elseif($order->order_status === 'delivered')
            <!-- DELIVERED PROOF OF CONDITION CARD -->
            <div style="background: #FFFFFF; border: 2px solid #10B981; border-radius: 16px; padding: 22px; margin-bottom: 24px; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.1);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            <polyline points="9 12 11 14 15 10"/>
                        </svg>
                        <strong style="font-size: 15px; color: #065F46;">24-Hour Return &amp; Dispute Window Active</strong>
                    </div>
                    <span style="background: #ECFDF5; color: #059669; font-size: 11.5px; font-weight: 800; padding: 4px 10px; border-radius: 999px; border: 1px solid #A7F3D0;">
                        EXPIRES: {{ $order->refund_window_expires_at ? $order->refund_window_expires_at->format('d M, h:i A') : 'In 24 Hours' }}
                    </span>
                </div>
                <p style="font-size: 13px; color: #475569; margin: 0 0 14px; line-height: 1.5;">
                    Package received and verified on your device. Vendor payout (₦{{ number_format((float)$order->vendor_net_90, 2) }}) remains safely locked in Escrow until the 24-hour return window elapses without dispute.
                </p>

                @if($order->proof_of_delivery_image)
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 12px; display: flex; align-items: center; gap: 14px;">
                        <img src="{{ $order->proof_of_delivery_image }}" alt="Delivery Proof" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #CBD5E1;">
                        <div>
                            <span style="font-size: 11.5px; font-weight: 700; color: #0F172A; display: flex; align-items: center; gap: 6px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                                <span>Doorstep Package Condition Proof Captured</span>
                            </span>
                            <span style="font-size: 11px; color: #64748B;">Recorded at customer doorstep handover</span>
                        </div>
                    </div>
                @endif

                @if($order->pickup_proof_image)
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 12px; display: flex; align-items: center; gap: 14px; margin-top: 10px;">
                        <img src="{{ asset($order->pickup_proof_image) }}" alt="Pickup Condition Proof" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #CBD5E1;">
                        <div>
                            <span style="font-size: 11.5px; font-weight: 700; color: #0F172A; display: flex; align-items: center; gap: 6px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                <span>Courier Counter Pickup Proof Snapped</span>
                            </span>
                            <span style="font-size: 11px; color: #64748B;">Recorded at branch counter ({{ $order->picked_up_at ? $order->picked_up_at->format('d M, h:i A') : 'Handover' }})</span>
                        </div>
                    </div>
                @endif

                @if(is_array($order->doorstep_rejection_photos) && count($order->doorstep_rejection_photos) > 0)
                    <div style="background: #FEF2F2; border: 1px solid #FECACA; border-radius: 10px; padding: 12px; margin-top: 10px;">
                        <span style="font-size: 11.5px; font-weight: 700; color: #991B1B; display: flex; align-items: center; gap: 5px; margin-bottom: 8px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                            <span>Doorstep Rejected Item Photo Proofs ({{ count($order->doorstep_rejection_photos) }})</span>
                        </span>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            @foreach($order->doorstep_rejection_photos as $rPhoto)
                                <a href="{{ asset($rPhoto) }}" target="_blank">
                                    <img src="{{ asset($rPhoto) }}" alt="Rejection Snap" style="width: 55px; height: 55px; object-fit: cover; border-radius: 6px; border: 1px solid #F87171;">
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif
    @endif

    <!-- ========================================== -->
    <!-- 3. SECRET HANDSHAKE PIN REFERENCE CARD -->
    <!-- ========================================== -->
    @if(($order->fulfillment_type === 'doorstep_delivery' && $order->payment_status === 'paid' && !in_array($order->order_status, ['delivered', 'cancelled'], true)) || ($order->fulfillment_type === 'in_shop_pickup' && $order->payment_status === 'paid' && !in_array($order->order_status, ['picked_up', 'delivered', 'cancelled'], true)))
    <div style="background: linear-gradient(135deg, #0F172A 0%, #1E1B4B 100%); border: 2px solid #FFD700; border-radius: 16px; padding: 24px; margin-bottom: 24px; color: #FFFFFF; text-align: center; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);">
        
        <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 215, 0, 0.15); border: 1px solid rgba(255, 215, 0, 0.35); padding: 4px 14px; border-radius: 999px; font-size: 11.5px; font-weight: 800; color: #FFD700; margin-bottom: 12px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="7.5" cy="15.5" r="5.5"></circle>
                <path d="M21 2l-9.6 9.6"></path>
                <path d="M15.5 7.5l3 3L22 7l-3-3"></path>
            </svg>
            <span>YOUR SECRET HANDSHAKE VERIFICATION CODE</span>
        </div>

        <div style="margin-bottom: 12px;">
            <span id="vmOtpCodeText" style="font-family: monospace; font-size: 38px; font-weight: 900; color: #FFD700; letter-spacing: 8px; display: block;">
                {{ $order->verification_code }}
            </span>
        </div>

        <p style="font-size: 13px; color: #E2E8F0; max-width: 480px; margin: 0 auto 18px; line-height: 1.5;">
            @if($order->fulfillment_type === 'in_shop_pickup')
                Show this code to the cashier at <strong>{{ $order->shop->name }} ({{ $order->shop->lga->name ?? 'Uyo' }} LGA)</strong> after physically inspecting your items.
            @else
                Only input this code on <strong>your own phone</strong> after physically inspecting your items outside. The rider does not have this code.
            @endif
        </p>

        <!-- Quick Actions -->
        <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
            <button type="button" onclick="navigator.clipboard.writeText('{{ $order->fulfillment_type === 'in_shop_pickup' ? $order->pickup_verification_code : $order->verification_code }}'); alert('PIN copied to clipboard: {{ $order->fulfillment_type === 'in_shop_pickup' ? $order->pickup_verification_code : $order->verification_code }}');" style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.25); color: #FFFFFF; font-weight: 700; font-size: 12.5px; padding: 9px 18px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 7px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                </svg>
                <span>Copy PIN</span>
            </button>
            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', \App\Models\SystemSetting::get('support_whatsapp', '09118949035')) }}?text=Hello%20Victorious%20Market%20Support,%20I%20need%20assistance%20with%20Order%20{{ $order->order_number }}" target="_blank" style="background: #25D366; color: #FFFFFF; font-weight: 700; font-size: 12.5px; padding: 9px 18px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 7px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2z"/>
                </svg>
                <span>VMarket Support Desk</span>
            </a>
        </div>
    </div>

    @endif

    <!-- ========================================== -->
    <!-- 4. VERTICAL 5-STEP TRACKING TIMELINE -->
    <!-- ========================================== -->
    <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 16px; padding: 26px; margin-bottom: 24px; box-shadow: var(--vm-shadow-xs);">
        <h2 style="font-size: 16px; font-weight: 800; color: #0F172A; margin: 0 0 20px; display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5E17EB" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <span>Live Delivery Timeline</span>
        </h2>

        @php
            $status = $order->order_status;
            $steps = [
                ['key' => 'placed', 'title' => 'Order Placed', 'desc' => 'Order created in system', 'done' => true, 'time' => $order->created_at->format('h:i A')],
                ['key' => 'paid', 'title' => 'Payment Confirmed', 'desc' => 'Escrow locked via Paystack', 'done' => true, 'time' => $order->created_at->addMinutes(2)->format('h:i A')],
                ['key' => 'preparing', 'title' => 'Merchant Preparing', 'desc' => "Verified at {$order->shop->name} ({$order->shop->lga->name} LGA)", 'done' => in_array($status, ['processing', 'ready_for_pickup', 'out_for_delivery', 'delivered']), 'time' => '11:15 AM'],
                ['key' => 'dispatch', 'title' => $order->fulfillment_type === 'in_shop_pickup' ? 'Ready for Counter Pickup' : 'Out for Delivery', 'desc' => $order->fulfillment_type === 'in_shop_pickup' ? 'Waiting at branch counter' : ($order->hasRiderArrived() ? 'Rider arrived at doorstep' : 'Courier in transit'), 'done' => in_array($status, ['out_for_delivery', 'delivered']), 'time' => $order->rider_arrived_at ? $order->rider_arrived_at->format('h:i A') : '12:30 PM (ETA)'],
                ['key' => 'delivered', 'title' => 'Delivered & Received', 'desc' => 'Customer phone PIN verified', 'done' => ($status === 'delivered'), 'time' => $order->received_at ? $order->received_at->format('h:i A') : 'Pending Confirmation'],
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
                        @if($step['done'])
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        @else
                            {{ $idx + 1 }}
                        @endif
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
    <!-- 5. ORDER DETAILS & FINANCIAL SPLIT -->
    <!-- ========================================== -->
    <div style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 16px; padding: 26px; margin-bottom: 24px; box-shadow: var(--vm-shadow-xs);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
            <h2 style="font-size: 16px; font-weight: 800; color: #0F172A; margin: 0;">
                Order Items &amp; Settlement Details
            </h2>
            <span style="font-size: 12px; color: #64748B;">
                Branch: <strong>{{ $order->shop->name }}</strong> ({{ $order->shop->lga->name ?? 'Uyo' }} LGA)
            </span>
        </div>

        <!-- Items Listing -->
        <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 18px;">
            @foreach($order->items as $item)
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px;">
                    <div>
                        <strong style="font-size: 13.5px; color: #0F172A; display: block;">{{ $item->product_name }}</strong>
                        <span style="font-size: 12px; color: #64748B;">Qty: {{ $item->quantity }} &bull; Unit: &#8358;{{ number_format((float)$item->unit_price, 2) }}</span>
                        @if($item->defect_photo_path)
                            <div style="margin-top: 6px; display: flex; align-items: center; gap: 8px;">
                                <span style="background: #FEE2E2; color: #DC2626; font-size: 10.5px; font-weight: 800; padding: 2px 7px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                    Rejected ({{ ucfirst($item->fulfillment_status) }})
                                </span>
                                <a href="{{ asset($item->defect_photo_path) }}" target="_blank" style="font-size: 11px; font-weight: 700; color: #DC2626; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                    <span>View Defect Snap</span>
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                </a>
                            </div>
                        @endif
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
            <div style="display: flex; justify-content: space-between; color: #059669; font-weight: 700; background: #ECFDF5; padding: 7px 12px; border-radius: 8px; align-items: center;">
                <span style="display: flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 6v12M9 9h6a2 2 0 0 1 0 4H9m7 0a2 2 0 0 1 0 4H8"></path>
                    </svg>
                    <span>5% Cashback Reward (Credited to Wallet):</span>
                </span>
                <span>+&#8358;{{ number_format((float)$order->customer_cashback_5, 2) }}</span>
            </div>
            <div style="border-top: 2px dashed #E2E8F0; padding-top: 10px; margin-top: 4px; display: flex; justify-content: space-between; font-size: 16px; font-weight: 900; color: #5E17EB;">
                <span>Total Amount Paid:</span>
                <span>&#8358;{{ number_format((float)$order->grand_total, 2) }}</span>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation Actions -->
    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
        <a href="{{ route('storefront') }}" style="background: #FFFFFF; border: 1.5px solid #CBD5E1; color: #0F172A; text-decoration: none; font-weight: 700; font-size: 13.5px; padding: 12px 22px; border-radius: 10px; transition: all 0.15s ease; display: inline-flex; align-items: center; gap: 6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>Continue Shopping</span>
        </a>
        <a href="{{ route('customer.dashboard') }}" style="background: #5E17EB; color: #FFFFFF; text-decoration: none; font-weight: 700; font-size: 13.5px; padding: 12px 22px; border-radius: 10px; box-shadow: 0 4px 12px rgba(94, 23, 235, 0.3); display: inline-flex; align-items: center; gap: 6px;">
            <span>View My Orders &amp; Wallet</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
        </a>
    </div>

</div>

<!-- Real-time Polling & Camera Preview Script -->
<script src="{{ asset('assets/js/client-image-optimizer.js') }}"></script>
<script>
    async function previewProofImage(input) {
        if (input.files && input.files[0]) {
            const rawFile = input.files[0];
            const previewImg = document.getElementById('proofPhotoPreviewImg');
            const container = document.getElementById('proofPhotoPreviewContainer');
            const compressingEl = document.getElementById('customerProofCompressing');
            const optBadge = document.getElementById('customerProofOptBadge');
            const optDetails = document.getElementById('customerProofOptDetails');

            if (compressingEl) compressingEl.style.display = 'block';

            if (window.ClientImageOptimizer && typeof window.ClientImageOptimizer.compressFile === 'function') {
                try {
                    const result = await window.ClientImageOptimizer.compressFile(rawFile, {
                        maxDimension: 1200,
                        quality: 0.80
                    });

                    previewImg.src = result.previewUrl;
                    container.style.display = 'block';
                    document.getElementById('snapButtonText').innerText = 'Change Snapped Photo';

                    // Sync compressed file back into input.files via DataTransfer
                    input.files = window.ClientImageOptimizer.createFileList([result.file]);

                    if (optBadge && optDetails) {
                        optDetails.textContent = `${window.ClientImageOptimizer.formatBytes(result.originalSize)} → ${window.ClientImageOptimizer.formatBytes(result.optimizedSize)}, ${result.reductionPercent}% saved`;
                        optBadge.style.display = 'block';
                    }
                } catch (err) {
                    console.error('Customer proof compression fallback:', err);
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        container.style.display = 'block';
                        document.getElementById('snapButtonText').innerText = 'Change Snapped Photo';
                    };
                    reader.readAsDataURL(rawFile);
                } finally {
                    if (compressingEl) compressingEl.style.display = 'none';
                }
            } else {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    container.style.display = 'block';
                    document.getElementById('snapButtonText').innerText = 'Change Snapped Photo';
                };
                reader.readAsDataURL(rawFile);
            }
        }
    }

    function removeProofPhoto() {
        const input = document.getElementById('proof_photo');
        input.value = '';
        document.getElementById('proofPhotoPreviewImg').src = '';
        document.getElementById('proofPhotoPreviewContainer').style.display = 'none';
        const optBadge = document.getElementById('customerProofOptBadge');
        if (optBadge) optBadge.style.display = 'none';
        document.getElementById('snapButtonText').innerText = 'Tap to Snap / Upload Photo';
    }

    // Auto-poll if order is out_for_delivery and rider has not yet marked arrived
    @if($order->order_status === 'out_for_delivery' && !$order->hasRiderArrived())
        const pollArrivalInterval = setInterval(function() {
            fetch("{{ route('order.handshake_status', $order->order_number) }}")
                .then(response => response.json())
                .then(data => {
                    if (data.rider_arrived) {
                        clearInterval(pollArrivalInterval);
                        // Reload page to reveal the customer PIN handshake input box!
                        window.location.reload();
                    }
                })
                .catch(err => console.log('Poll sync error:', err));
        }, 3500);
    @endif
</script>
@endsection