<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalApiController extends Controller
{
    /**
     * List all published legal policies
     */
    public function index(): JsonResponse
    {
        $documents = LegalDocument::where('is_published', true)
            ->select(['id', 'slug', 'title', 'category', 'current_version', 'effective_date', 'summary', 'updated_at'])
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $documents,
        ]);
    }

    /**
     * Get specific legal policy by slug
     */
    public function show(string $slug): JsonResponse
    {
        $document = LegalDocument::getBySlug($slug);

        if (!$document) {
            return response()->json([
                'success' => false,
                'message' => "Legal policy '{$slug}' not found.",
            ], 404);
        }

        $version = $document->currentVersion;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $document->id,
                'slug' => $document->slug,
                'title' => $document->title,
                'category' => $document->category,
                'version' => $document->current_version,
                'effective_date' => $document->effective_date ? $document->effective_date->format('Y-m-d') : null,
                'effective_date_formatted' => $document->effective_date ? $document->effective_date->format('F d, Y') : null,
                'summary' => $document->summary,
                'content_markdown' => $version ? $version->content : '',
                'content_html' => $version ? $version->rendered_html : '',
                'updated_at' => $document->updated_at ? $document->updated_at->toIso8601String() : null,
            ],
        ]);
    }
}
