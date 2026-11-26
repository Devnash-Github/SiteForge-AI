<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\Websites\PublishingService;
use App\Services\Websites\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishController extends Controller
{
    public function __construct(private PublishingService $publishing) {}

    public function publish(Request $request, Website $website): JsonResponse
    {
        $this->authorize('update', $website);
        $data = $request->validate(['slug' => ['nullable', 'string', 'max:80']]);
        $this->publishing->publish($website, $data['slug'] ?? null);

        return response()->json([
            'status' => $website->status,
            'slug' => $website->slug,
            'url' => $website->publicUrl(),
            'published_at' => $website->published_at?->toIso8601String(),
        ]);
    }

    public function unpublish(Request $request, Website $website): JsonResponse
    {
        $this->authorize('update', $website);
        $this->publishing->unpublish($website);

        return response()->json(['status' => $website->status]);
    }

    public function checkSlug(Request $request, Website $website, SlugService $slugs): JsonResponse
    {
        $this->authorize('update', $website);
        $slug = strtolower(trim((string) $request->query('slug', '')));
        $valid = $slugs->isValid($slug);

        return response()->json([
            'slug' => $slug,
            'valid' => $valid,
            'available' => $valid && $slugs->isAvailable($slug, $website->id),
            'suggestion' => $valid ? null : $slugs->suggest($slug ?: $website->business->name, '', $website->id),
            'url' => $valid ? route('sites.show', ['slug' => $slug]) : null,
        ]);
    }
}
