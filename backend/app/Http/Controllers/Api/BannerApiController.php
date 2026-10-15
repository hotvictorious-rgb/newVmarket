<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerApiController extends Controller
{
    /**
     * Get active storefront & mobile banners.
     * Supports query filtering: ?type=hero_slider or ?type=promo_strip.
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');

        if ($type === 'hero_slider') {
            $heroBanners = Banner::getActiveHeroBanners();
            return response()->json([
                'status' => true,
                'data' => $heroBanners->map(fn (Banner $b) => $this->formatBanner($b)),
            ]);
        }

        if ($type === 'promo_strip') {
            $promoBanners = Banner::getActivePromoBanners();
            return response()->json([
                'status' => true,
                'data' => $promoBanners->map(fn (Banner $b) => $this->formatBanner($b)),
            ]);
        }

        $heroBanners = Banner::getActiveHeroBanners();
        $promoBanners = Banner::getActivePromoBanners();

        return response()->json([
            'status' => true,
            'data' => [
                'hero_banners' => $heroBanners->map(fn (Banner $b) => $this->formatBanner($b)),
                'promo_banners' => $promoBanners->map(fn (Banner $b) => $this->formatBanner($b)),
            ],
        ]);
    }

    /**
     * Formats banner model into clean mobile DTO.
     */
    protected function formatBanner(Banner $banner): array
    {
        return [
            'id' => $banner->id,
            'banner_type' => $banner->banner_type,
            'template_style' => $banner->template_style ?: Banner::TEMPLATE_LUXURY_SPLIT,
            'title' => $banner->title,
            'subtitle' => $banner->subtitle,
            'badge_text' => $banner->badge_text,
            'discount_tag' => $banner->discount_tag,
            'feature_tags' => $banner->feature_tags_list,
            'image_url' => $banner->image_path ? url($banner->image_path) : url('assets/img/vm_icon.jpg'),
            'bg_gradient' => $banner->bg_gradient ?: 'linear-gradient(135deg, #FAF5FF 0%, #FFFFFF 100%)',
            'text_color' => $banner->text_color ?: '#0F172A',
            'cta_text' => $banner->cta_text ?: 'Shop Verified Items',
            'cta_url' => $banner->cta_url ?: '#catalog',
            'sort_order' => $banner->sort_order,
        ];
    }
}
