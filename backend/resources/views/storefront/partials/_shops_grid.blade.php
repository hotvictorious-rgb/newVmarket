@forelse($shops->take(4) as $shop)
    <a href="{{ route('shop.storefront', $shop->slug) }}" class="vm-discovery-tile" style="text-decoration: none; transition: transform 0.2s ease;">
        <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 10px; background: #F8FAFC; border: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
            <img src="{{ $shop->logo_url ?? $shop->image_path ?? asset('assets/img/vm_icon.jpg') }}" alt="{{ $shop->name }}" style="width: 100%; height: 100%; object-fit: cover;">
            <span class="vm-tile-floating-badge" style="position: absolute; bottom: 5px; left: 5px; font-size: 9.5px; background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(4px); color: #FFFFFF; padding: 2px 7px; border-radius: 5px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#38BDF8" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <span>Uyo Hub</span>
            </span>
        </div>
        <span class="vm-discovery-tile-caption" style="display: block; font-size: 11.5px; color: #0F172A; margin-top: 5px; font-weight: 700; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $shop->name }}</span>
    </a>
@empty
    <div style="grid-column: 1 / -1; background: #F8FAFC; border: 1.5px dashed #CBD5E1; border-radius: 12px; padding: 20px 14px; text-align: center;">
        <div style="width: 42px; height: 42px; border-radius: 50%; background: #EFF6FF; color: #2563EB; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
        <strong style="display: block; font-size: 13px; color: #0F172A; margin-bottom: 4px; font-weight: 700;">Central Fulfilment from Uyo Hub</strong>
        <p style="font-size: 11px; color: #64748B; margin: 0 0 12px; line-height: 1.45;">
            Victorious MARKET operates centrally out of Uyo, Akwa Ibom State, delivering to {{ $activeCity ?? 'your destination' }}.
        </p>
        <button type="button" onclick="if(window.vmOpenLocationModal) window.vmOpenLocationModal();" style="background: #FFFFFF; border: 1.5px solid #CBD5E1; color: #0F172A; font-size: 11px; font-weight: 700; padding: 6px 14px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: all 0.15s ease;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span>Change Destination</span>
        </button>
    </div>
@endforelse