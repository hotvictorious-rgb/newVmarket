@if($isCorridorCatalog ?? false)
    <div style="grid-column: 1 / -1; background: #EFF6FF; border: 1.5px solid #BFDBFE; border-radius: 14px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 8px;">
        <div style="display: flex; align-items: center; gap: 12px; font-size: 13px; color: #1E40AF;">
            <div style="width: 38px; height: 38px; border-radius: 8px; background: #DBEAFE; color: #1D4ED8; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <div>
                <strong style="font-size: 13.5px; color: #1E3A8A; display: block; margin-bottom: 2px;">Transit Corridor Dispatch to {{ $activeCity }}</strong>
                <div style="font-size: 11.5px; color: #3B82F6; line-height: 1.4;">
                    Local physical shops are being onboarded. These items deliver swiftly to {{ $activeCity }} via verified fast transit (12&ndash;24 hrs).
                </div>
            </div>
        </div>
        <a href="{{ route('storefront', ['scope' => 'all']) }}#catalog" style="background: #FFFFFF; border: 1px solid #93C5FD; color: #1D4ED8; font-size: 11.5px; font-weight: 700; padding: 7px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
            <span>View All Transit Options</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
    </div>
@endif

@forelse($products as $product)
    @php
        $shopLgaId = $product->shop->lga_id ?? null;
        $isLocalShop = ($shopLgaId == $selectedLgaId);
        $lane = $activeLanes ? $activeLanes->firstWhere('origin_lga_id', $shopLgaId) : null;
    @endphp
    <div class="vm-product-card">
        
        <div>
            <!-- Product Thumbnail Box -->
            <div class="vm-product-img-wrap" style="width: 100%; aspect-ratio: 1 / 1; background: #F8FAFC; border-bottom: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                <img src="{{ $product->image_url ?? $product->image_path ?? asset('assets/img/placeholder/placeholder-1-1.png') }}" alt="{{ $product->name }}" class="vm-product-img" style="max-width: 80%; max-height: 80%; object-fit: contain;">
                
                <!-- Condition Badge (Brand New vs UK Used) -->
                @if($product->condition === 'uk_used')
                    <span style="position: absolute; top: 10px; left: 10px; background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-size: 9.5px; font-weight: 800; padding: 2px 8px; border-radius: 999px; letter-spacing: 0.3px; display: inline-flex; align-items: center; gap: 4px;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>UK Used</span>
                    </span>
                @else
                    <span style="position: absolute; top: 10px; left: 10px; background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; font-size: 9.5px; font-weight: 800; padding: 2px 8px; border-radius: 999px; letter-spacing: 0.3px; display: inline-flex; align-items: center; gap: 4px;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        <span>Brand New</span>
                    </span>
                @endif

                @if($product->is_vmarket_flagship)
                    <span style="position: absolute; top: 10px; right: 10px; background: var(--vm-primary, #5E17EB); color: #FFFFFF; font-size: 9.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; letter-spacing: 0.4px; display: inline-flex; align-items: center; gap: 4px;">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        <span>FLAGSHIP</span>
                    </span>
                @endif

                <!-- Dynamic Proximity Tag -->
                <span style="position: absolute; bottom: 8px; left: 8px; font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;
                    @if($isLocalShop)
                        background: #ECFDF5; color: #059669; border: 1px solid rgba(5, 150, 105, 0.2);
                    @elseif($lane)
                        background: #EFF6FF; color: #1D4ED8; border: 1px solid rgba(29, 78, 216, 0.2);
                    @else
                        background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0;
                    @endif">
                    @if($isLocalShop)
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        <span>In-Shop {{ $activeCity }} &bull; Same-Day</span>
                    @elseif($lane)
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        <span>Transit from {{ $product->shop->lga->name ?? 'Hub' }} (&#8358;{{ number_format((float)$lane->base_fee) }})</span>
                    @else
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                        <span>Regional Transit</span>
                    @endif
                </span>
            </div>

            <!-- Product Information -->
            <div class="vm-product-info" style="padding: 12px 14px 10px;">
                <div class="vm-product-shop" style="display: flex; align-items: center; gap: 5px; font-size: 11.5px; color: #64748B; margin-bottom: 5px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                    <a href="{{ route('shop.storefront', $product->shop->slug ?? $product->shop_id) }}" style="color: #475569; text-decoration: none; font-weight: 600;">
                        Victorious MARKET
                    </a>
                    <span style="opacity: 0.5;">•</span>
                    <span style="color: #94A3B8;">{{ $product->shop->lga->name ?? 'Uyo' }}</span>
                </div>

                <h3 class="vm-product-name" style="font-size: 14px; font-weight: 700; color: #0F172A; margin: 0 0 8px; line-height: 1.35; min-height: 36px;">
                    <a href="{{ route('product.detail', $product->slug) }}" style="color: inherit; text-decoration: none;">
                        {{ $product->name }}
                    </a>
                </h3>

                <div class="vm-product-price-row" style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px; flex-wrap: wrap; gap: 4px;">
                    <span class="vm-price-current" style="font-size: 16.5px; font-weight: 800; color: #5E17EB;">
                        &#8358;{{ number_format((float)$product->unit_price, 2) }}
                    </span>
                    <span style="font-size: 10.5px; color: #059669; font-weight: 700; background: #ECFDF5; padding: 2px 6px; border-radius: 4px; border: 1px solid rgba(5, 150, 105, 0.15);">
                        +5% Reward
                    </span>
                </div>

                <!-- Star Rating & Stock / Cargo Status -->
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px; font-size: 11px; color: #64748B; margin-bottom: 4px;">
                    <div style="display: flex; align-items: center; gap: 4px;">
                        @if(($product->reviews_count ?? 0) > 0)
                            <span style="color: #F59E0B; display: inline-flex; align-items: center;">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            </span>
                            <strong style="color: #0F172A; font-weight: 700;">{{ number_format((float)$product->rating, 1) }}</strong>
                            <span>({{ $product->reviews_count }})</span>
                        @else
                            <span style="color: #F59E0B; display: inline-flex; align-items: center;">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            </span>
                            <span style="color: #0F172A; font-weight: 700;">5.0</span>
                            <span style="color: #059669; font-weight: 600;">Verified</span>
                        @endif
                    </div>
                    <div>
                        @if(!$product->is_in_stock)
                            <span style="color: #DC2626; font-weight: 600; display: inline-flex; align-items: center; gap: 3px;">
                                <span style="width: 5px; height: 5px; border-radius: 50%; background: #EF4444;"></span>
                                <span>Out of Stock</span>
                            </span>
                        @elseif(in_array($product->package_tier, ['keke', 'bulky'], true))
                            <span style="font-size: 9.5px; font-weight: 700; background: #FFFBEB; color: #B45309; border: 1px solid #FDE68A; padding: 2px 6px; border-radius: 4px; display: inline-flex; align-items: center; gap: 3px;">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                <span>Keke Cargo</span>
                            </span>
                        @else
                            <span style="color: #10B981; font-weight: 600; display: inline-flex; align-items: center; gap: 3px;">
                                <span style="width: 5px; height: 5px; border-radius: 50%; background: #10B981;"></span>
                                <span>In Stock</span>
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Add to Cart & Details Action (Responsive 2-Col Grid) -->
        <div style="padding: 0 14px 14px;">
            <div class="vm-card-action-grid">
                @if($product->is_in_stock)
                    <button type="button" class="vm-btn-add-cart" 
                            data-id="{{ $product->id }}" 
                            data-name="{{ $product->name }}" 
                            data-price="{{ $product->unit_price }}" 
                            data-image="{{ $product->image_url ?? $product->image_path ?? asset('assets/img/placeholder/placeholder-1-1.png') }}"
                            data-shop="Victorious MARKET" 
                            data-lga="Uyo" 
                            data-slug="{{ $product->slug }}"
                            style="background: #F5F3FF; border: 1.5px solid rgba(94, 23, 235, 0.25); color: #5E17EB; font-weight: 700; font-size: 12px; padding: 8px 6px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px; transition: all 0.15s ease;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        <span>+ Cart</span>
                    </button>
                @else
                    <button type="button" disabled
                            style="background: #F1F5F9; border: 1.5px solid #E2E8F0; color: #94A3B8; font-weight: 700; font-size: 12px; padding: 8px 6px; border-radius: 8px; cursor: not-allowed; display: flex; align-items: center; justify-content: center; gap: 5px; opacity: 0.7;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>Out of Stock</span>
                    </button>
                @endif
                <a href="{{ route('product.detail', $product->slug) }}" style="background: #0F172A; border: none; color: #FFFFFF; font-weight: 700; font-size: 12px; padding: 8px 6px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px; text-decoration: none; transition: all 0.15s ease;">
                    <span>Details</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
            </div>
        </div>

    </div>
@empty
    @if(request('search'))
        <div style="grid-column: 1 / -1; text-align: center; padding: 48px 20px; background: #FFFFFF; border: 1.5px dashed #CBD5E1; border-radius: 16px;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
            <h4 style="font-size: 17px; font-weight: 800; color: #0F172A; margin: 0 0 6px;">No products found matching "{{ request('search') }}"</h4>
            <p style="font-size: 13.5px; color: #64748B; max-width: 440px; margin: 0 auto 18px; line-height: 1.5;">
                We couldn't find any items matching your search keyword in this location filter. Check your spelling or browse all verified marketplace items.
            </p>
            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <a href="{{ route('storefront') }}#catalog" style="background: var(--vm-primary, #5E17EB); color: #FFFFFF; text-decoration: none; border-radius: 8px; padding: 10px 20px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                    <span>Clear Search Filter</span>
                </a>
            </div>
        </div>
    @elseif(request('category'))
        <div style="grid-column: 1 / -1; text-align: center; padding: 48px 20px; background: #FFFFFF; border: 1.5px dashed #CBD5E1; border-radius: 16px;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: #EDE9FE; color: #5E17EB; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            </div>
            <h4 style="font-size: 17px; font-weight: 800; color: #0F172A; margin: 0 0 6px;">No products found in this category</h4>
            <p style="font-size: 13.5px; color: #64748B; max-width: 440px; margin: 0 auto 18px; line-height: 1.5;">
                Merchants currently have no active listings under "{{ ucwords(str_replace('-', ' ', request('category'))) }}" in {{ $activeCity }}.
            </p>
            <a href="{{ route('storefront') }}#catalog" style="background: var(--vm-primary, #5E17EB); color: #FFFFFF; text-decoration: none; border-radius: 8px; padding: 10px 20px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                <span>View All Products</span>
            </a>
        </div>
    @elseif(request('condition'))
        <div style="grid-column: 1 / -1; text-align: center; padding: 48px 20px; background: #FFFFFF; border: 1.5px dashed #CBD5E1; border-radius: 16px;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: #FEF3C7; color: #D97706; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <h4 style="font-size: 17px; font-weight: 800; color: #0F172A; margin: 0 0 6px;">No {{ request('condition') === 'uk_used' ? 'UK Used' : 'Brand New' }} Items Found</h4>
            <p style="font-size: 13.5px; color: #64748B; max-width: 440px; margin: 0 auto 18px; line-height: 1.5;">
                No active listings with condition "{{ request('condition') === 'uk_used' ? 'UK Used (Clean Grade A)' : 'Brand New (Sealed Pack)' }}" are currently listed in {{ $activeCity }}.
            </p>
            <a href="{{ route('storefront', array_filter(['scope' => request('scope'), 'category' => request('category'), 'search' => request('search')])) }}#catalog" style="background: var(--vm-primary, #5E17EB); color: #FFFFFF; text-decoration: none; border-radius: 8px; padding: 10px 20px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                <span>Clear Condition Filter</span>
            </a>
        </div>
    @else
        <div style="grid-column: 1 / -1; text-align: center; padding: 48px 20px; background: #FFFFFF; border: 1px dashed var(--vm-border); border-radius: 16px;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: rgba(94, 23, 235, 0.08); color: var(--vm-primary, #5E17EB); display: flex; align-items: center; justify-content: center; margin: 0 auto 14px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
            <h4 style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0 0 6px;">No Direct Local Merchants in {{ $activeCity }} Yet</h4>
            <p style="font-size: 13px; color: #64748B; max-width: 420px; margin: 0 auto 16px; line-height: 1.5;">
                We are actively onboarding physical plaza vendors in {{ $activeCity }}. In the meantime, you can explore products delivering swiftly from our verified transit corridor.
            </p>
            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <a href="{{ route('storefront', ['scope' => 'all']) }}#catalog" style="background: var(--vm-primary, #5E17EB); color: #FFFFFF; border-radius: 8px; padding: 9px 18px; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    <span>View All Items Delivering to {{ $activeCity }}</span>
                </a>
                <button type="button" onclick="if(window.vmOpenLocationModal) window.vmOpenLocationModal();" style="background: #F8FAFC; border: 1px solid #CBD5E1; color: #334155; border-radius: 8px; padding: 9px 18px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <span>Switch Location</span>
                </button>
            </div>
        </div>
    @endif
@endforelse