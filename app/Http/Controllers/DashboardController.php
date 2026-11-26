<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Support\Presenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $base = Website::query()->whereHas('business', fn ($q) => $q->where('user_id', $user->id));

        $stats = [
            'total' => (clone $base)->count(),
            'published' => (clone $base)->where('status', Website::STATUS_PUBLISHED)->count(),
            'draft' => (clone $base)->where('status', '!=', Website::STATUS_PUBLISHED)->count(),
        ];

        $recent = WebsiteListQuery::apply($base, $request)
            ->with(['business.media', 'sections'])
            ->limit(6)
            ->get()
            ->map(fn ($w) => Presenter::websiteCard($w));

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'websites' => $recent,
            'filters' => ['search' => (string) $request->query('search', ''), 'status' => (string) $request->query('status', 'all')],
        ]);
    }
}
