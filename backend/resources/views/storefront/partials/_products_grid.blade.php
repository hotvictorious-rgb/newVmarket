@forelse($products as $product)
    @php
        $shopLgaId = $product->shop->lga_id ?? null;
        $isLocalShop = ($shopLgaId == $selectedLgaId);
        $lane = $activeLanes ? $activeLanes->firstWhere('origin_lga_id', $shopLgaId) : null;
    @endphp
    <div class="vm-product-card" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--vm-shadow-xs); transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1); position: relative;">
        
        <div>
            <!-- Product Thumbnail Box -->
            <div class="vm-product-img-wrap" style="width: 100%; aspect-ratio: 1 / 1; background: #F8FAFC; border-bottom: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: center; position: relative;">
                <img src="{{ asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="{{ $product->name }}" class="vm-product-img" style="width: 80px; height: 80px; opacity: 0.65; object-fit: contain;">
                
                @if($product->is_vmarket_flagship)
                    <span style="position: absolute; top: 10px; right: 10px; background: var(--vm-primary); color: #FFFFFF; font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 999px; letter-spacing: 0.4px;">
                        ★ FLAGSHIP
                    </span>
                @endif

                <!-- Dynamic Proximity Tag -->
                <span style="position: absolute; bottom: 8px; left: 8px; font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;
                    @if($isLocalShop)
                        background: #ECFDF5; color: #059669; border: 1px solid rgba(5, 150, 105, 0.2);
                    @elseif($lane)
                        background: #EFF6FF; color: #1D4ED8; border: 1px solid rgba(29, 78, 216, 0.2);
                    @else
                        background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0;
                    @endif">
                    @if($isLocalShop)
                        <span>⚡</span><span>In-Shop & Same-Day</span>
                    @elseif($lane)
                        <span>🚚</span><span>Delivers to {{ $activeCity }} (₦{{ number_format((float)$lane->base_fee) }})</span>
                    @else
                        <span>📦</span><span>Nationwide Transit</span>
                    @endif
                </span>
            </div>

            <!-- Product Information -->
            <div class="vm-product-info" style="padding: 14px;">
                <div class="vm-product-shop" style="display: flex; align-items: center; gap: 5px; font-size: 11.5px; color: #64748B; margin-bottom: 6px;">
                    <span>🏬</span>
                    <span style="font-weight: 500;">{{ Str::limit($product->shop->name ?? 'Emeka Electronics Plaza', 22) }}</span>
                    <span style="opacity: 0.5;">•</span>
                    <span style="color: #94A3B8;">{{ $product->shop->lga->name ?? 'Uyo' }}</span>
                </div>

                <h3 class="vm-product-name" style="font-size: 14px; font-weight: 700; color: #0F172A; margin: 0 0 10px; line-height: 1.35; min-height: 38px;"><a href="{{ route('product.detail', $product->slug) }}" style="color: inherit; text-decoration: none;">
                    {{ $product->name }}
                </a></h3>

                <div class="vm-product-price-row" style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px;">
                    <span class="vm-price-current" style="font-size: 17px; font-weight: 800; color: var(--vm-info);">
                        ₦{{ number_format((float)$product->unit_price, 2) }}
                    </span>
                    <span style="font-size: 11px; color: #059669; font-weight: 600; background: #ECFDF5; padding: 2px 7px; border-radius: 4px; border: 1px solid rgba(5, 150, 105, 0.15);">
                        +5% Reward
                    </span>
                </div>
            </div>
        </div>

        <!-- Add to Cart / Reservation Action -->
        <div style="padding: 0 14px 14px;">
            <a href="{{ route('product.detail', $product->slug) }}" style="width: 100%; background: #F8FAFC; border: 1.5px solid var(--vm-border); color: #0F172A; font-weight: 700; font-size: 12.5px; padding: 9px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; transition: all 0.15s ease;"><span>🔍 View Item &amp; Delivery</span></a>
        </div>

    </div>
@empty
    <div style="grid-column: 1 / -1; text-align: center; padding: 48px 20px; background: #FFFFFF; border: 1px dashed var(--vm-border); border-radius: 16px;">
        <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(94, 23, 235, 0.08); color: var(--vm-primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 14px; font-size: 24px;">
            📍
        </div>
        <h4 style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0 0 6px;">No Direct Local Merchants in {{ $activeCity }} Yet</h4>
        <p style="font-size: 13px; color: #64748B; max-width: 420px; margin: 0 auto 16px; line-height: 1.5;">
            We are actively onboarding physical plaza vendors in {{ $activeCity }}. In the meantime, you can explore products delivering from our Uyo Flagship Plaza.
        </p>
        <button type="button" onclick="vmSelectLga('Uyo', 'Akwa Ibom', 69)" style="background: var(--vm-primary); color: #FFFFFF; border: none; border-radius: 8px; padding: 9px 18px; font-size: 13px; font-weight: 600; cursor: pointer;">
            Switch to Uyo Plaza
        </button>
    </div>
@endforelse