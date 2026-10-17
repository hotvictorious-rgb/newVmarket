@extends('layouts.app')

@section('title', $document->title . ' — Victorious MARKET Legal')

@section('content')
<div class="vm-container" style="padding: 30px 16px 80px;">
    <!-- Breadcrumb -->
    <div style="font-size: 13px; color: #64748B; margin-bottom: 24px; display: flex; align-items: center; gap: 8px;">
        <a href="{{ route('storefront') }}" style="color: var(--vm-primary); text-decoration: none; font-weight: 600;">Storefront</a>
        <span>&rsaquo;</span>
        <span style="color: #64748B;">Legal &amp; Compliance</span>
        <span>&rsaquo;</span>
        <span style="color: #0F172A; font-weight: 700;">{{ $document->title }}</span>
    </div>

    <!-- Legal Header Banner -->
    <div style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border-radius: 16px; padding: 36px 32px; color: #FFFFFF; margin-bottom: 30px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.2); position: relative; overflow: hidden;">
        <div style="position: absolute; right: -20px; top: -20px; opacity: 0.08; font-size: 160px; pointer-events: none; user-select: none;">
            ⚖️
        </div>

        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; position: relative; z-index: 2;">
            <div>
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(234, 179, 8, 0.15); border: 1px solid rgba(234, 179, 8, 0.4); color: #FACC15; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; margin-bottom: 12px; letter-spacing: 0.5px;">
                    @if($document->category === 'about')
                        🏛️ INSTITUTIONAL CHARTER &amp; VISION
                    @elseif($document->category === 'support')
                        🎧 OMNICHANNEL SUPPORT &amp; HELP DESK
                    @elseif($document->category === 'faq')
                        💡 OPERATIONS KNOWLEDGE BASE &amp; FAQ
                    @elseif($document->category === 'returns' && $document->slug === 'cancellation-policy')
                        🛡️ ORDER CANCELLATION &amp; REVERSAL POLICY
                    @else
                        🛡️ CENTRALLY GOVERNED MARKETPLACE CONTRACT
                    @endif
                </div>
                <h1 style="font-family: 'Cinzel', serif, system-ui; font-size: 32px; font-weight: 800; margin: 0 0 10px; color: #FFFFFF; letter-spacing: -0.5px;">
                    {{ $document->title }}
                </h1>
                <p style="color: #94A3B8; font-size: 15px; margin: 0; max-width: 650px; line-height: 1.5;">
                    {{ $document->summary ?? 'Official institutional terms governing marketplace participation, payments, escrow protection, and legal accountability.' }}
                </p>
            </div>

            <!-- Print / Meta Badges -->
            <div style="display: flex; flex-direction: column; align-items: flex-start; sm:align-items: flex-end; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="background: rgba(255, 255, 255, 0.1); color: #FFFFFF; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; font-family: monospace;">
                        v{{ $document->current_version }}
                    </span>
                    <span style="background: rgba(16, 185, 129, 0.15); color: #34D399; border: 1px solid rgba(16, 185, 129, 0.3); padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;">
                        ● Legally Binding
                    </span>
                </div>
                <div style="font-size: 12px; color: #94A3B8;">
                    Effective: <strong>{{ $document->effective_date ? $document->effective_date->format('M d, Y') : 'Immediate' }}</strong>
                </div>
                <button type="button" onclick="window.print()" style="background: #FFFFFF; color: #0F172A; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s ease;">
                    🖨️ Print / Save PDF
                </button>
            </div>
        </div>

        <!-- Document Policy Tabs -->
        <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 28px; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 20px;">
            @foreach($allLegalDocs as $doc)
                @php
                    $tabUrl = match($doc->slug) {
                        'terms-of-service' => route('terms'),
                        'about-us' => route('about'),
                        'contact-and-help-center' => route('contact'),
                        'frequently-asked-questions' => route('faq'),
                        'cancellation-policy' => route('cancellation'),
                        default => route('legal.show', $doc->slug),
                    };
                @endphp
                <a href="{{ $tabUrl }}" 
                   style="padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.2s ease; {{ $doc->id === $document->id ? 'background: #3B82F6; color: #FFFFFF;' : 'background: rgba(255, 255, 255, 0.05); color: #CBD5E1;' }}">
                    {{ $doc->title }}
                </a>
            @endforeach
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

            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px; font-size: 12px; color: #64748B; line-height: 1.4;">
                <div style="font-weight: 700; color: #0F172A; margin-bottom: 4px;">
                    {{ in_array($document->category, ['about', 'support', 'faq']) ? 'Platform Headquarter' : 'Governing Law & Jurisdiction' }}
                </div>
                {{ in_array($document->category, ['about', 'support', 'faq']) 
                    ? 'Victorious Market Operations Desk, Uyo, Akwa Ibom State, Federal Republic of Nigeria.' 
                    : 'High Court of Akwa Ibom State, Federal Republic of Nigeria.' }}
            </div>
        </aside>

        <!-- Legal Prose Document Body -->
        <article class="legal-prose-container" style="background: #FFFFFF; border: 1px solid var(--vm-border); border-radius: 16px; padding: 48px; box-shadow: var(--vm-shadow-sm); line-height: 1.7; color: #1E293B;">
            {!! $currentVersion ? $currentVersion->rendered_html : '<p>No published version found.</p>' !!}
        </article>
    </div>
</div>

<style>
@media (max-width: 900px) {
    .legal-grid {
        grid-template-columns: 1fr !important;
    }
    aside {
        display: none !important;
    }
}

.legal-prose-container h1 {
    font-family: 'Cinzel', serif, system-ui;
    font-size: 26px;
    font-weight: 800;
    color: #0F172A;
    margin-top: 0;
    margin-bottom: 16px;
    border-bottom: 2px solid #E2E8F0;
    padding-bottom: 12px;
}

.legal-prose-container h2, 
.legal-prose-container h3 {
    font-size: 18px;
    font-weight: 700;
    color: #0F172A;
    margin-top: 32px;
    margin-bottom: 12px;
    scroll-margin-top: 100px;
}

.legal-prose-container p {
    margin-top: 0;
    margin-bottom: 16px;
    font-size: 15px;
}

.legal-prose-container ul, 
.legal-prose-container ol {
    margin-top: 0;
    margin-bottom: 18px;
    padding-left: 24px;
}

.legal-prose-container li {
    margin-bottom: 8px;
    font-size: 14.5px;
}

.legal-prose-container strong {
    color: #0F172A;
}

.legal-prose-container hr {
    border: none;
    border-top: 1px solid #E2E8F0;
    margin: 30px 0;
}

.legal-prose-container blockquote {
    border-left: 4px solid #3B82F6;
    background: #EFF6FF;
    padding: 12px 18px;
    border-radius: 0 8px 8px 0;
    margin: 20px 0;
    color: #1E3A8A;
    font-style: italic;
}

.toc-link {
    color: #475569;
    text-decoration: none;
    padding: 6px 10px;
    border-radius: 6px;
    transition: all 0.15s ease;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.toc-link:hover {
    background: #F1F5F9;
    color: #0F172A;
    font-weight: 600;
}

/* Bank-Grade Print Styling */
@media print {
    body {
        background: #FFFFFF !important;
        color: #000000 !important;
    }
    nav, header, footer, .vm-header, .vm-footer, aside, button, .toc-link, .legal-header-banner div:last-child {
        display: none !important;
    }
    .vm-container {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    .legal-grid {
        display: block !important;
    }
    .legal-prose-container {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const headings = document.querySelectorAll('.legal-prose-container h3, .legal-prose-container h2');
    const toc = document.getElementById('legalToc');
    
    if (headings.length > 0 && toc) {
        headings.forEach((heading, idx) => {
            const anchorId = 'section-' + idx;
            heading.id = anchorId;
            
            const link = document.createElement('a');
            link.href = '#' + anchorId;
            link.className = 'toc-link';
            link.textContent = heading.textContent.replace(/^[0-9]+\.\s*/, '');
            link.title = heading.textContent;
            
            toc.appendChild(link);
        });
    }
});
</script>
@endsection
