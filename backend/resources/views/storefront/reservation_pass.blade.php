{{-- RETIRED: Public in-shop pickup reservation passes are retired under the retailer model. Support-managed pickup is an exceptional admin workflow only. --}}
@extends('layouts.app')

@section('title', 'In-Shop Inspection Pass — ' . $reservation->reservation_code)

@push('styles')
<style>
@media print {
    .vm-top-bar, .vm-header, .vm-nav-bar, .vm-footer, .vm-mobile-bottom-nav, .vm-pass-actions {
        display: none !important;
    }
    body {
        background: #FFFFFF !important;
        padding: 0 !important;
    }
    .vm-container {
        padding: 0 !important;
        max-width: 100% !important;
    }
    .vm-pass-card {
        border: 2px solid #0F172A !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }
}
</style>
@endpush

@section('content')
<div class="vm-container" style="padding: 40px 16px 80px; max-width: 680px;">
    
    <div class="vm-pass-card" style="background: #FFFFFF; border: 2px solid var(--vm-gold); border-radius: 18px; overflow: hidden; box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);">
        
        <!-- Pass Header (Boarding Pass Architecture) -->
        <div style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); color: #FFF; padding: 28px 24px; text-align: center; border-bottom: 2px solid var(--vm-gold); position: relative;">
            <div style="display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 800; color: var(--vm-gold); text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <polyline points="9 12 11 14 15 10"/>
                </svg>
                <span>Victorious MARKET &bull; Official Inspection Pass</span>
            </div>
            <h1 style="font-size: 22px; font-weight: 800; margin: 0 0 16px; color: #FFFFFF;">
                Inspect First, Pay Later Guarantee
            </h1>
            
            <div style="background: rgba(255, 215, 0, 0.12); border: 2px dashed var(--vm-gold); border-radius: 14px; padding: 16px 26px; display: inline-block; max-width: 90%;">
                <span style="font-size: 11px; color: #CBD5E1; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; display: block; margin-bottom: 4px;">
                    Your In-Shop Reservation Code:
                </span>
                <span style="font-family: monospace; font-size: 34px; font-weight: 900; color: var(--vm-gold); letter-spacing: 5px; display: block; line-height: 1.2;">
                    {{ $reservation->reservation_code }}
                </span>
                <button type="button" onclick="navigator.clipboard.writeText('{{ $reservation->reservation_code }}'); alert('Reservation code {{ $reservation->reservation_code }} copied to clipboard!');" style="margin-top: 10px; background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.3); color: #FFF; font-size: 11.5px; font-weight: 700; padding: 5px 14px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    <span>Copy Code</span>
                </button>
            </div>
        </div>

        <!-- Pass Body -->
        <div style="padding: 28px 24px;">
            <!-- Status & Total Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; padding-bottom: 16px; border-bottom: 1px solid #F1F5F9; flex-wrap: wrap; gap: 10px;">
                <div>
                    <span style="font-size: 11px; font-weight: 800; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">PASS STATUS</span>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10B981; display: inline-block;"></span>
                        <strong style="color: #059669; font-size: 14px;">Reserved (Item Staged at Counter)</strong>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 11px; font-weight: 800; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">ESTIMATED TOTAL</span>
                    <strong style="color: var(--vm-primary); font-size: 17px; font-weight: 800;">&#8358;{{ number_format((float)$reservation->estimated_total, 2) }}</strong>
                </div>
            </div>

            <!-- Merchant & Branch Location Card (Strict Multi-Shop Separation) -->
            <div style="background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 12px; padding: 18px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <span style="font-size: 10.5px; font-weight: 800; color: #5E17EB; text-transform: uppercase; letter-spacing: 0.5px; display: block;">MERCHANT &amp; SPECIFIC BRANCH</span>
                        <h2 style="font-size: 15px; font-weight: 800; color: #0F172A; margin: 2px 0 0; display: flex; align-items: center; gap: 7px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5E17EB" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                            <span>{{ $reservation->shop->name }}</span>
                            @if($reservation->shop->vendor && $reservation->shop->vendor->business_name)
                                <span style="font-size: 12px; font-weight: 600; color: #64748B;">({{ $reservation->shop->vendor->business_name }})</span>
                            @endif
                        </h2>
                    </div>
                    <span style="background: #F1F5F9; color: #334155; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; border: 1px solid #CBD5E1;">
                        Branch #{{ $reservation->shop->id }}
                    </span>
                </div>

                <div style="display: flex; align-items: flex-start; gap: 7px; font-size: 13px; color: #334155; margin-bottom: 10px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0284C7" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px;">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span>
                        <strong>{{ $reservation->shop->address }}</strong>
                        <span style="color: #64748B;">&bull; {{ $reservation->shop->lga->name ?? 'Uyo' }} LGA, Akwa Ibom State</span>
                    </span>
                </div>

                <div style="font-size: 12px; color: #059669; font-weight: 700; display: flex; align-items: center; gap: 6px; background: #ECFDF5; padding: 6px 12px; border-radius: 8px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <polyline points="9 12 11 14 15 10"/>
                    </svg>
                    <span>Verified Merchant Counter &bull; Zero Upfront Fee &bull; In-Person Testing</span>
                </div>
            </div>

            <!-- Reserved Item Card -->
            <div style="display: flex; gap: 14px; align-items: center; background: #FFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 14px; margin-bottom: 22px;">
                <div style="width: 52px; height: 52px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #5E17EB;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line>
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                </div>
                <div style="flex-grow: 1; min-width: 0;">
                    <strong style="font-size: 14.5px; color: #0F172A; display: block; line-height: 1.3;">{{ $reservation->product->name }}</strong>
                    <span style="font-size: 12px; color: #64748B;">
                        Quantity: <strong>{{ $reservation->quantity }}</strong> &bull; Category: <strong>{{ $reservation->product->category->name ?? 'General' }}</strong>
                    </span>
                </div>
            </div>

            <!-- Real-World 4-Step Instructions -->
            <div style="background: #F5F3FF; border: 1.5px solid rgba(94, 23, 235, 0.2); border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                <div style="font-weight: 800; font-size: 13px; color: var(--vm-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span>How to Complete This Purchase:</span>
                </div>
                <ol style="margin: 0; padding-left: 20px; font-size: 13px; color: #334155; line-height: 1.75;">
                    <li>Visit <strong>{{ $reservation->shop->name }}</strong> at <strong>{{ $reservation->shop->address }} ({{ $reservation->shop->lga->name ?? 'Uyo' }} LGA)</strong>.</li>
                    <li>Present your code <strong style="font-family: monospace; color: var(--vm-primary);">{{ $reservation->reservation_code }}</strong> to the store cashier.</li>
                    <li>Physically inspect, test, and hold the <strong>{{ $reservation->product->name }}</strong> at the counter.</li>
                    <li>If fully satisfied, the cashier marks inspection approved and you pay digitally or cash at the counter.</li>
                </ol>
            </div>

            <!-- Action Buttons -->
            <div class="vm-pass-actions" style="display: flex; gap: 12px;">
                <a href="{{ route('storefront') }}" style="flex: 1; text-align: center; background: #F8FAFC; border: 1.5px solid var(--vm-border); color: #0F172A; text-decoration: none; font-weight: 700; font-size: 13.5px; padding: 13px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: background 0.15s ease;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="12" x2="5" y2="12"></line>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    <span>Return to Storefront</span>
                </a>
                <button type="button" onclick="window.print()" style="flex: 1; background: var(--vm-primary); color: #FFF; border: none; font-weight: 700; font-size: 13.5px; padding: 13px; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px; box-shadow: 0 4px 14px rgba(94, 23, 235, 0.3);">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    <span>Print Inspection Pass</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection