@extends('layouts.dashboard')

@section('title', 'Legal & Policy Governance Desk — Victorious MARKET')
@section('role_title', 'Super Admin')
@section('page_title', 'Legal & Policy CMS')
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
                <span>Bank-Grade Legal &amp; Policy Governance Desk</span>
            </h1>
            <p class="page-header-subtitle">
                Centrally manage marketplace terms, return policies, and privacy disclosures with immutable audit versioning and instant public sync.
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.dashboard') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Command Tower
            </a>
            <a href="{{ route('terms') }}" target="_blank" class="btn-dash btn-dash-primary" style="font-size: 12px; padding: 6px 14px;">
                View Live Terms 🌐
            </a>
        </div>
    </div>

    @if(session('status'))
        <div style="background: #ECFDF5; border: 1px solid #10B981; color: #065F46; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
            <span>✅</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    <!-- Legal Documents Overview Grid -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">
                <span style="font-size: 18px;">⚖️</span>
                <div>
                    <span style="font-weight: 800; color: var(--adm-text);">Active Marketplace Policies &amp; Contracts</span>
                    <div class="dash-card-subtitle">
                        Every modification generates an immutable audit revision with admin ID, timestamp, and client IP tracking.
                    </div>
                </div>
            </div>
            <span class="dash-badge dash-badge-green">Immutable Versioning Active</span>
        </div>

        <div style="padding: 20px; overflow-x: auto;">
            <table class="dash-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid #E2E8F0; text-align: left; font-size: 12px; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px;">
                        <th style="padding: 12px 16px;">Document Title</th>
                        <th style="padding: 12px 16px;">Category / Slug</th>
                        <th style="padding: 12px 16px;">Active Version</th>
                        <th style="padding: 12px 16px;">Effective Date</th>
                        <th style="padding: 12px 16px;">Revisions</th>
                        <th style="padding: 12px 16px;">Status</th>
                        <th style="padding: 12px 16px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($documents as $doc)
                        <tr style="border-bottom: 1px solid #F1F5F9; font-size: 14px;">
                            <td style="padding: 16px;">
                                <div style="font-weight: 700; color: #0F172A; font-size: 15px;">
                                    {{ $doc->title }}
                                </div>
                                <div style="font-size: 12px; color: #64748B; margin-top: 2px;">
                                    {{ Str::limit($doc->summary, 60) }}
                                </div>
                            </td>
                            <td style="padding: 16px;">
                                <code style="background: #F1F5F9; color: #475569; padding: 3px 8px; border-radius: 4px; font-size: 12px;">
                                    {{ $doc->slug }}
                                </code>
                            </td>
                            <td style="padding: 16px;">
                                <span style="background: #EFF6FF; color: #1D4ED8; font-weight: 700; font-family: monospace; padding: 4px 10px; border-radius: 6px; font-size: 12px; border: 1px solid #BFDBFE;">
                                    v{{ $doc->current_version }}
                                </span>
                                @if($doc->has_draft)
                                    <div style="margin-top: 4px;">
                                        <span style="background: #FEF3C7; color: #B45309; font-weight: 700; font-family: monospace; padding: 2px 6px; border-radius: 4px; font-size: 11px; border: 1px solid #FCD34D;">
                                            📝 Draft v{{ $doc->draft_version ?? $doc->current_version }}
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td style="padding: 16px; color: #334155; font-size: 13px;">
                                {{ $doc->effective_date ? $doc->effective_date->format('M d, Y') : 'N/A' }}
                            </td>
                            <td style="padding: 16px;">
                                <a href="{{ route('admin.legal.history', $doc->id) }}" style="color: #6366F1; text-decoration: none; font-weight: 600; font-size: 13px;">
                                    {{ $doc->versions_count }} {{ Str::plural('revision', $doc->versions_count) }} &rsaquo;
                                </a>
                            </td>
                            <td style="padding: 16px;">
                                @if($doc->is_published)
                                    <span style="background: #ECFDF5; color: #059669; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 999px; border: 1px solid #A7F3D0;">
                                        ● Live
                                    </span>
                                @else
                                    <span style="background: #FEF3C7; color: #D97706; font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 999px;">
                                        ○ Hidden
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 16px; text-align: right;">
                                <div style="display: inline-flex; gap: 6px; align-items: center;">
                                    <a href="{{ route('admin.legal.edit', $doc->id) }}" class="btn-dash btn-dash-primary" style="font-size: 12px; padding: 6px 12px;">
                                        {{ $doc->has_draft ? '✏️ Edit Draft' : '✏️ Revise' }}
                                    </a>
                                    @if($doc->has_draft)
                                        <a href="{{ route('admin.legal.preview_draft', $doc->id) }}" target="_blank" class="btn-dash" style="background: #F59E0B; color: #FFFFFF; font-size: 11.5px; padding: 6px 10px; font-weight: 700;" title="Preview working draft in storefront layout">
                                            👁️ Preview Draft
                                        </a>
                                    @endif
                                    <a href="{{ $doc->slug === 'terms-of-service' ? route('terms') : route('legal.show', $doc->slug) }}" target="_blank" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 10px;" title="View public live page">
                                        🌐 Live
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
