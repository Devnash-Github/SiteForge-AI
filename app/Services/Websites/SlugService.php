<?php

namespace App\Services\Websites;

use App\Models\Website;
use Illuminate\Support\Str;

class SlugService
{
    public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function suggest(string $name, string $city = '', ?int $exceptId = null): string
    {
        $base = Str::slug(trim($name.' '.$city)) ?: 'site';
        $base = trim(Str::limit($base, 70, ''), '-');
        if (strlen($base) < 3) {
            $base = 'site-'.$base;
        }

        $slug = $base;
        $i = 2;
        while (! $this->isAvailable($slug, $exceptId)) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function isValid(string $slug): bool
    {
        return strlen($slug) >= 3 && strlen($slug) <= 80 && preg_match(self::PATTERN, $slug) === 1
            && ! in_array($slug, config('siteforge.reserved_slugs', []), true);
    }

    public function isAvailable(string $slug, ?int $exceptId = null): bool
    {
        if (in_array($slug, config('siteforge.reserved_slugs', []), true)) {
            return false;
        }

        return ! Website::where('slug', $slug)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists();
    }
}
