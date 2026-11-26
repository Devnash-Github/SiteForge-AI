<?php

namespace App\Http\Controllers;

use App\Models\Website;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicSiteController extends Controller
{
    public function show(string $slug): Response
    {
        $website = Website::where('slug', $slug)->where('status', Website::STATUS_PUBLISHED)->firstOrFail();
        $content = $website->published_content ?? [];

        $hero = collect($content['sections'] ?? [])->firstWhere('type', 'hero');
        $about = collect($content['sections'] ?? [])->firstWhere('type', 'about');
        $description = Str::limit((string) (data_get($hero, 'content.subheadline') ?: data_get($about, 'content.body') ?: ''), 160);

        return Inertia::render('PublicSite', ['site' => $content])
            ->withViewData(['meta' => [
                'title' => data_get($content, 'business.name', $website->name).' — '.data_get($content, 'business.city', ''),
                'description' => $description ?: data_get($content, 'business.name', $website->name),
            ]]);
    }
}
