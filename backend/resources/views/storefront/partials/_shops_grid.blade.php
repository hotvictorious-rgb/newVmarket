@forelse($shops->take(4) as $shop)
    <a href="{{ route('shop.storefront', $shop->slug) }}" class="vm-discovery-tile" style="text-decoration: none; transition: transform 0.2s ease;">
        <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: #F8FAFC; border: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
            <img src="{{ $shop->logo_url ?? $shop->image_path ?? asset('assets/img/vm_icon.jpg') }}" alt="{{ $shop->name }}" style="width: 100%; height: 100%; object-fit: cover;">
            <span class="vm-tile-floating-badge" style="position: absolute; bottom: 4px; left: 4px; font-size: 9.5px; background: #1E293B; color: #FFFFFF; padding: 1px 6px; border-radius: 4px; font-weight: 700;">🏬 {{ $shop->lga->name ?? 'In-Shop' }}</span>
        </div>
        <span class="vm-discovery-tile-caption" style="display: block; font-size: 11.5px; color: #1E293B; margin-top: 4px; font-weight: 600; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $shop->name }}</span>
    </a>
@empty
    <div class="vm-discovery-tile" style="text-decoration: none;">
        <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: #F8FAFC; border: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: center; position: relative;">
            <img src="{{ asset('assets/img/vm_icon.jpg') }}" alt="Plaza Merchant" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px;">
            <span class="vm-tile-floating-badge" style="position: absolute; bottom: 4px; left: 4px; font-size: 9.5px; background: #1E293B; color: #FFFFFF; padding: 1px 6px; border-radius: 4px; font-weight: 600;">🏬 Uyo Plaza</span>
        </div>
        <span class="vm-discovery-tile-caption" style="display: block; font-size: 11.5px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Plaza Merchant...</span>
    </div>
@endforelse