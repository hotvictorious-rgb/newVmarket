@extends('layouts.app')

@section('title', 'In-Shop Inspection Pass — ' . $reservation->reservation_code)

@section('content')
<div class="vm-container" style="padding: 40px 16px 80px; max-width: 680px;">
    
    <div style="background: #FFFFFF; border: 2px solid var(--vm-gold); border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.08);">
        
        <!-- Pass Header -->
        <div style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); color: #FFF; padding: 24px; text-align: center; border-bottom: 2px solid var(--vm-gold);">
            <div style="font-size: 12px; font-weight: 700; color: var(--vm-gold); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">
                VICTORIOUS MARKET &bull; OFFICIAL IN-SHOP INSPECTION PASS
            </div>
            <h1 style="font-size: 20px; font-weight: 800; margin: 0 0 12px;">Inspect First, Pay Later Guarantee</h1>
            
            <div style="background: rgba(255, 215, 0, 0.15); border: 1.5px dashed var(--vm-gold); border-radius: 12px; padding: 14px 24px; display: inline-block;">
                <span style="font-size: 11px; color: #CBD5E1; display: block; margin-bottom: 2px;">YOUR IN-SHOP RESERVATION CODE:</span>
                <span style="font-family: monospace; font-size: 32px; font-weight: 900; color: var(--vm-gold); letter-spacing: 4px; display: block;">
                    {{ $reservation->reservation_code }}
                </span>
                <button type="button" onclick="navigator.clipboard.writeText('{{ $reservation->reservation_code }}'); alert('Reservation code {{ $reservation->reservation_code }} copied to clipboard!');" style="margin-top: 8px; background: rgba(255, 255, 255, 0.15); border: 1px solid rgba(255, 255, 255, 0.3); color: #FFF; font-size: 11.5px; font-weight: 700; padding: 4px 12px; border-radius: 6px; cursor: pointer;">
                    📋 Copy Code
                </button>
            </div>
        </div>

        <!-- Pass Body -->
        <div style="padding: 28px;">
            <!-- Status Badge -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #F1F5F9;">
                <div>
                    <span style="font-size: 11.5px; color: #64748B; display: block;">PASS STATUS:</span>
                    <strong style="color: #059669; font-size: 14px;">● Reserved (Item Staged at Counter)</strong>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 11.5px; color: #64748B; display: block;">ESTIMATED TOTAL:</span>
                    <strong style="color: var(--vm-primary); font-size: 16px;">&#8358;{{ number_format((float)$reservation->estimated_total, 2) }}</strong>
                </div>
            </div>

            <!-- Shop Location Card -->
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 16px; margin-bottom: 20px;">
                <div style="font-weight: 700; font-size: 13.5px; color: #0F172A; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                    <span>🏢</span> {{ $reservation->shop->name }}
                </div>
                <div style="font-size: 12.5px; color: #64748B; margin-bottom: 6px;">
                    📍 {{ $reservation->shop->address }} ({{ $reservation->shop->lga->name ?? 'Uyo' }} LGA)
                </div>
                <div style="font-size: 12px; color: #64748B;">
                    📞 Counter Support: <strong>{{ $reservation->shop->phone }}</strong>
                </div>
            </div>

            <!-- Reserved Item Card -->
            <div style="display: flex; gap: 14px; align-items: center; background: #FFF; border: 1px solid var(--vm-border); border-radius: 10px; padding: 14px; margin-bottom: 24px;">
                <div style="width: 50px; height: 50px; background: #F8FAFC; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                    📦
                </div>
                <div>
                    <strong style="font-size: 14px; color: #0F172A; display: block;">{{ $reservation->product->name }}</strong>
                    <span style="font-size: 12px; color: #64748B;">Quantity: {{ $reservation->quantity }} &bull; Category: {{ $reservation->product->category->name ?? 'Electronics' }}</span>
                </div>
            </div>

            <!-- Real-World 4-Step Instructions -->
            <div style="background: #F5F3FF; border: 1px solid rgba(94, 23, 235, 0.2); border-radius: 10px; padding: 18px; margin-bottom: 24px;">
                <div style="font-weight: 800; font-size: 13px; color: var(--vm-primary); margin-bottom: 10px;">
                    HOW TO COMPLETE THIS PURCHASE:
                </div>
                <ol style="margin: 0; padding-left: 18px; font-size: 12.5px; color: #475569; line-height: 1.7;">
                    <li>Visit <strong>{{ $reservation->shop->name }}</strong> at <strong>{{ $reservation->shop->address }}</strong>.</li>
                    <li>Present your code <strong>{{ $reservation->reservation_code }}</strong> to the cashier.</li>
                    <li>Physically hold, inspect, and test the <strong>{{ $reservation->product->name }}</strong>.</li>
                    <li>If satisfied, the cashier approves inspection and you pay digitally at the counter.</li>
                </ol>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 12px;">
                <a href="{{ route('storefront') }}" style="flex: 1; text-align: center; background: #F8FAFC; border: 1px solid var(--vm-border); color: #0F172A; text-decoration: none; font-weight: 700; font-size: 13px; padding: 12px; border-radius: 8px;">
                    &larr; Return to Storefront
                </a>
                <button type="button" onclick="window.print()" style="flex: 1; background: var(--vm-primary); color: #FFF; border: none; font-weight: 700; font-size: 13px; padding: 12px; border-radius: 8px; cursor: pointer;">
                    🖨️ Print Pass
                </button>
            </div>
        </div>
    </div>
</div>
@endsection