<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

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
        'cost_price',
        'is_in_stock',
        'inventory_quantity',
        'physical_quantity',
        'allocated_quantity',
        'held_quantity',
        'listing_status',
        'listing_confirmed_at',
        'is_vmarket_flagship',
        'is_approved',
        'package_tier',
        'condition',
        'slash_price',
        'warranty_type',
        'rating',
        'reviews_count',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'slash_price' => 'decimal:2',
        'rating' => 'decimal:2',
        'reviews_count' => 'integer',
        'physical_quantity' => 'integer',
        'allocated_quantity' => 'integer',
        'held_quantity' => 'integer',
        'is_in_stock' => 'boolean',
        'is_vmarket_flagship' => 'boolean',
        'is_approved' => 'boolean',
        'images' => 'array',
        'listing_confirmed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'video_thumbnail_url',
        'video_embed_url',
        'condition_label',
        'package_tier_label',
        'warranty_label',
        'discount_percent',
        'available_quantity',
        'is_available_for_sale',
    ];



    /**
     * Storefront availability gate:
     * In the two-status model, product is available if marked In Stock and within the 7-day freshness window.
     */
    public function getIsAvailableForSaleAttribute(): bool
    {
        return (bool)$this->is_in_stock && $this->isFresh();
    }

    /**
     * Availability indicator:
     * Returns positive availability if in stock, 0 if out of stock.
     */
    public function getAvailableQuantityAttribute(): int
    {
        if (!(bool)$this->is_in_stock) {
            return 0;
        }

        if (isset($this->attributes['inventory_quantity']) && (int)$this->attributes['inventory_quantity'] > 0) {
            return (int)$this->attributes['inventory_quantity'];
        }

        return 999;
    }

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id')->withTrashed();
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id')->withTrashed();
    }

    public function supplierOffers()
    {
        return $this->hasMany(SupplierOffer::class, 'product_id');
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
     * Fresh = (t_confirmed is not null) AND (t_confirmed <= t_now < t_confirmed + 7 days)
     */
    public function isFresh(): bool
    {
        if (!$this->listing_confirmed_at) {
            return false;
        }

        $now = Carbon::now();
        $confirmedAt = Carbon::parse($this->listing_confirmed_at);

        return $confirmedAt->lte($now) && $now->lt($confirmedAt->copy()->addDays(7));
    }

    /**
     * Public Marketplace Eligibility Scope:
     * Returns products that are active, approved, listed, and within the 7-day freshness window.
     */
    public function scopeMarketplaceEligible(Builder $query): Builder
    {
        $now = Carbon::now();
        $sevenDaysAgo = $now->copy()->subDays(7);

        return $query->where('is_approved', true)
            ->where('listing_status', 'listed')
            ->whereNotNull('listing_confirmed_at')
            ->where('listing_confirmed_at', '>', $sevenDaysAgo)
            ->where('listing_confirmed_at', '<=', $now)
            ->whereHas('supplierOffers', function (Builder $offers) use ($now) {
                $offers->where('is_available', true)
                    ->whereNotNull('availability_confirmed_at')
                    ->whereNotNull('availability_confirmed_by')
                    ->where('availability_confirmed_at', '<=', $now)
                    ->whereNotNull('availability_expires_at')
                    ->where('availability_expires_at', '>', $now)
                    ->whereNull('deleted_at')
                    ->whereHas('vendor', function ($vendor) {
                        $vendor->whereNull('vendor_profiles.deleted_at')
                            ->where('marketplace_status', 'active')
                            ->whereRaw('(select count(*) from shops as active_supplier_shops where active_supplier_shops.vendor_id = vendor_profiles.id and active_supplier_shops.deleted_at is null and active_supplier_shops.is_active = 1) = 1')
                            ->whereHas('user', function ($user) {
                                $user->where('status', 'active');
                            });
                    })
                    ->whereHas('shop', function ($shop) {
                        $shop->whereNull('shops.deleted_at')
                            ->where('shops.is_active', true)
                            ->whereColumn('shops.vendor_id', 'supplier_offers.vendor_id')
                            ->whereHas('lga', function ($lga) {
                                $lga->whereRaw('LOWER(TRIM(lgas.name)) = ?', ['uyo'])
                                    ->whereHas('state', function ($state) {
                                        $state->whereRaw('LOWER(TRIM(states.name)) = ?', ['akwa ibom']);
                                    });
                            });
                    });
            });
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

    public function getDiscountPercentageAttribute(): ?int
    {
        return $this->discount_percent;
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function activeReviews()
    {
        return $this->hasMany(Review::class)->where('status', 'active');
    }

    /**
     * Atomically recalculate product rating and total reviews count.
     */
    public function recalculateRating(): void
    {
        $activeReviews = $this->reviews()->where('status', 'active');
        $count = $activeReviews->count();
        $avg = $count > 0 ? round((float)$activeReviews->avg('rating'), 2) : 0.00;

        $this->update([
            'rating' => $avg,
            'reviews_count' => $count,
        ]);
    }
}