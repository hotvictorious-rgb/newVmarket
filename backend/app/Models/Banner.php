<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Banner extends Model
{
    use HasFactory;

    public const CACHE_KEY_HERO = 'storefront_active_banners_hero_slider';
    public const CACHE_KEY_PROMO = 'storefront_active_banners_promo_strip';
    public const CACHE_KEY_ALL = 'storefront_all_active_banners';

    public const TEMPLATE_LUXURY_SPLIT = 'luxury_split';
    public const TEMPLATE_FULL_COVER = 'full_cover';
    public const TEMPLATE_FLASH_URGENCY = 'flash_urgency';
    public const TEMPLATE_MINIMAL_BENTO = 'minimal_bento';
    public const TEMPLATE_FRESH_MARKET = 'fresh_market';
    public const TEMPLATE_BRAND_PRESTIGE = 'brand_prestige';

    protected $fillable = [
        'banner_type',
        'template_style',
        'title',
        'subtitle',
        'badge_text',
        'discount_tag',
        'feature_tags',
        'image_path',
        'bg_gradient',
        'text_color',
        'cta_text',
        'cta_url',
        'is_active',
        'sort_order',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            self::flushBannerCache();
        });

        static::deleted(function () {
            self::flushBannerCache();
        });
    }

    public static function flushBannerCache(): void
    {
        Cache::forget(self::CACHE_KEY_HERO);
        Cache::forget(self::CACHE_KEY_PROMO);
        Cache::forget(self::CACHE_KEY_ALL);
    }

    /**
     * Scope for banners that are active and currently valid.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'desc');
    }

    public function scopeHero($query)
    {
        return $query->where('banner_type', 'hero_slider');
    }

    public function scopePromo($query)
    {
        return $query->where('banner_type', 'promo_strip');
    }

    public function getFeatureTagsListAttribute(): array
    {
        if (empty($this->feature_tags)) {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $this->feature_tags))));
    }

    /**
     * Retrieve cached active hero banners.
     */
    public static function getActiveHeroBanners()
    {
        return Cache::remember(self::CACHE_KEY_HERO, 3600, function () {
            return self::hero()->active()->get();
        });
    }

    /**
     * Retrieve cached active promo strip banners.
     */
    public static function getActivePromoBanners()
    {
        return Cache::remember(self::CACHE_KEY_PROMO, 3600, function () {
            return self::promo()->active()->get();
        });
    }
}
