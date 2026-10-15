@extends('layouts.dashboard')

@section('title', 'Physical Plaza Outlets — Vendor')
@section('role_title', 'Merchant Commerce Suite')
@section('page_title', 'Physical Plaza Outlets')
@section('role_badge', 'VENDOR')
@section('role_badge_class', 'role-badge-vendor')

@section('sidebar_menu')
    @include('vendor.partials._sidebar')
@endsection

@section('content')
    <div class="breadcrumb-trail">
        <a href="{{ route('vendor.dashboard') }}">Merchant</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Physical Plaza Outlets</span>
    </div>

    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">🏬</span>
                <span>Physical Plaza Outlets &amp; Branches</span>
            </h1>
            <p class="page-header-subtitle">
                Registered physical plaza retail locations with staffed counters for in-person customer inspections and rider dispatches.
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <button class="btn-dash btn-dash-primary" onclick="document.getElementById('vmNewShopModal').style.display = 'flex';">
                + Register New Outlet
            </button>
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <div>
                <div class="dash-card-title">Registered Plaza Outlets ({{ count($shops) }})</div>
                <span class="dash-card-subtitle">Branch locations enabled for customer walk-in inspection</span>
            </div>
        </div>

        <div class="dash-table-responsive">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Branch Name &amp; Plaza</th>
                        <th>LGA Corridor</th>
                        <th>Contact Phone</th>
                        <th>Operating Hours</th>
                        <th>Inspection Terminal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shops as $shop)
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-text); font-size: 14px;">{{ $shop->name }}</div>
                                <span style="font-size: 12px; color: var(--adm-text-muted);">{{ $shop->address }}</span>
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-info">{{ $shop->lga->name ?? 'Uyo' }} LGA</span>
                            </td>
                            <td style="font-weight: 600; color: var(--adm-text);">
                                {{ $shop->contact_phone }}
                            </td>
                            <td style="font-size: 12.5px; color: var(--adm-text-muted);">
                                {{ $shop->operating_hours ?? 'Mon-Sat: 8:00 AM - 6:00 PM' }}
                            </td>
                            <td>
                                <span class="dash-badge dash-badge-success">● Counter Active</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--adm-text-muted);">
                                No physical branches registered yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal for New Shop -->
    <div id="vmNewShopModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; width: 100%; max-width: 520px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-size: 17px; font-weight: 700; color: var(--adm-text); margin: 0;">Register New Plaza Outlet</h3>
                <button type="button" onclick="document.getElementById('vmNewShopModal').style.display = 'none';" style="border: none; background: none; font-size: 18px; cursor: pointer;">&times;</button>
            </div>
            <form method="POST" action="{{ route('vendor.shops.store') }}">
                @csrf
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Branch / Plaza Name</label>
                    <input type="text" name="name" required class="form-control" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--adm-border); border-radius: 6px;" placeholder="e.g. Victor Tech Ibom Plaza Outlet">
                </div>
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Physical Street Address</label>
                    <input type="text" name="address" required class="form-control" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--adm-border); border-radius: 6px;" placeholder="e.g. Shop 14, Ibom Plaza Commercial Center">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">Contact Phone</label>
                        <input type="text" name="contact_phone" required class="form-control" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--adm-border); border-radius: 6px;" placeholder="08012345678">
                    </div>
                    <div>
                        <label style="display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 4px;">LGA Corridor</label>
                        <select name="lga_id" class="form-control" style="width: 100%; padding: 8px 12px; border: 1.5px solid var(--adm-border); border-radius: 6px;">
                            @foreach($allLgas as $lga)
                                <option value="{{ $lga->id }}">{{ $lga->name }} ({{ $lga->state->name ?? 'Akwa Ibom' }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="btn-dash btn-dash-outline" onclick="document.getElementById('vmNewShopModal').style.display = 'none';">Cancel</button>
                    <button type="submit" class="btn-dash btn-dash-primary">Register Outlet</button>
                </div>
            </form>
        </div>
    </div>
@endsection