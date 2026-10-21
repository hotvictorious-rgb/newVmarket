@if(isset($shops) && count($shops) > 1)
    <div class="dash-card" style="margin-bottom: 20px; border-left: 4px solid #0284C7; background: #F0F9FF;">
        <div style="padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 22px;">🏬</span>
                <div>
                    <div style="font-size: 13.5px; font-weight: 700; color: #0369A1;">
                        Plaza Branch Management ({{ count($shops) }} Registered Outlets)
                    </div>
                    <div style="font-size: 12px; color: #0284C7;">
                        Each shop operates from its own origin LGA with isolated inventory, counter verification, and rider coverage.
                    </div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <label for="vendorShopFilterSelect" style="font-size: 12px; font-weight: 700; color: #0369A1;">Active Shop Filter:</label>
                <select id="vendorShopFilterSelect" 
                        onchange="const url = new URL(window.location.href); if(this.value && this.value !== 'all') { url.searchParams.set('shop_id', this.value); } else { url.searchParams.delete('shop_id'); } window.location.href = url.toString();"
                        style="padding: 6px 12px; border: 1.5px solid #0284C7; border-radius: 6px; font-size: 12.5px; font-weight: 700; color: #0C4A6E; background: white; cursor: pointer;">
                    <option value="all">🌐 All Outlets (Consolidated Overview)</option>
                    @foreach($shops as $s)
                        <option value="{{ $s->id }}" {{ (isset($selectedShopId) && (int)$selectedShopId === (int)$s->id) ? 'selected' : '' }}>
                            🏬 {{ $s->name }} — 📍 {{ $s->lga->name ?? 'LGA' }} ({{ $s->state->name ?? 'Akwa Ibom' }})
                        </option>
                    @endforeach
                </select>
                @if(isset($selectedShopId) && $selectedShopId)
                    <button type="button" 
                            onclick="const url = new URL(window.location.href); url.searchParams.delete('shop_id'); window.location.href = url.toString();" 
                            class="btn-dash btn-dash-outline" 
                            style="padding: 5px 10px; font-size: 11.5px; border-color: #0284C7; color: #0284C7; background: white;">
                        Show All
                    </button>
                @endif
            </div>
        </div>
    </div>
@endif
