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
                Slug: <code>{{ $document->slug }}</code> &bull; Current Live Version: <strong>v{{ $document->current_version }}</strong>
            </p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.legal.index') }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                &larr; Back to Policies
            </a>
            <a href="{{ route('admin.legal.history', $document->id) }}" class="btn-dash btn-dash-outline" style="font-size: 12px; padding: 6px 12px;">
                📜 View {{ $document->versions->count() }} Past Revisions
            </a>
        </div>
    </div>

    @if($errors->any())
        <div style="background: #FEF2F2; border: 1px solid #EF4444; color: #991B1B; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600;">
            <ul style="margin: 0; padding-left: 20px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.legal.update', $document->id) }}">
        @csrf

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
            
            <!-- Left Column: Markdown Editor & Live Preview -->
            <div class="dash-card">
                <div class="dash-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div class="dash-card-title">
                        <span style="font-size: 18px;">📝</span>
                        <span style="font-weight: 800; color: var(--adm-text);">Policy Content (Markdown)</span>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" id="tabEditorBtn" onclick="switchTab('editor')" class="btn-dash btn-dash-primary" style="font-size: 11px; padding: 4px 10px;">
                            Raw Markdown
                        </button>
                        <button type="button" id="tabPreviewBtn" onclick="switchTab('preview')" class="btn-dash btn-dash-outline" style="font-size: 11px; padding: 4px 10px;">
                            Live HTML Preview
                        </button>
                    </div>
                </div>

                <div style="padding: 20px;">
                    <!-- Editor Pane -->
                    <div id="editorPane">
                        <textarea name="content" id="markdownContent" rows="24" required
                                  style="width: 100%; font-family: 'Courier New', Courier, monospace; font-size: 13.5px; line-height: 1.5; padding: 14px; border: 1.5px solid #CBD5E1; border-radius: 8px; resize: vertical; box-sizing: border-box;">{{ old('content', $document->currentVersion->content ?? '') }}</textarea>
                        <div style="font-size: 12px; color: #64748B; margin-top: 6px;">
                            Supports GitHub-flavored Markdown: <code># Headers</code>, <code>**Bold**</code>, <code>*Bullet lists*</code>, <code>--- Horizontal rules</code>.
                        </div>
                    </div>

                    <!-- Live Preview Pane -->
                    <div id="previewPane" style="display: none; background: #F8FAFC; border: 1.5px solid #E2E8F0; border-radius: 8px; padding: 24px; min-height: 480px; max-height: 600px; overflow-y: auto; line-height: 1.6;">
                        <div id="previewContainer"></div>
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
                            <input type="text" name="title" value="{{ old('title', $document->title) }}" required
                                   style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        </div>

                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">
                                New Version Tag * (SemVer)
                            </label>
                            <input type="text" name="version" value="{{ old('version', $document->current_version) }}" required placeholder="e.g. 1.0.1"
                                   style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 14px; font-family: monospace; font-weight: 700; box-sizing: border-box;">
                            <div style="font-size: 11px; color: #64748B; margin-top: 2px;">
                                Current active: v{{ $document->current_version }}
                            </div>
                        </div>

                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">
                                Effective Date *
                            </label>
                            <input type="date" name="effective_date" value="{{ old('effective_date', $document->effective_date ? $document->effective_date->format('Y-m-d') : date('Y-m-d')) }}" required
                                   style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        </div>

                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">
                                Summary of Changes * (Audit Log)
                            </label>
                            <textarea name="summary_of_changes" rows="3" placeholder="Briefly describe what changed in this revision..."
                                      style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; resize: vertical; box-sizing: border-box;">{{ old('summary_of_changes') }}</textarea>
                        </div>

                        <div>
                            <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">
                                Public Summary Description
                            </label>
                            <textarea name="summary" rows="2" placeholder="Short description for storefront header and mobile cards..."
                                      style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; resize: vertical; box-sizing: border-box;">{{ old('summary', $document->summary) }}</textarea>
                        </div>

                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                            <input type="checkbox" name="is_published" id="is_published" value="1" {{ $document->is_published ? 'checked' : '' }} style="width: 16px; height: 16px;">
                            <label for="is_published" style="font-size: 13px; font-weight: 700; color: #0F172A; cursor: pointer;">
                                Publish Live Immediately
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Publish Action Card -->
                <div class="dash-card" style="border: 1.5px solid #3B82F6;">
                    <div style="padding: 20px; display: flex; flex-direction: column; gap: 10px;">
                        <button type="submit" class="btn-dash btn-dash-primary" style="width: 100%; padding: 12px; font-size: 14px; font-weight: 800; text-align: center; justify-content: center;">
                            🚀 Publish Policy Version
                        </button>
                        <a href="{{ route('admin.legal.index') }}" class="btn-dash btn-dash-outline" style="width: 100%; padding: 10px; font-size: 13px; text-align: center; justify-content: center; box-sizing: border-box;">
                            Cancel &amp; Discard
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </form>

    <script>
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
            
            // Simple markdown-to-html preview
            previewContainer.innerHTML = renderSimpleMarkdown(content);
        } else {
            editorPane.style.display = 'block';
            previewPane.style.display = 'none';
            editorBtn.className = 'btn-dash btn-dash-primary';
            previewBtn.className = 'btn-dash btn-dash-outline';
        }
    }

    function renderSimpleMarkdown(text) {
        if (!text) return '<p>No content entered.</p>';
        return text
            .replace(/^# (.*$)/gim, '<h1 style="font-size:22px;font-weight:800;margin:16px 0 8px;">$1</h1>')
            .replace(/^### (.*$)/gim, '<h3 style="font-size:16px;font-weight:700;margin:16px 0 6px;">$1</h3>')
            .replace(/^## (.*$)/gim, '<h2 style="font-size:18px;font-weight:700;margin:18px 0 8px;">$1</h2>')
            .replace(/^\> (.*$)/gim, '<blockquote style="border-left:3px solid #3B82F6;padding-left:12px;color:#1E3A8A;margin:10px 0;">$1</blockquote>')
            .replace(/\*\*(.*)\*\*/gim, '<strong>$1</strong>')
            .replace(/\*(.*)\*/gim, '<em>$1</em>')
            .replace(/---/gim, '<hr style="border:none;border-top:1px solid #CBD5E1;margin:18px 0;">')
            .replace(/\n$/gim, '<br />')
            .replace(/\n/gim, '<br />');
    }
    </script>
@endsection
