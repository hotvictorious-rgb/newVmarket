@forelse($shops->take(2) as $shop)
    <a href="#merchants" class="vm-discovery-tile" style="text-decoration: none;">
        <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: #F8FAFC; border: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: center; position: relative;">
            <img src="{{ asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="{{ $shop->name }}" style="width: 48px; height: 48px; opacity: 0.6;">
            <span class="vm-tile-floating-badge" style="position: absolute; bottom: 4px; left: 4px; font-size: 9.5px; background: #1E293B; color: #FFFFFF; padding: 1px 6px; border-radius: 4px; font-weight: 600;">🏬 In-Shop</span>
        </div>
        <span class="vm-discovery-tile-caption" style="display: block; font-size: 11.5px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">{{ Str::limit($shop->name, 12) }}</span>
    </a>
@empty
    <div class="vm-discovery-tile" style="text-decoration: none;">
        <div class="vm-discovery-tile-thumb-wrap" style="width: 100%; aspect-ratio: 1 / 1; border-radius: 8px; background: #F8FAFC; border: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: center; position: relative;">
            <img src="{{ asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="Emeka Electronics" style="width: 48px; height: 48px; opacity: 0.6;">
            <span class="vm-tile-floating-badge" style="position: absolute; bottom: 4px; left: 4px; font-size: 9.5px; background: #1E293B; color: #FFFFFF; padding: 1px 6px; border-radius: 4px; font-weight: 600;">🏬 In-Shop</span>
        </div>
        <span class="vm-discovery-tile-caption" style="display: block; font-size: 11.5px; color: #475569; margin-top: 4px; font-weight: 500; text-align: center;">Emeka Electronics...</span>
    </div>
@endforelse