@extends('layouts.app')

@section('title', 'Verified Delivery Addresses — Victorious MARKET')

@section('content')
<div class="container customer-profile-wrapper">
    <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--vm-text-muted); margin-bottom: 22px;">
        <a href="{{ route('storefront') }}" style="color: var(--vm-text-muted); text-decoration: none;">Storefront</a>
        <span>/</span>
        <a href="{{ route('customer.dashboard') }}" style="color: var(--vm-text-muted); text-decoration: none;">Account</a>
        <span>/</span>
        <span style="color: var(--vm-primary); font-weight: 700;">My Addresses</span>
    </div>

    <div class="profile-layout-grid">
        @include('customer.partials._sidebar')

        <main class="profile-canvas">
            <div class="page-header-row" style="margin-bottom: 20px;">
                <div>
                    <h1 class="page-header-title" style="font-size: 22px; font-weight: 800; color: var(--vm-dark); display: flex; align-items: center; gap: 10px;">
                        <span>📍</span>
                        <span>Verified Delivery Addresses</span>
                    </h1>
                    <p style="font-size: 13.5px; color: var(--vm-text-muted); margin-top: 4px;">
                        Manage your doorstep delivery destinations mapped to certified Akwa Ibom LGA corridors.
                    </p>
                </div>
            </div>

            <div class="legacy-card">
                <h3 class="legacy-card-title">Saved Delivery Addresses</h3>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-top: 14px;">
                    <div style="background: #F8FAFC; border: 1.5px solid var(--vm-primary); border-radius: 12px; padding: 18px; position: relative;">
                        <span class="badge" style="position: absolute; top: 14px; right: 14px; background: var(--vm-primary); color: white; font-size: 10.5px; font-weight: 700; padding: 3px 8px; border-radius: 999px;">
                            DEFAULT HOME
                        </span>
                        <div style="font-size: 15px; font-weight: 700; color: var(--vm-dark);">Emeka Okonkwo</div>
                        <div style="font-size: 13.5px; color: var(--vm-text-muted); margin-top: 4px;">14 Oron Road, Uyo, Akwa Ibom State</div>
                        <div style="font-size: 12.5px; font-weight: 600; color: var(--vm-primary); margin-top: 6px;">📞 08012345678</div>
                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--vm-border); font-size: 12px; color: var(--vm-text-muted);">
                            Corridor: <strong>Uyo Municipal (Intra-LGA)</strong>
                        </div>
                    </div>

                    <div style="background: #F8FAFC; border: 1.5px dashed var(--vm-border); border-radius: 12px; padding: 18px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 140px; text-align: center;">
                        <span style="font-size: 28px; margin-bottom: 6px;">➕</span>
                        <div style="font-size: 14px; font-weight: 700; color: var(--vm-dark);">Add New Delivery Address</div>
                        <div style="font-size: 12px; color: var(--vm-text-muted); margin-top: 2px;">Map to Akwa Ibom LGA Corridors</div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
@endsection