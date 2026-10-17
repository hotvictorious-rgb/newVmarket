<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

class LegalDocument extends Model
{
    use HasFactory;

    protected $table = 'legal_documents';

    protected $fillable = [
        'slug',
        'title',
        'category',
        'current_version',
        'effective_date',
        'is_published',
        'summary',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'is_published' => 'boolean',
    ];

    /**
     * All versions of this document
     */
    public function versions(): HasMany
    {
        return $this->hasMany(LegalDocumentVersion::class, 'legal_document_id')->orderBy('id', 'desc');
    }

    /**
     * Active current version
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(LegalDocumentVersion::class, 'legal_document_id')
            ->where('version', $this->current_version);
    }

    /**
     * Get published legal document by slug with cache
     */
    public static function getBySlug(string $slug): ?self
    {
        return Cache::remember("legal_doc_{$slug}", 3600, function () use ($slug) {
            return static::with(['versions' => function ($query) {
                $query->orderBy('id', 'desc');
            }])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();
        });
    }

    /**
     * Clear document cache
     */
    public function purgeCache(): void
    {
        Cache::forget("legal_doc_{$this->slug}");
        Cache::forget("legal_doc_content_{$this->slug}");
    }
}
