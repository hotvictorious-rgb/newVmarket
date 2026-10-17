@extends('layouts.dashboard')

@section('title', 'STEP 1: Plaza Counter Pickup — Delivery Rider')
@section('role_title', 'Delivery Rider Dispatch Cockpit')
@section('page_title', 'STEP 1: PLAZA COUNTER PICKUP')
@section('role_badge', 'DISPATCH RIDER')
@section('role_badge_class', 'role-badge-rider')

@section('sidebar_menu')
    @include('rider.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('rider.dashboard') }}">Rider Cockpit</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">STEP 1: PLAZA COUNTER PICKUP</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏬</span>
                <span>STEP 1: PLAZA COUNTER PICKUP</span>
            </h1>
            <p class="page-header-subtitle">
                Collect parcel at merchant branch counter. Enter the merchant's secret 6-digit pickup code on your phone to verify package custody.
            </p>
        </div>
        <div>
            <span class="dash-badge dash-badge-gold" style="padding: 6px 14px; font-size: 12.5px; font-weight: 700;">
                {{ count($activeDeliveries) }} Counter Handover(s) Awaiting
            </span>
        </div>
    </div>

    @if(session('success'))
        <div style="background: #ECFDF5; border-left: 4px solid #10B981; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #065F46; font-size: 13.5px; font-weight: 600;">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if(session('status'))
        <div style="background: #ECFDF5; border-left: 4px solid #10B981; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #065F46; font-size: 13.5px; font-weight: 600;">
            ✅ {{ session('status') }}
        </div>
    @endif
    @if($errors->any())
        <div style="background: #FEF2F2; border-left: 4px solid #EF4444; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #991B1B; font-size: 13.5px; font-weight: 600;">
            ❌ {{ $errors->first() }}
        </div>
    @endif

    @php
        $selectedOrderNumber = request('order_number');
        $currentOrder = $activeDeliveries->firstWhere('order_number', $selectedOrderNumber) ?? $activeDeliveries->first();
    @endphp

    @if($currentOrder)
        <!-- If multiple deliveries, show selector tabs -->
        @if(count($activeDeliveries) > 1)
            <div style="display: flex; gap: 8px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 6px;">
                @foreach($activeDeliveries as $del)
                    <a href="{{ route('rider.pickup.index', ['order_number' => $del->order_number]) }}" style="text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 12.5px; font-weight: 700; white-space: nowrap; border: 1.5px solid {{ $del->id === $currentOrder->id ? 'var(--adm-primary)' : 'var(--adm-border)' }}; background: {{ $del->id === $currentOrder->id ? 'var(--adm-primary)' : '#FFFFFF' }}; color: {{ $del->id === $currentOrder->id ? '#FFFFFF' : 'var(--adm-text)' }};">
                        📦 {{ $del->order_number }} ({{ $del->shop->name ?? 'Shop' }})
                    </a>
                @endforeach
            </div>
        @endif

        <div style="display: grid; grid-template-columns: 1fr; gap: 24px; max-width: 720px;">
            
            <!-- 1. Origin Merchant Outlet Info Card -->
            <div class="dash-card" style="border-top: 3.5px solid var(--adm-gold);">
                <div class="dash-card-header">
                    <div>
                        <div class="dash-card-title">Origin Merchant Outlet &amp; Counter Location</div>
                        <span class="dash-card-subtitle">Head to this physical branch counter to inspect and collect the parcel</span>
                    </div>
                </div>
                <div class="dash-card-body">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                        <div>
                            <span style="font-size: 11px; font-weight: 800; color: var(--adm-text-muted); text-transform: uppercase;">MERCHANT PLAZA OUTLET</span>
                            <h3 style="font-size: 19px; font-weight: 800; color: var(--adm-text); margin: 3px 0 4px;">{{ $currentOrder->shop->name ?? 'Victor Tech Uyo Flagship' }}</h3>
                            <p style="font-size: 13.5px; color: var(--adm-text-muted); margin: 0;">📍 {{ $currentOrder->shop->address ?? 'Shop 12, Ibom Plaza Commercial Center, Uyo' }}</p>
                        </div>
                        <div>
                            @if($currentOrder->shop && $currentOrder->shop->phone)
                                <a href="tel:{{ $currentOrder->shop->phone }}" class="btn-dash btn-dash-secondary" style="font-size: 12px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px;">
                                    <span>📞 Call Shop</span>
                                </a>
                            @endif
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; padding-top: 14px; border-top: 1px solid var(--adm-border);">
                        <div>
                            <span style="font-size: 11px; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase;">Order Number</span>
                            <div style="font-size: 14px; font-weight: 800; color: var(--adm-primary);">{{ $currentOrder->order_number }}</div>
                        </div>
                        <div>
                            <span style="font-size: 11px; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase;">Package Value</span>
                            <div style="font-size: 14px; font-weight: 800; color: var(--adm-text);">₦{{ number_format($currentOrder->total_amount, 2) }}</div>
                        </div>
                        <div>
                            <span style="font-size: 11px; font-weight: 700; color: var(--adm-text-muted); text-transform: uppercase;">Destination Customer</span>
                            <div style="font-size: 14px; font-weight: 700; color: var(--adm-text);">{{ $currentOrder->customer->name ?? 'Shopper' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Dual-Custody Handshake Input Card (Mirrors Customer Handshake) -->
            <div style="background: linear-gradient(135deg, #0F172A 0%, #1E1B4B 100%); border: 2.5px solid #FFD700; border-radius: 16px; padding: 26px; color: #FFFFFF; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);">
                
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                    <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255, 215, 0, 0.15); border: 1px solid rgba(255, 215, 0, 0.35); padding: 5px 14px; border-radius: 999px; font-size: 11.5px; font-weight: 800; color: #FFD700;">
                        <span>🔑</span>
                        <span>COURIER PLAZA PICKUP HANDSHAKE</span>
                    </div>
                    <span style="font-size: 11.5px; color: #94A3B8;">Custody Transfer: Vendor &rarr; Rider</span>
                </div>

                <p style="font-size: 13.5px; color: #E2E8F0; margin: 0 0 20px; line-height: 1.5;">
                    Ask the counter staff or shop manager for the <strong>6-digit Pickup Code</strong> displayed on their screen. Enter it on your phone below to seal package custody.
                </p>

                <!-- Handover Ready Indicator -->
                <div id="handoverReadyBanner" style="display: {{ $currentOrder->isVendorHandoverReady() ? 'flex' : 'none' }}; align-items: center; gap: 10px; background: rgba(16, 185, 129, 0.2); border: 1px solid #10B981; border-radius: 10px; padding: 10px 14px; margin-bottom: 18px;">
                    <span style="font-size: 16px;">⚡</span>
                    <span style="font-size: 12.5px; color: #A7F3D0; font-weight: 700;">
                        Merchant authorized handover! The 6-digit code is active on their screen.
                    </span>
                </div>

                <div id="handoverWaitingBanner" style="display: {{ !$currentOrder->isVendorHandoverReady() ? 'flex' : 'none' }}; align-items: center; gap: 10px; background: rgba(245, 158, 11, 0.18); border: 1px solid #F59E0B; border-radius: 10px; padding: 10px 14px; margin-bottom: 18px;">
                    <span style="font-size: 16px;">⏳</span>
                    <span style="font-size: 12.5px; color: #FDE68A; font-weight: 600;">
                        Waiting for merchant to click "Rider Arrived / Handover Ready" on their screen. You can still input the code if they told it to you.
                    </span>
                </div>

                <!-- Handshake Submission Form -->
                <form method="POST" action="{{ route('rider.verify.pickup.otp') }}" enctype="multipart/form-data" style="background: rgba(0, 0, 0, 0.25); border: 1.5px solid rgba(255, 255, 255, 0.15); border-radius: 14px; padding: 22px;">
                    @csrf
                    <input type="hidden" name="order_number" value="{{ $currentOrder->order_number }}">

                    <!-- Step 1: Physical Check -->
                    <div style="margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.15);">
                        <span style="display: block; font-size: 11px; font-weight: 800; color: #A7F3D0; text-transform: uppercase; letter-spacing: 0.5px;">STEP 1: PHYSICAL SEAL CHECK</span>
                        <p style="font-size: 13px; color: #FFFFFF; margin: 4px 0 0;">
                            Inspect the package packaging box, carton seals, and labeling before accepting custody.
                        </p>
                    </div>

                    <!-- Step 2: Optional Photo Snapping -->
                    <div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.15);">
                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px;">
                            <span style="font-size: 11px; font-weight: 800; color: #A7F3D0; text-transform: uppercase; letter-spacing: 0.5px;">STEP 2: SNAP CONDITION (OPTIONAL)</span>
                            <span style="font-size: 11.5px; color: #FCD34D; font-weight: 700;">OPTIONAL</span>
                        </div>
                        <p style="font-size: 12.5px; color: #E2E8F0; margin: 0 0 12px; line-height: 1.4;">
                            Snap a quick photo of the parcel at the merchant counter. Snapping is strictly optional, but protects you against claims of transit damage upon delivery.
                        </p>

                        <!-- Camera / File Input -->
                        <input type="file" id="proof_photo" name="proof_photo" accept="image/*" capture="environment" style="display: none;" onchange="previewPickupImage(this)">

                        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                            <button type="button" onclick="document.getElementById('proof_photo').click()" style="background: rgba(255, 255, 255, 0.15); border: 1.5px dashed rgba(255, 255, 255, 0.4); color: #FFFFFF; padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                                <span>📸</span> <span id="snapPickupBtnText">Tap to Snap / Upload Photo</span>
                            </button>
                            <span style="font-size: 11.5px; color: #93C5FD;">No photo required to proceed</span>
                        </div>

                        <!-- Preview Container -->
                        <div id="pickupPhotoPreviewContainer" style="display: none; margin-top: 12px; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 10px; max-width: 260px;">
                            <img id="pickupPhotoPreviewImg" src="" alt="Proof Preview" style="width: 100%; height: 140px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,0.3); display: block; margin-bottom: 6px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 11px; color: #A7F3D0; font-weight: 700;">✓ Photo Attached</span>
                                <button type="button" onclick="removePickupPhoto()" style="background: transparent; border: none; color: #F87171; font-size: 11.5px; font-weight: 700; cursor: pointer;">Remove</button>
                            </div>
                            <div id="pickupOptBadge" style="display: none; margin-top: 6px; font-size: 10.5px; color: #6EE7B7; font-weight: 700;">
                                ⚡ Optimized on-device (<span id="pickupOptDetails"></span>)
                            </div>
                        </div>
                        <div id="pickupCompressing" style="display: none; margin-top: 6px; font-size: 11.5px; color: #93C5FD;">
                            ⏳ Compressing photo &amp; stripping GPS data...
                        </div>
                    </div>

                    <!-- Step 3: Enter Merchant 6-Digit Pickup Code -->
                    <div style="margin-bottom: 22px;">
                        <span style="display: block; font-size: 11px; font-weight: 800; color: #A7F3D0; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">STEP 3: ENTER 6-DIGIT MERCHANT PICKUP PIN</span>
                        <label for="pickup_otp" style="display: block; font-size: 13px; color: #FFFFFF; margin-bottom: 8px;">
                            Input the 6-digit PIN given by the merchant counter staff:
                        </label>

                        <div style="max-width: 300px;">
                            <input type="text" id="pickup_otp" name="pickup_otp" maxlength="6" pattern="[0-9]{6}" required class="form-control" placeholder="••••••" style="width: 100%; padding: 14px 16px; font-size: 28px; font-weight: 900; letter-spacing: 8px; text-align: center; font-family: monospace; border: 2.5px solid #FFD700; border-radius: 12px; background: #FFFFFF; color: #0F172A; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);">
                        </div>
                        <span style="font-size: 11.5px; color: #D1FAE5; display: block; margin-top: 8px; line-height: 1.4;">
                            🔒 Mathematical Non-Interference Guarantee: This 6-digit PIN only unlocks this specific package and cannot pick up any other order in the system.
                        </span>
                    </div>

                    <!-- Confirm Pickup Button -->
                    <button type="submit" style="width: 100%; background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: #FFFFFF; border: none; padding: 16px 24px; border-radius: 12px; font-size: 16px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 4px 16px rgba(16, 185, 129, 0.4); transition: transform 0.15s ease;">
                        <span>🏬</span>
                        <span>Confirm Counter Pickup &amp; Start Transit</span>
                    </button>
                </form>
            </div>
        </div>
    @else
        <div class="dash-card">
            <div class="dash-card-body" style="text-align: center; padding: 60px 20px;">
                <span style="font-size: 48px; display: block; margin-bottom: 12px;">🏍️</span>
                <h3 style="font-size: 18px; font-weight: 800; color: var(--adm-text);">No Deliveries Pending Counter Pickup</h3>
                <p style="font-size: 13.5px; color: var(--adm-text-muted); margin-top: 6px;">You currently have zero packages awaiting plaza collection.</p>
                <div style="margin-top: 20px;">
                    <a href="{{ route('rider.deliveries.index') }}" class="btn-dash btn-dash-primary" style="padding: 10px 20px; font-size: 13px; font-weight: 700; text-decoration: none;">
                        View Active Deliveries &rarr;
                    </a>
                </div>
            </div>
        </div>
    @endif

    <script src="{{ asset('assets/js/client-image-optimizer.js') }}"></script>
    <script>
        async function previewPickupImage(input) {
            if (input.files && input.files[0]) {
                const rawFile = input.files[0];
                const previewImg = document.getElementById('pickupPhotoPreviewImg');
                const container = document.getElementById('pickupPhotoPreviewContainer');
                const compressingEl = document.getElementById('pickupCompressing');
                const optBadge = document.getElementById('pickupOptBadge');
                const optDetails = document.getElementById('pickupOptDetails');

                if (compressingEl) compressingEl.style.display = 'block';

                if (window.ClientImageOptimizer && typeof window.ClientImageOptimizer.compressFile === 'function') {
                    try {
                        const result = await window.ClientImageOptimizer.compressFile(rawFile, {
                            maxDimension: 1200,
                            quality: 0.80
                        });

                        previewImg.src = result.previewUrl;
                        container.style.display = 'block';
                        document.getElementById('snapPickupBtnText').innerText = 'Change Photo';

                        // Sync compressed WebP file into input.files via DataTransfer
                        input.files = window.ClientImageOptimizer.createFileList([result.file]);

                        if (optBadge && optDetails) {
                            optDetails.textContent = `${window.ClientImageOptimizer.formatBytes(result.originalSize)} → ${window.ClientImageOptimizer.formatBytes(result.optimizedSize)}, ${result.reductionPercent}% saved`;
                            optBadge.style.display = 'block';
                        }
                    } catch (err) {
                        console.error('Pickup photo compression fallback:', err);
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewImg.src = e.target.result;
                            container.style.display = 'block';
                            document.getElementById('snapPickupBtnText').innerText = 'Change Photo';
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
                        document.getElementById('snapPickupBtnText').innerText = 'Change Photo';
                    };
                    reader.readAsDataURL(rawFile);
                }
            }
        }

        function removePickupPhoto() {
            document.getElementById('proof_photo').value = '';
            document.getElementById('pickupPhotoPreviewContainer').style.display = 'none';
            const optBadge = document.getElementById('pickupOptBadge');
            if (optBadge) optBadge.style.display = 'none';
            document.getElementById('snapPickupBtnText').innerText = 'Tap to Snap / Upload Photo';
        }

        @if($currentOrder)
            // Polling to detect when vendor clicks "Rider Arrived / Handover Ready"
            setInterval(function() {
                fetch('/rider/orders/{{ $currentOrder->order_number }}/pickup-status')
                    .then(res => res.json())
                    .then(data => {
                        const readyBanner = document.getElementById('handoverReadyBanner');
                        const waitingBanner = document.getElementById('handoverWaitingBanner');
                        if (data.is_handover_ready) {
                            if (readyBanner) readyBanner.style.display = 'flex';
                            if (waitingBanner) waitingBanner.style.display = 'none';
                        }
                        if (data.is_picked_up) {
                            window.location.href = "{{ route('rider.dashboard') }}";
                        }
                    })
                    .catch(err => console.debug('Pickup status poll error:', err));
            }, 3500);
        @endif
    </script>
@endsection