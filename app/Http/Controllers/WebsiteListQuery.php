<?php

namespace App\Http\Controllers;

use App\Models\Website;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Shared search / status filtering for website lists. */
final class WebsiteListQuery
{
    public static function apply(Builder $query, Request $request): Builder
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_substr($search, 0, 100)).'%';
            $query->where(function ($q) use ($like) {
                $q->where('websites.name', 'like', $like)
                    ->orWhereHas('business', fn ($b) => $b->where('name', 'like', $like)->orWhere('city', 'like', $like));
            });
        }

        if ($status === 'published') {
            $query->where('status', Website::STATUS_PUBLISHED);
        } elseif ($status === 'draft') {
            $query->where('status', '!=', Website::STATUS_PUBLISHED);
        }

        return $query->orderByDesc('updated_at');
    }
}
