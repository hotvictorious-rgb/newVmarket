@extends('layouts.dashboard')

@section('title', 'Revision History: ' . $document->title)
@section('role_title', 'Super Admin')
@section('page_title', 'Policy Audit History')
@section('role_badge', 'SUPER ADMIN')
@section('role_badge_class', 'role-badge-super-admin')

@section('sidebar_menu')
    @include('admin.partials._sidebar')
@endsection

@section('content')
    <!-- Page Header -->
    <div class="page-header-row">
        <div>
            <h1 class="page-header-title">
                <span style="font-size: 26px;">📜</span>
                <span>Immutable Revision Trail: {{ $document->title }}</span>
            </h1>
            <p class="page-header-subtitle">
                Cryptographic and administrative audit history for slug: <code>{{ $document->slug }}</code> &bull; Total Revisions: <strong>{{ $document->versions->count() }}</strong>
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.legal.index') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Legal Desk
            </a>
            <a href="{{ route('admin.legal.edit', $document->id) }}" class="btn-dash btn-dash-primary" style="font-size: 12px; padding: 6px 12px;">
                ✏️ Create New Revision
            </a>
        </div>
    </div>

    <!-- Timeline of Versions -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        @foreach($document->versions as $version)
            <div class="dash-card" style="{{ $version->version === $document->current_version ? 'border: 2px solid #3B82F6;' : '' }}">
                <div class="dash-card-header" style="background: {{ $version->version === $document->current_version ? 'rgba(59, 130, 246, 0.05)' : '#F8FAFC' }};">
                    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; flex-wrap: wrap; gap: 12px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="background: #1E293B; color: #FFFFFF; font-family: monospace; font-size: 14px; font-weight: 800; padding: 4px 12px; border-radius: 6px;">
                                v{{ $version->version }}
                            </span>
                            @if($version->version === $document->current_version)
                                <span style="background: #ECFDF5; color: #059669; border: 1px solid #10B981; font-size: 11px; font-weight: 800; padding: 3px 8px; border-radius: 999px;">
                                    ● CURRENT ACTIVE STOREFRONT VERSION
                                </span>
                            @endif
                        </div>

                        <div style="font-size: 12px; color: #64748B; display: flex; align-items: center; gap: 16px;">
                            <span>Effective: <strong>{{ $version->effective_date ? $version->effective_date->format('M d, Y') : 'N/A' }}</strong></span>
                            <span>Published: <strong>{{ $version->created_at->format('M d, Y H:i:s') }}</strong></span>
                        </div>
                    </div>
                </div>

                <div style="padding: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 16px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px; font-size: 12.5px;">
                        <div>
                            <div style="color: #64748B; font-size: 11px; text-transform: uppercase; font-weight: 700;">Published By</div>
                            <div style="color: #0F172A; font-weight: 600; margin-top: 2px;">
                                {{ $version->publisher->name ?? 'System Seeder / Foundational Charter' }}
                            </div>
                        </div>

                        <div>
                            <div style="color: #64748B; font-size: 11px; text-transform: uppercase; font-weight: 700;">IP Address</div>
                            <div style="color: #0F172A; font-family: monospace; margin-top: 2px;">
                                {{ $version->ip_address ?? '127.0.0.1' }}
                            </div>
                        </div>

                        <div>
                            <div style="color: #64748B; font-size: 11px; text-transform: uppercase; font-weight: 700;">Change Summary</div>
                            <div style="color: #0F172A; font-weight: 500; margin-top: 2px;">
                                {{ $version->summary_of_changes ?? 'No notes provided' }}
                            </div>
                        </div>
                    </div>

                    <!-- Collapsible Content Accordion -->
                    <details style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px 16px;">
                        <summary style="font-size: 13px; font-weight: 700; color: #3B82F6; cursor: pointer; user-select: none;">
                            👁️ View Full Historical Legal Prose ({{ strlen($version->content) }} characters)
                        </summary>
                        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #E2E8F0; max-height: 400px; overflow-y: auto; font-size: 13px; line-height: 1.6; color: #334155;">
                            {!! $version->rendered_html !!}
                        </div>
                    </details>
                </div>
            </div>
        @endforeach
    </div>
@endsection
