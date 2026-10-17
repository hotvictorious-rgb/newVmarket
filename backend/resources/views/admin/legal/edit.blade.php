@extends('layouts.dashboard')

@section('title', 'Revise Legal Policy: ' . $document->title)
@section('role_title', 'Super Admin')
@section('page_title', 'Edit Legal Document')
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
                <span style="font-size: 26px;">✏️</span>
                <span>Revise Policy: {{ $document->title }}</span>
            </h1>
            <p class="page-header-subtitle">
                Slug: <code>{{ $document->slug }}</code> &bull; Live Public Version: <strong>v{{ $document->current_version }}</strong>
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.legal.index') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Policies
            </a>
            @if($document->has_draft)
                <a href="{{ route('admin.legal.preview_draft', $document->id) }}" target="_blank" class="btn-dash" style="background: #F59E0B; color: #FFFFFF; font-size: 12px; padding: 6px 12px; font-weight: 700;">
                    👁️ Full Draft Page Preview ↗
                </a>
            @endif
            <a href="{{ route('admin.legal.history', $document->id) }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                📜 View {{ $document->versions->count() }} Past Revisions
            </a>
        </div>
    </div>

    @if(session('status'))
        <div style="background: #ECFDF5; border: 1px solid #10B981; color: #065F46; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
            <span>✅</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div style="background: #FEF2F2; border: 1px solid #EF4444; color: #991B1B; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
            <span>⚠️</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($document->has_draft)
        <!-- Working Draft Active Banner -->
        <div style="background: #FFFBEB; border: 1.5px solid #FCD34D; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.1);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 24px;">📝</span>
                <div>
                    <strong style="color: #92400E; font-size: 14px; display: block;">
                        Unpublished Working Draft Active (v{{ $document->draft_version ?? $document->current_version }})
                    </strong>
                    <span style="font-size: 12.5px; color: #B45309;">
                        Saved {{ $document->draft_updated_at ? $document->draft_updated_at->diffForHumans() : 'recently' }}. Live public users and mobile apps are still served <strong>v{{ $document->current_version }}</strong>.
                    </span>
                </div>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="{{ route('admin.legal.preview_draft', $document->id) }}" target="_blank" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px; border-color: #F59E0B; color: #B45309; font-weight: 700;">
                    👁️ Full Page Draft
                </a>
                <form method="POST" action="{{ route('admin.legal.draft.discard', $document->id) }}" style="display: inline;" onsubmit="return confirm('Discard active draft? All unsaved draft changes will be removed and editor will revert to live v{{ $document->current_version }}.');">
                    @csrf
                    <button type="submit" class="btn-dash" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FCA5A5; font-size: 12px; padding: 6px 12px; font-weight: 700; cursor: pointer;">
                        🗑️ Discard Draft
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div style="background: #FEF2F2; border: 1px solid #EF4444; color: #991B1B; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
            <ul style="margin: 0; padding-left: 20px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="legalForm" method="POST" action="{{ route('admin.legal.update', $document->id) }}">
        @csrf

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
            
            <!-- Left Column: Markdown Editor & Live Preview -->
            <div class="dash-card">
                <div class="dash-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div class="dash-card-title">
                        <span style="font-size: 18px;">📝</span>
                        <span style="font-weight: 800; color: var(--adm-text);">Policy Content (Markdown)</span>
                    </div>
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <button type="button" id="tabEditorBtn" onclick="switchTab('editor')" class="btn-dash btn-dash-primary" style="font-size: 11px; padding: 4px 10px;">
                            Raw Editor
                        </button>
                        <button type="button" id="tabPreviewBtn" onclick="switchTab('preview')" class="btn-dash btn-dash-outline" style="font-size: 11px; padding: 4px 10px;">
                            Live HTML
                        </button>
                        <button type="button" onclick="openLiveStorefrontPreviewModal()" class="btn-dash" style="background: #6366F1; color: #FFFFFF; font-size: 11px; padding: 4px 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                            <span>👁️</span>
                            <span>Storefront Preview Modal</span>
                        </button>
                    </div>
                </div>

                <div style="padding: 20px;">
                    <!-- Editor Pane -->
                    <div id="editorPane">
                        <textarea name="content" id="markdownContent" rows="24" required
                                  style="width: 100%; font-family: 'Courier New', Courier, monospace; font-size: 13.5px; line-height: 1.5; padding: 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; resize: vertical; box-sizing: border-box;">{{ old('content', $document->has_draft ? $document->draft_content : ($document->currentVersion->content ?? '')) }}</textarea>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #64748B; margin-top: 6px;">
                            <span>Supports GitHub-flavored Markdown: <code># Headers</code>, <code>**Bold**</code>, <code>*Lists*</code>, <code>| Tables |</code>, <code>--- Rules</code>.</span>
                            <span id="wordCharCounter" style="font-family: monospace;">0 words</span>
                        </div>
                    </div>

                    <!-- Live Preview Pane -->
                    <div id="previewPane" style="display: none; background: #FFFFFF; border: 1.5px solid #CBD5E1; border-radius: 8px; padding: 24px; min-height: 480px; max-height: 600px; overflow-y: auto; line-height: 1.7; color: #1E293B;">
                        <div id="previewContainer" class="legal-prose-container">
                            <span style="color: #94A3B8;">Rendering preview...</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Metadata & Publication Controls -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                
                <!-- Version & Effective Date Card -->
                <div class="dash-card">
                    <div class="dash-card-header">
                        <div class="dash-card-title">
                            <span style="font-size: 16px;">⚙️</span>
                            <span style="font-weight: 800; color: var(--adm-text);">Revision Metadata</span>
                        </div>
                    </div>
                    <div style="padding: 20px; display: flex; flex-direction: column; gap: 14px;">
                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">
                                Document Title *
                            </label>
                            <input type="text" name="title" id="documentTitleInput" value="{{ old('title', $document->has_draft ? ($document->draft_title ?? $document->title) : $document->title) }}" required
                                   style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        </div>

                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">
                                Target Version Tag * (SemVer)
                            </label>
                            <input type="text" name="version" id="documentVersionInput" value="{{ old('version', $document->has_draft ? ($document->draft_version ?? $document->current_version) : $document->current_version) }}" required placeholder="e.g. 1.1.0"
                                   style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 14px; font-family: monospace; font-weight: 700; box-sizing: border-box;">
                            <div style="font-size: 11px; color: #64748B; margin-top: 2px;">
                                Currently Live on Site: <strong>v{{ $document->current_version }}</strong>
                            </div>
                        </div>

                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">
                                Effective Date *
                            </label>
                            <input type="date" name="effective_date" id="documentEffectiveDateInput" value="{{ old('effective_date', $document->has_draft ? ($document->draft_effective_date ? $document->draft_effective_date->format('Y-m-d') : date('Y-m-d')) : ($document->effective_date ? $document->effective_date->format('Y-m-d') : date('Y-m-d'))) }}" required
                                   style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        </div>

                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">
                                Summary of Changes (Audit Trail)
                            </label>
                            <textarea name="summary_of_changes" rows="3" placeholder="Explain what changed in this revision..."
                                      style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; resize: vertical; box-sizing: border-box;">{{ old('summary_of_changes', $document->has_draft ? $document->draft_summary_of_changes : '') }}</textarea>
                        </div>

                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">
                                Public Summary Description
                            </label>
                            <textarea name="summary" id="documentSummaryInput" rows="2" placeholder="Short description for storefront header and mobile cards..."
                                      style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; resize: vertical; box-sizing: border-box;">{{ old('summary', $document->has_draft ? ($document->draft_summary ?? $document->summary) : $document->summary) }}</textarea>
                        </div>

                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                            <input type="checkbox" name="is_published" id="is_published" value="1" {{ $document->is_published ? 'checked' : '' }} style="width: 16px; height: 16px;">
                            <label for="is_published" style="font-size: 13px; font-weight: 700; color: #0F172A; cursor: pointer;">
                                Keep Published on Storefront
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Dual Action Card: Draft vs Publish -->
                <div class="dash-card" style="border: 2px solid #3B82F6;">
                    <div style="padding: 20px; display: flex; flex-direction: column; gap: 12px;">
                        
                        <!-- 1. Save as Draft -->
                        <button type="submit" formaction="{{ route('admin.legal.draft.save', $document->id) }}" class="btn-dash" style="width: 100%; padding: 12px; font-size: 13.5px; font-weight: 800; background: #F59E0B; color: #FFFFFF; border: none; border-radius: 8px; text-align: center; justify-content: center; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                            <span>💾</span>
                            <span>Save as Working Draft</span>
                        </button>
                        <p style="font-size: 11.5px; color: #64748B; margin: -4px 0 4px; line-height: 1.3;">
                            Safely preserves changes in the database. Active live version (<strong>v{{ $document->current_version }}</strong>) remains untouched for customers and vendors.
                        </p>

                        <hr style="border: none; border-top: 1px solid #E2E8F0; margin: 4px 0;">

                        <!-- 2. Publish Live Immediately -->
                        <button type="submit" formaction="{{ route('admin.legal.update', $document->id) }}" onclick="return confirm('Publish this version live to all customers and vendors? Current active version will become v' + document.getElementById('documentVersionInput').value);" class="btn-dash btn-dash-primary" style="width: 100%; padding: 12px; font-size: 14px; font-weight: 800; text-align: center; justify-content: center; display: flex; align-items: center; gap: 6px;">
                            <span>🚀</span>
                            <span>Publish Version Live</span>
                        </button>
                        <p style="font-size: 11.5px; color: #64748B; margin: -4px 0 4px; line-height: 1.3;">
                            Promotes this version to active status across all storefront routes and mobile API endpoints.
                        </p>

                        <hr style="border: none; border-top: 1px solid #E2E8F0; margin: 4px 0;">

                        <!-- 3. Discard or Cancel -->
                        <a href="{{ route('admin.legal.index') }}" class="btn-dash btn-dash-outline" style="width: 100%; padding: 8px; font-size: 12.5px; text-align: center; justify-content: center; box-sizing: border-box;">
                            Cancel &amp; Return to Index
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </form>

    <!-- LIVE STOREFRONT SIMULATION MODAL -->
    <div id="storefrontPreviewModal" style="display: none; position: fixed; inset: 0; z-index: 10000; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(4px); overflow-y: auto; padding: 30px 16px;">
        <div style="max-width: 1100px; margin: 0 auto; background: #F8FAFC; border-radius: 16px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 2px solid #3B82F6;">
            
            <!-- Modal Control Top Bar -->
            <div style="background: #0F172A; color: #FFFFFF; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <div style="display: flex; align-items: center; gap: 10px; font-size: 13.5px; font-weight: 700;">
                    <span style="font-size: 18px;">👁️</span>
                    <span>Live Storefront Simulation (Real-Time Layout &amp; Table of Contents)</span>
                </div>
                <button type="button" onclick="closeLiveStorefrontPreviewModal()" style="background: rgba(255, 255, 255, 0.15); border: none; color: #FFFFFF; padding: 6px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer;">
                    ✕ Close Simulation
                </button>
            </div>

            <!-- Storefront Banner Simulation -->
            <div style="padding: 24px 30px;">
                <div style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border-radius: 16px; padding: 32px; color: #FFFFFF; margin-bottom: 24px; position: relative;">
                    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
                        <div>
                            <div id="simCategoryBadge" style="display: inline-flex; align-items: center; gap: 8px; background: rgba(234, 179, 8, 0.15); border: 1px solid rgba(234, 179, 8, 0.4); color: #FACC15; padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 700; margin-bottom: 10px;">
                                🛡️ CENTRALLY GOVERNED MARKETPLACE CONTRACT
                            </div>
                            <h1 id="simTitle" style="font-family: 'Cinzel', serif, system-ui; font-size: 28px; font-weight: 800; margin: 0 0 8px; color: #FFFFFF;">
                                Policy Title
                            </h1>
                            <p id="simSummary" style="color: #94A3B8; font-size: 14px; margin: 0; max-width: 650px;">
                                Summary description will appear here.
                            </p>
                        </div>
                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px;">
                            <span id="simVersionBadge" style="background: rgba(255, 255, 255, 0.1); color: #FFFFFF; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; font-family: monospace;">
                                v1.0.0 (Preview)
                            </span>
                            <div style="font-size: 12px; color: #94A3B8;">
                                Effective: <strong id="simEffectiveDate">Immediate</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Simulation Main Layout: Sidebar & Prose -->
                <div style="display: grid; grid-template-columns: 260px 1fr; gap: 24px; align-items: start;">
                    
                    <!-- Dynamic TOC Sidebar -->
                    <aside style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px;">
                        <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; color: #94A3B8; margin-bottom: 12px;">
                            On This Document
                        </div>
                        <div id="simToc" style="display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; max-height: 400px; overflow-y: auto;">
                            <!-- Filled dynamically from headings -->
                        </div>
                    </aside>

                    <!-- Rendered Prose Container -->
                    <article id="simProse" class="legal-prose-container" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; padding: 36px; line-height: 1.7; color: #1E293B;">
                        <!-- Rendered HTML inserted here -->
                    </article>
                </div>
            </div>

        </div>
    </div>

    <script>
    const csrfToken = '{{ csrf_token() }}';

    // Word Counter
    const mdArea = document.getElementById('markdownContent');
    const wordCounter = document.getElementById('wordCharCounter');
    function updateCounter() {
        const text = mdArea.value.trim();
        const words = text ? text.split(/\s+/).length : 0;
        const chars = text.length;
        if (wordCounter) {
            wordCounter.textContent = `${words} words · ${chars} characters`;
        }
    }
    if (mdArea) {
        mdArea.addEventListener('input', updateCounter);
        updateCounter();
    }

    // In-Page Preview Tab Switcher
    function switchTab(mode) {
        const editorPane = document.getElementById('editorPane');
        const previewPane = document.getElementById('previewPane');
        const editorBtn = document.getElementById('tabEditorBtn');
        const previewBtn = document.getElementById('tabPreviewBtn');
        const content = document.getElementById('markdownContent').value;
        const previewContainer = document.getElementById('previewContainer');

        if (mode === 'preview') {
            editorPane.style.display = 'none';
            previewPane.style.display = 'block';
            editorBtn.className = 'btn-dash btn-dash-outline';
            previewBtn.className = 'btn-dash btn-dash-primary';
            
            // Server-side robust GFM rendering via AJAX
            previewContainer.innerHTML = '<div style="color:#64748B; font-size:13px;">⏳ Rendering GitHub Flavored Markdown preview...</div>';
            fetch('{{ route("admin.legal.preview_raw") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ content: content })
            })
            .then(res => res.json())
            .then(data => {
                previewContainer.innerHTML = data.html || '<p style="color:#94A3B8;">Empty document.</p>';
            })
            .catch(err => {
                console.error('Preview error:', err);
                previewContainer.innerHTML = '<p style="color:#DC2626;">Failed to render live preview. Check network connection.</p>';
            });
        } else {
            editorPane.style.display = 'block';
            previewPane.style.display = 'none';
            editorBtn.className = 'btn-dash btn-dash-primary';
            previewBtn.className = 'btn-dash btn-dash-outline';
        }
    }

    // Live Full Storefront Simulation Modal
    function openLiveStorefrontPreviewModal() {
        const modal = document.getElementById('storefrontPreviewModal');
        const titleInput = document.getElementById('documentTitleInput');
        const versionInput = document.getElementById('documentVersionInput');
        const effectiveDateInput = document.getElementById('documentEffectiveDateInput');
        const summaryInput = document.getElementById('documentSummaryInput');
        const mdContent = document.getElementById('markdownContent').value;

        // Sync Metadata
        document.getElementById('simTitle').textContent = titleInput.value || 'Untitled Policy';
        document.getElementById('simVersionBadge').textContent = 'v' + (versionInput.value || '1.0.0') + ' (Live Simulation)';
        document.getElementById('simEffectiveDate').textContent = effectiveDateInput.value || 'Immediate';
        document.getElementById('simSummary').textContent = summaryInput.value || 'Official institutional terms governing marketplace participation.';

        const simProse = document.getElementById('simProse');
        const simToc = document.getElementById('simToc');
        simProse.innerHTML = '<div style="color:#64748B; font-size:13px;">⏳ Rendering high-fidelity storefront preview...</div>';
        simToc.innerHTML = '';

        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';

        fetch('{{ route("admin.legal.preview_raw") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ content: mdContent })
        })
        .then(res => res.json())
        .then(data => {
            simProse.innerHTML = data.html || '<p style="color:#94A3B8;">Empty document.</p>';

            // Generate Dynamic Table of Contents from parsed HTML headings
            const headings = simProse.querySelectorAll('h1, h2, h3');
            if (headings.length === 0) {
                simToc.innerHTML = '<span style="color:#94A3B8;font-size:12px;">No headings in draft.</span>';
                return;
            }

            headings.forEach((h, idx) => {
                const id = 'sim-head-' + idx;
                h.id = id;
                const link = document.createElement('a');
                link.href = '#' + id;
                link.textContent = h.textContent.trim();
                link.style.display = 'block';
                link.style.color = '#475569';
                link.style.textDecoration = 'none';
                link.style.padding = '4px 6px';
                link.style.borderRadius = '4px';
                link.style.fontSize = h.tagName === 'H1' ? '12.5px' : (h.tagName === 'H2' ? '12px' : '11.5px');
                link.style.fontWeight = h.tagName === 'H1' ? '700' : '500';
                link.style.paddingLeft = h.tagName === 'H3' ? '14px' : (h.tagName === 'H2' ? '8px' : '4px');

                link.addEventListener('mouseenter', () => {
                    link.style.background = '#EFF6FF';
                    link.style.color = '#2563EB';
                });
                link.addEventListener('mouseleave', () => {
                    link.style.background = 'transparent';
                    link.style.color = '#475569';
                });

                simToc.appendChild(link);
            });
        })
        .catch(err => {
            console.error('Storefront preview error:', err);
            simProse.innerHTML = '<p style="color:#DC2626;">Error rendering preview.</p>';
        });
    }

    function closeLiveStorefrontPreviewModal() {
        const modal = document.getElementById('storefrontPreviewModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }
    </script>
@endsection
