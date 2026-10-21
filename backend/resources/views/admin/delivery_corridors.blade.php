@extends('layouts.dashboard')

@section('title', 'Uyo Delivery Corridors & Logistics Tariffs')
@section('role_title', 'Super Admin')
@section('page_title', 'Delivery Corridors & Company Tariffs')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@section('content')
<div style="max-width:1200px;margin:0 auto;padding:20px;">
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin-bottom:24px;">
        <h2 style="margin-top:0;">Customer delivery pricing (flat per order)</h2>
        <p>These are the customer-facing charges for delivery anywhere within Uyo: one flat base fee per order, plus a surcharge for each physical unit that cannot safely fit on a bike. These values do not determine what a logistics company earns.</p>
        <form method="POST" action="{{ route('admin.delivery_corridors.customer_fees.update') }}">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
                <label>Customer flat base fee per order (₦)<input required type="number" min="0" step="0.01" name="customer_base_fee" value="{{ $customerBaseFee }}" style="display:block;width:100%;padding:10px;"></label>
                <label>Customer bulky surcharge per physical unit (₦)<input required type="number" min="0" step="0.01" name="customer_bulky_surcharge_per_unit" value="{{ $customerBulkySurcharge }}" style="display:block;width:100%;padding:10px;"></label>
            </div>
            <button type="submit" style="margin-top:12px;padding:10px 16px;background:#1d4ed8;color:#fff;border:0;border-radius:8px;">Save customer pricing</button>
        </form>
    </div>
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin-bottom:24px;">
        <h2 style="margin-top:0;">Create Uyo customer-delivery corridor</h2>
        <p>Customer pricing is separate from every logistics company's negotiated payable. Create a corridor and configure recognised landmarks/aliases so checkout can match the customer's saved address. Unmatched or ambiguous addresses are blocked rather than guessed.</p>
        <form method="POST" action="{{ route('admin.delivery_corridors.store') }}">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <label>Corridor code<input required name="code" pattern="[a-z0-9][a-z0-9_-]*" maxlength="80" placeholder="uyo-plaza-to-aka-road" style="display:block;width:100%;padding:10px;"></label>
                <label>Display name<input required name="name" maxlength="160" placeholder="Plaza / Aka Road corridor" style="display:block;width:100%;padding:10px;"></label>
                <label>Minimum estimated hours<input required type="number" min="1" max="168" name="estimated_delivery_hours_min" value="2" style="display:block;width:100%;padding:10px;"></label>
                <label>Maximum estimated hours<input required type="number" min="1" max="168" name="estimated_delivery_hours_max" value="4" style="display:block;width:100%;padding:10px;"></label>
            </div>
            <input type="hidden" name="lga_id" value="{{ $uyo->id }}">
            <label style="display:block;margin-top:12px;">Recognised landmark aliases (one per line; include common spellings)<textarea required name="landmark_aliases_text" rows="4" style="display:block;width:100%;padding:10px;" placeholder="Plaza&#10;Ibom Plaza&#10;Uyo Plaza"></textarea></label>
            <label style="display:block;margin:12px 0;"><input type="checkbox" name="is_active" value="1"> Activate corridor for checkout after reviewing aliases and prices</label>
            <button type="submit" style="padding:10px 16px;background:#1d4ed8;color:#fff;border:0;border-radius:8px;">Create corridor</button>
        </form>
    </div>

    @forelse($corridors as $corridor)
        <section style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">{{ $corridor->name }}</h2>
                    <p style="margin:6px 0;color:#64748b;">{{ $corridor->code }} · Uyo · Landmark-matched route used for the selected logistics company's payable tariff.</p>
                    <p style="margin:6px 0;"><strong>Status:</strong> {{ $corridor->is_active ? 'Active' : 'Inactive' }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.delivery_corridors.update', $corridor->id) }}" style="border-top:1px solid #e2e8f0;padding-top:16px;">
                @csrf
                @method('PUT')
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                    <label>Name<input required name="name" value="{{ $corridor->name }}" style="display:block;width:100%;padding:9px;"></label>
                    <label>Minimum hours<input required type="number" min="1" max="168" name="estimated_delivery_hours_min" value="{{ $corridor->estimated_delivery_hours_min }}" style="display:block;width:100%;padding:9px;"></label>
                    <label>Maximum hours<input required type="number" min="1" max="168" name="estimated_delivery_hours_max" value="{{ $corridor->estimated_delivery_hours_max }}" style="display:block;width:100%;padding:9px;"></label>
                </div>
                <label style="display:block;margin-top:12px;">Landmark aliases<textarea required name="landmark_aliases_text" rows="3" style="display:block;width:100%;padding:9px;">{{ implode("\n", (array)$corridor->landmark_aliases) }}</textarea></label>
                <label style="display:block;margin:12px 0;"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" {{ $corridor->is_active ? 'checked' : '' }}> Active</label>
                <button type="submit" style="padding:9px 14px;background:#1d4ed8;color:#fff;border:0;border-radius:8px;">Save customer corridor</button>
            </form>

            <h3 style="margin-top:24px;">Independent logistics-company payables</h3>
            <p>These amounts are paid to the logistics company, not its rider. They do not derive from the customer delivery fee.</p>
            @foreach($companies as $company)
                @php($rate = $corridor->companyRates->firstWhere('logistics_company_id', $company->id))
                <form method="POST" action="{{ route('admin.delivery_corridors.company_rates.upsert', $corridor->id) }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;align-items:end;border-top:1px solid #e2e8f0;padding:12px 0;">
                    @csrf
                    <input type="hidden" name="logistics_company_id" value="{{ $company->id }}">
                    <div><strong>{{ $company->company_name }}</strong><div style="color:#64748b;font-size:12px;">{{ $rate ? 'Tariff version '.$rate->tariff_version : 'No tariff configured' }}</div></div>
                    <label>Company base payable (₦)<input required type="number" min="0.01" step="0.01" name="base_payable" value="{{ $rate?->base_payable ?? '' }}" style="display:block;width:100%;padding:9px;"></label>
                    <label>Company bulky surcharge per unit (₦)<input required type="number" min="0" step="0.01" name="bulky_surcharge_per_unit" value="{{ $rate?->bulky_surcharge_per_unit ?? '0.00' }}" style="display:block;width:100%;padding:9px;"></label>
                    <label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" {{ !$rate || $rate->is_active ? 'checked' : '' }}> Active rate</label>
                    <button type="submit" style="padding:9px 12px;background:#0f766e;color:#fff;border:0;border-radius:8px;">Save company tariff</button>
                </form>
            @endforeach
        </section>
    @empty
        <p>No corridors configured yet. Create and activate a corridor before customer checkout can match addresses.</p>
    @endforelse
</div>
@endsection
