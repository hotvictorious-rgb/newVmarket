@extends('layouts.dashboard')

@section('title', 'Supplier Catalog & Admin Listings')
@section('role_title', 'Super Admin')
@section('page_title', 'Supplier Catalog')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@section('content')
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">Supplier Catalog &amp; Admin Listings</h1>
            <p class="page-header-subtitle">One public Victorious MARKET product can have multiple private supplier offers. Supplier offers stay unavailable until an admin records current availability evidence.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('admin.products.index') }}" class="btn-dash btn-dash-outline">Legacy Product Catalog</a>
            <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline">Command Tower</a>
        </div>
    </div>

    @if(session('success'))
        <div role="status" style="background:#ECFDF5;border:1px solid #10B981;color:#065F46;border-radius:10px;padding:12px 16px;margin-bottom:18px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" style="background:#FEF2F2;border:1px solid #FCA5A5;color:#991B1B;border-radius:10px;padding:12px 16px;margin-bottom:18px;">
            <strong>Action not completed.</strong>
            <ul style="margin:6px 0 0 18px;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px;margin-bottom:24px;">
        <section style="background:#fff;border:1px solid var(--adm-border);border-radius:12px;padding:18px;">
            <h2 style="font-size:17px;margin:0 0 6px;">Create canonical product</h2>
            <p style="font-size:12px;color:#64748B;margin:0 0 14px;">Creates an unpublished Victorious MARKET draft without assigning supplier ownership. Search first to avoid duplicates.</p>
            <form method="POST" action="{{ route('admin.supplier-catalog.products.store') }}" style="display:grid;gap:10px;">
                @csrf
                <label>Public product name
                    <input name="name" value="{{ old('name') }}" required maxlength="180" class="form-control" placeholder="Brand, model and exact variant">
                </label>
                <label>Category
                    <select name="category_id" required class="form-control">
                        <option value="">Select category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Retail price (₦)
                    <input type="number" name="unit_price" value="{{ old('unit_price') }}" required min="1" step="0.01" max="100000000" class="form-control">
                </label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <label>Condition
                        <select name="condition" required class="form-control">
                            <option value="brand_new" @selected(old('condition','brand_new') === 'brand_new')>Brand new</option>
                            <option value="uk_used" @selected(old('condition') === 'uk_used')>UK used</option>
                        </select>
                    </label>
                    <label>Delivery class
                        <select name="package_tier" required class="form-control">
                            <option value="bike" @selected(old('package_tier','bike') === 'bike')>Bike</option>
                            <option value="standard" @selected(old('package_tier') === 'standard')>Standard</option>
                            <option value="keke" @selected(old('package_tier') === 'keke')>Keke / bulky</option>
                            <option value="bulky" @selected(old('package_tier') === 'bulky')>Bulky</option>
                            <option value="fragile" @selected(old('package_tier') === 'fragile')>Fragile</option>
                        </select>
                    </label>
                </div>
                <label>Thumbnail URL or storage path (optional)
                    <input name="thumbnail" value="{{ old('thumbnail') }}" maxlength="2048" class="form-control" placeholder="/storage/products/example.jpg">
                </label>
                <label>Description
                    <textarea name="description" rows="3" maxlength="10000" class="form-control">{{ old('description') }}</textarea>
                </label>
                <button type="submit" class="btn-dash btn-dash-primary">Create unpublished draft</button>
            </form>
        </section>

        <section style="background:#fff;border:1px solid var(--adm-border);border-radius:12px;padding:18px;">
            <h2 style="font-size:17px;margin:0 0 6px;">Catalog rules</h2>
            <ul style="font-size:13px;color:#475569;line-height:1.65;padding-left:18px;margin-top:10px;">
                <li>Search for an existing exact product before creating a new one.</li>
                <li>Attach suppliers as private offers, not as public sellers.</li>
                <li>New or restored offers start unavailable until confirmed.</li>
                <li>Manual delisting overrides supplier availability.</li>
                <li>Disabling an offer affects new sourcing only; paid order snapshots are not rewritten.</li>
                <li>Wholesale cost and supplier identity are shown only in this admin screen.</li>
            </ul>
        </section>
    </div>

    <section style="background:#fff;border:1px solid var(--adm-border);border-radius:12px;padding:16px;margin-bottom:18px;">
        <form method="GET" action="{{ route('admin.supplier-catalog.index') }}" style="display:grid;grid-template-columns:minmax(180px,1fr) 180px auto;gap:10px;align-items:end;">
            <label>Search canonical catalog
                <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Product name or slug">
            </label>
            <label>Listing state
                <select name="listing_status" class="form-control">
                    <option value="">All states</option>
                    @foreach(['listed'=>'Listed','unlisted'=>'Draft / unlisted','delisted'=>'Manually delisted'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('listing_status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="btn-dash btn-dash-primary">Search</button>
        </form>
    </section>

    @forelse($products as $product)
        <section style="background:#fff;border:1px solid var(--adm-border);border-radius:12px;padding:18px;margin-bottom:18px;">
            <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:flex-start;">
                <div style="display:flex;gap:12px;min-width:0;flex:1;">
                    @if($product->thumbnail)
                        <img src="{{ $product->thumbnail }}" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid #E2E8F0;">
                    @endif
                    <div>
                        <h2 style="font-size:17px;margin:0 0 4px;">{{ $product->name }}</h2>
                        <div style="font-size:12px;color:#64748B;">Product #{{ $product->id }} · {{ $product->category->name ?? 'Uncategorized' }} · {{ $product->condition ?? 'condition not set' }} · {{ $product->package_tier ?? 'delivery class not set' }}</div>
                        <div style="font-size:14px;font-weight:700;margin-top:6px;">Retail price: ₦{{ number_format((float)$product->unit_price, 2) }}</div>
                        <div style="font-size:12px;margin-top:4px;">Listing: <strong>{{ $product->listing_status }}</strong> · Approval: <strong>{{ $product->is_approved ? 'approved' : 'not approved' }}</strong></div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <form method="POST" action="{{ route('admin.supplier-catalog.products.listing', $product->id) }}">
                        @csrf
                        <input type="hidden" name="action" value="{{ $product->listing_status === 'listed' ? 'delist' : 'list' }}">
                        <button type="submit" class="btn-dash {{ $product->listing_status === 'listed' ? 'btn-dash-outline' : 'btn-dash-primary' }}" onclick="return confirm('{{ $product->listing_status === 'listed' ? 'Manually delist' : 'Approve and list' }} this product?')">{{ $product->listing_status === 'listed' ? 'Manually delist' : 'Approve / list' }}</button>
                    </form>
                    <a href="{{ route('product.detail', $product->slug) }}" target="_blank" rel="noopener" class="btn-dash btn-dash-outline">Public preview</a>
                </div>
            </div>

            <div style="margin-top:18px;border-top:1px solid #E2E8F0;padding-top:14px;">
                <h3 style="font-size:14px;margin:0 0 10px;">Private supplier offers ({{ $product->supplierOffers->count() }})</h3>
                @forelse($product->supplierOffers as $offer)
                    <div style="border:1px solid #E2E8F0;border-radius:9px;padding:12px;margin-bottom:10px;">
                        <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                            <div>
                                <strong>{{ $offer->vendor->business_name ?? 'Supplier record unavailable' }}</strong> — {{ $offer->shop->name ?? 'Shop unavailable' }}
                                <div style="font-size:12px;color:#64748B;margin-top:3px;">Offer #{{ $offer->id }} · {{ $offer->shop->lga->name ?? 'LGA unknown' }} · Wholesale ₦{{ number_format((float)$offer->wholesale_cost, 2) }}</div>
                                <div style="font-size:12px;margin-top:4px;">
                                    State:
                                    @if($offer->trashed())
                                        <strong>retired</strong>
                                    @elseif(!$offer->is_available)
                                        <strong>unavailable / disabled</strong>
                                    @elseif(!$offer->availability_confirmed_at || !$offer->availability_expires_at || !$offer->availability_expires_at->isFuture())
                                        <strong>confirmation missing or expired</strong>
                                    @else
                                        <strong>availability confirmed until {{ $offer->availability_expires_at->format('d M Y, H:i') }}</strong>
                                    @endif
                                </div>
                            </div>
                            @if(!$offer->trashed())
                                <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                                    <form method="POST" action="{{ route('admin.supplier-catalog.offers.cost', $offer->id) }}" style="display:flex;gap:6px;align-items:center;">
                                        @csrf @method('PATCH')
                                        <input type="number" name="wholesale_cost" min="0.01" step="0.01" max="100000000" required value="{{ $offer->wholesale_cost }}" aria-label="Wholesale cost" style="width:120px;" class="form-control">
                                        <button type="submit" class="btn-dash btn-dash-outline">Update cost</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.supplier_offers.confirm_availability', $offer->id) }}" style="display:grid;grid-template-columns:minmax(190px,1fr);gap:6px;max-width:280px;">
                                        @csrf
                                        <label style="font-size:11px;">Confirm until
                                            <input type="datetime-local" name="availability_expires_at" required class="form-control">
                                        </label>
                                        <input name="notes" maxlength="500" class="form-control" placeholder="Confirmation source / notes (optional)">
                                        <button type="submit" class="btn-dash btn-dash-primary">Confirm availability</button>
                                    </form>
                                    @if($offer->is_available)
                                        <form method="POST" action="{{ route('admin.supplier-catalog.offers.disable', $offer->id) }}" style="display:grid;gap:6px;max-width:220px;">
                                            @csrf
                                            <input name="reason" required minlength="5" maxlength="500" class="form-control" placeholder="Reason to disable">
                                            <button type="submit" class="btn-dash btn-dash-outline" onclick="return confirm('Disable this offer for new allocations?')">Disable offer</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p style="font-size:12px;color:#64748B;">No supplier offers are attached. The product cannot be purchased until an eligible offer is added and confirmed.</p>
                @endforelse

                <form method="POST" action="{{ route('admin.supplier-catalog.offers.store', $product->id) }}" style="display:grid;grid-template-columns:minmax(220px,1fr) 180px auto;gap:10px;align-items:end;margin-top:12px;background:#F8FAFC;border-radius:9px;padding:12px;">
                    @csrf
                    <label>Approved Uyo supplier shop
                        <select name="shop_id" required class="form-control">
                            <option value="">Select private supplier</option>
                            @foreach($shops as $shop)
                                <option value="{{ $shop->id }}">{{ $shop->vendor->business_name ?? 'Supplier' }} — {{ $shop->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Wholesale cost (₦)
                        <input type="number" name="wholesale_cost" required min="0.01" step="0.01" max="100000000" class="form-control">
                    </label>
                    <button type="submit" class="btn-dash btn-dash-primary">Attach unconfirmed offer</button>
                </form>
            </div>
        </section>
    @empty
        <div style="background:#fff;border:1px dashed #CBD5E1;border-radius:12px;padding:24px;text-align:center;color:#64748B;">No catalog products match this search. Search the canonical catalog before creating a new product.</div>
    @endforelse

    <div style="margin-top:18px;">{{ $products->links() }}</div>
@endsection
