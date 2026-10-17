<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalDocumentVersion extends Model
{
    use HasFactory;

    protected $table = 'legal_document_versions';

    protected $fillable = [
        'legal_document_id',
        'version',
        'title',
        'content',
        'summary_of_changes',
        'effective_date',
        'published_by',
        'ip_address',
    ];

    protected $casts = [
        'effective_date' => 'date',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * Render Markdown to sanitized HTML for web and mobile webviews
     */
    public function getRenderedHtmlAttribute(): string
    {
        return \Illuminate\Support\Str::markdown($this->content ?? '');
    }
}
