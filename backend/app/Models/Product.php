<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'vendor_id',
        'shop_id',
        'category_id',
        'name',
        'slug',
        'description',
        'thumbnail',
        'youtube_video_id',
        'images',
        'unit_price',
        'is_in_stock',
        'inventory_quantity',
        'listing_status',
        'listing_confirmed_at',
        'is_vmarket_flagship',
        'is_approved',
        'package_tier',
        'condition',
        'slash_price',
        'warranty_type',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'slash_price' => 'decimal:2',
        'is_in_stock' => 'boolean',
        'is_vmarket_flagship' => 'boolean',
        'is_approved' => 'boolean',
        'images' => 'array',
        'listing_confirmed_at' => 'datetime',
    ];

    protected $appends = [
        'video_thumbnail_url',
        'video_embed_url',
        'condition_label',
        'package_tier_label',
        'warranty_label',
        'discount_percent',
    ];

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Deterministic YouTube Thumbnail URL.
     * Zero SSRF: Backend never fetches this image; clients load directly from Google CDN.
     */
    public function getVideoThumbnailUrlAttribute(): ?string
    {
        return $this->youtube_video_id
            ? "https://img.youtube.com/vi/{$this->youtube_video_id}/hqdefault.jpg"
            : null;
    }

    /**
     * Deterministic Privacy-Enhanced YouTube Embed URL.
     * Zero SSRF & XSS: Bounded to 11-char alphanumeric ID on youtube-nocookie.com.
     */
    public function getVideoEmbedUrlAttribute(): ?string
    {
        return $this->youtube_video_id
            ? "https://www.youtube-nocookie.com/embed/{$this->youtube_video_id}"
            : null;
    }

    /**
     * Extract strictly the 11-character alphanumeric YouTube Video ID.
     * Rejects all non-matching or malicious inputs.
     */
    public static function extractYoutubeVideoId(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $trimmed = trim($url);

        // If user directly passed an 11-character token
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $trimmed)) {
            return $trimmed;
        }

        // Full YouTube URL matching (watch?v=, embed/, shorts/, or youtu.be/)
        $pattern = '/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
        if (preg_match($pattern, $trimmed, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * 7-Day Marketplace Freshness Invariant:
     * Product listings require explicit merchant confirmation every 7 days.
     */
    public function isFresh(): bool
    {
        if (!$this->listing_confirmed_at) {
            return false;
        }

        return Carbon::now()->diffInDays($this->listing_confirmed_at) <= 7;
    }

    /**
     * Public Marketplace Eligibility Scope:
     * Returns products that are active, approved, listed, and within the 7-day freshness window.
     */
    public function scopeMarketplaceEligible(Builder $query): Builder
    {
        return $query->where('is_approved', true)
            ->where('listing_status', 'listed')
            ->whereNotNull('listing_confirmed_at')
            ->where('listing_confirmed_at', '>=', Carbon::now()->subDays(7))
            ->whereHas('vendor', function ($q) {
                $q->where('marketplace_status', 'active');
            })
            ->whereHas('shop');
    }

    public function getConditionLabelAttribute(): string
    {
        return match ($this->condition) {
            'uk_used' => 'UK Used (Clean Grade A)',
            default => 'Brand New (Sealed Pack)',
        };
    }

    public function getPackageTierLabelAttribute(): string
    {
        return in_array($this->package_tier, ['keke', 'bulky'], true)
            ? 'Keke Cargo (Heavy / Bulky Surcharge)'
            : 'Bike Dispatch (Standard Parcel Box)';
    }

    public function getWarrantyLabelAttribute(): string
    {
        return match ($this->warranty_type) {
            '7_days_replacement' => '7-Day Return / Replacement Warranty',
            'brand_warranty' => 'Official Manufacturer Warranty',
            'as_is' => 'Sold As-Is (No Warranty After Inspection)',
            default => 'Doorstep / In-Store Testing on Arrival',
        };
    }

    public function getDiscountPercentAttribute(): ?int
    {
        if (!$this->slash_price || bccomp((string)$this->slash_price, (string)$this->unit_price, 2) <= 0) {
            return null;
        }

        $diff = bcsub((string)$this->slash_price, (string)$this->unit_price, 2);
        $percent = bcmul(bcdiv($diff, (string)$this->slash_price, 4), '100', 0);
        return (int)$percent;
    }
}