@forelse($shops->take(4) as $shop)
    <a href="{{ route('shop.storefront', $shop->slug) }}" class="vm-discovery-tile" style="text-decoration: none; transition: transform 0.2s ease;">
        <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: #F8FAFC; border: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
            <img src="{{ $shop->logo_url ?? $shop->image_path ?? asset('assets/img/vm_icon.jpg') }}" alt="{{ $shop->name }}" style="width: 100%; height: 100%; object-fit: cover;">
            <span class="vm-tile-floating-badge" style="position: absolute; bottom: 4px; left: 4px; font-size: 9.5px; background: #1E293B; color: #FFFFFF; padding: 1px 6px; border-radius: 4px; font-weight: 700;">🏬 {{ $shop->lga->name ?? 'In-Shop' }}</span>
        </div>
        <span class="vm-discovery-tile-caption" style="display: block; font-size: 11.5px; color: #1E293B; margin-top: 4px; font-weight: 600; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $shop->name }}</span>
    </a>
@empty
    <div style="grid-column: 1 / -1; background: #F8FAFC; border: 1.5px dashed #E2E8F0; border-radius: 10px; padding: 18px 12px; text-align: center;">
        <div style="width: 38px; height: 38px; border-radius: 50%; background: #EFF6FF; color: #3B82F6; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px; font-size: 18px;">
            🏬
        </div>
        <strong style="display: block; font-size: 12.5px; color: #0F172A; margin-bottom: 4px;">No Walk-In Plazas in {{ $activeCity ?? 'this LGA' }} Yet</strong>
        <p style="font-size: 11px; color: #64748B; margin: 0 0 10px; line-height: 1.4;">
            Physical stores are being onboarded. Orders to {{ $activeCity ?? 'this LGA' }} are dispatched swiftly via our verified transit corridor.
        </p>
        <button type="button" onclick="if(window.vmOpenLocationModal) window.vmOpenLocationModal();" style="background: #FFFFFF; border: 1px solid #CBD5E1; color: #334155; font-size: 11px; font-weight: 700; padding: 5px 12px; border-radius: 6px; cursor: pointer;">
            📍 Change LGA
        </button>
    </div>
@endforelse