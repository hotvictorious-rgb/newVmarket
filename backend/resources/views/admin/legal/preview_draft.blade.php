@extends('layouts.app')

@section('title', '[DRAFT PREVIEW] ' . ($document->draft_title ?? $document->title) . ' — Victorious MARKET')

@section('content')
<!-- Super Admin Draft Notice Ribbon -->
<div style="background: linear-gradient(90deg, #D97706 0%, #B45309 100%); color: #FFFFFF; padding: 12px 24px; font-size: 13.5px; position: sticky; top: 0; z-index: 9999; box-shadow: 0 4px 14px rgba(180, 83, 9, 0.35);">
    <div class="vm-container" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 0;">
        <div style="display: flex; align-items: center; gap: 10px; font-weight: 700;">
            <span style="font-size: 20px;">🟡</span>
            <span>SUPER ADMIN DRAFT PREVIEW &bull; Target Version: <strong>v{{ $document->draft_version ?? $document->current_version }}</strong> &bull; Public Live Version: <strong>v{{ $document->current_version }}</strong></span>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="{{ route('admin.legal.edit', $document->id) }}" style="background: #FFFFFF; color: #92400E; padding: 6px 14px; border-radius: 6px; font-weight: 800; font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <span>✏️</span>
                <span>Return to Editor</span>
            </a>
            <a href="{{ route('admin.legal.index') }}" style="background: rgba(255, 255, 255, 0.2); color: #FFFFFF; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 12px; text-decoration: none;">
                Close Preview
            </a>
        </div>
    </div>
</div>

<div class="vm-container" style="padding: 30px 16px 80px;">
    <!-- Breadcrumb -->
    <div style="font-size: 13px; color: #64748B; margin-bottom: 24px; display: flex; align-items: center; gap: 8px;">
        <a href="{{ route('admin.legal.index') }}" style="color: var(--vm-primary); text-decoration: none; font-weight: 600;">Admin Legal Desk</a>
        <span>&rsaquo;</span>
        <span style="color: #D97706; font-weight: 700;">[Draft Preview]</span>
        <span>&rsaquo;</span>
        <span style="color: #0F172A; font-weight: 700;">{{ $document->draft_title ?? $document->title }}</span>
    </div>

    <!-- Legal Header Banner -->
    <div style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border-radius: 16px; padding: 36px 32px; color: #FFFFFF; margin-bottom: 30px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2); position: relative; overflow: hidden; border: 2px dashed #F59E0B;">
        <div style="position: absolute; right: -20px; top: -20px; opacity: 0.08; font-size: 160px; pointer-events: none; user-select: none;">
            ⚖️
        </div>

        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; position: relative; z-index: 2;">
            <div>
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(245, 158, 11, 0.2); border: 1px solid rgba(245, 158, 11, 0.6); color: #FBBF24; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 800; margin-bottom: 12px; letter-spacing: 0.5px;">
                    📝 DRAFT PREVIEW (UNPUBLISHED)
                </div>
                <h1 style="font-family: 'Cinzel', serif, system-ui; font-size: 32px; font-weight: 800; margin: 0 0 10px; color: #FFFFFF; letter-spacing: -0.5px;">
                    {{ $document->draft_title ?? $document->title }}
                </h1>
                <p style="color: #94A3B8; font-size: 15px; margin: 0; max-width: 650px; line-height: 1.5;">
                    {{ $document->draft_summary ?? ($document->summary ?? 'Official institutional terms governing marketplace participation, payments, escrow protection, and legal accountability.') }}
                </p>
            </div>

            <!-- Print / Meta Badges -->
            <div style="display: flex; flex-direction: column; align-items: flex-start; sm:align-items: flex-end; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="background: rgba(245, 158, 11, 0.2); color: #FBBF24; border: 1px solid rgba(245, 158, 11, 0.4); padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 800; font-family: monospace;">
                        v{{ $document->draft_version ?? $document->current_version }} (Draft)
                    </span>
                    <span style="background: rgba(239, 68, 68, 0.15); color: #F87171; border: 1px solid rgba(239, 68, 68, 0.3); padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700;">
                        ○ Not Published
                    </span>
                </div>
                <div style="font-size: 12px; color: #94A3B8;">
                    Proposed Effective: <strong>{{ $document->draft_effective_date ? $document->draft_effective_date->format('M d, Y') : ($document->effective_date ? $document->effective_date->format('M d, Y') : 'Immediate') }}</strong>
                </div>
                <button type="button" onclick="window.print()" style="background: #FFFFFF; color: #0F172A; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s ease;">
                    🖨️ Print / Save PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content Layout -->
    <div style="display: grid; grid-template-columns: 280px 1fr; gap: 32px; align-items: start;" class="legal-grid">
        
        <!-- Sidebar Navigation & Quick Jump -->
        <aside style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 12px; padding: 24px; position: sticky; top: 100px; box-shadow: var(--vm-shadow-sm);">
            <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #94A3B8; margin-bottom: 14px;">
                On This Document
            </div>

            <div id="legalToc" style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; max-height: calc(100vh - 240px); overflow-y: auto;">
                <!-- Filled via JS from headings -->
            </div>

            <hr style="border: none; border-top: 1px solid #E2E8F0; margin: 20px 0 16px;">

            <div style="background: #FFFBEB; border: 1px solid #FCD34D; border-radius: 8px; padding: 12px; font-size: 12px; color: #92400E; line-height: 1.4;">
                <div style="font-weight: 800; margin-bottom: 4px;">Draft Revision Status</div>
                Last edited: {{ $document->draft_updated_at ? $document->draft_updated_at->diffForHumans() : 'Recently' }}.
                Changes are saved safely as a working draft.
            </div>
        </aside>

        <!-- Legal Prose Document Body -->
        <article class="legal-prose-container" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 16px; padding: 48px; box-shadow: var(--vm-shadow-sm); line-height: 1.7; color: #1E293B;">
            {!! $currentVersion ? $currentVersion->rendered_html : '<p>No draft content entered.</p>' !!}
        </article>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const prose = document.querySelector('.legal-prose-container');
    const toc = document.getElementById('legalToc');
    if (!prose || !toc) return;

    const headings = prose.querySelectorAll('h1, h2, h3');
    if (headings.length === 0) {
        toc.innerHTML = '<span style="color:#94A3B8;font-size:12px;">No section headings found.</span>';
        return;
    }

    headings.forEach((h, idx) => {
        const id = 'sec-' + idx;
        h.id = id;
        const link = document.createElement('a');
        link.href = '#' + id;
        link.textContent = h.textContent.trim();
        link.style.display = 'block';
        link.style.color = '#475569';
        link.style.textDecoration = 'none';
        link.style.padding = '4px 8px';
        link.style.borderRadius = '6px';
        link.style.transition = 'all 0.2s ease';
        link.style.fontSize = h.tagName === 'H1' ? '13px' : (h.tagName === 'H2' ? '12.5px' : '12px');
        link.style.fontWeight = h.tagName === 'H1' ? '700' : '500';
        link.style.paddingLeft = h.tagName === 'H3' ? '16px' : (h.tagName === 'H2' ? '10px' : '6px');

        link.addEventListener('mouseenter', () => {
            link.style.background = '#FEF3C7';
            link.style.color = '#B45309';
        });
        link.addEventListener('mouseleave', () => {
            link.style.background = 'transparent';
            link.style.color = '#475569';
        });

        toc.appendChild(link);
    });
});
</script>
@endsection
