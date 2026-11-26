<?php

namespace App\Services\Facts;

use Illuminate\Support\Str;

/**
 * Cleans fact values and produces a comparison key used for de-duplication
 * and conflict detection (e.g. "+91 98200 12345" and "098200-12345" compare equal).
 */
class FactNormalizer
{
    public function clean(string $key, string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value) ?? $value;

        $value = $key === 'description' || $key === 'opening_hours'
            ? trim(preg_replace("/[ \t]+/u", ' ', preg_replace("/\n{3,}/u", "\n\n", str_replace("\r", '', $value)) ?? '') ?? '')
            : trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($key === 'email') {
            $value = Str::lower($value);
        }

        return Str::limit($value, FactKeys::MAX_LENGTH[$key] ?? 500, '');
    }

    public function normalize(string $key, string $value): string
    {
        $normalized = match ($key) {
            'phone' => $this->phoneDigits($value),
            'email' => Str::lower(trim($value)),
            default => $this->words($value),
        };

        return Str::limit($normalized, 500, '');
    }

    /** Compare phones on their last 10 digits so country codes and trunk zeros don't create false conflicts. */
    public function phoneDigits(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    public function words(string $value): string
    {
        $value = Str::lower(Str::ascii($value));
        $value = str_replace(['&'], [' and '], $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /** @return string[] significant tokens (length >= 3) */
    public function tokens(string $value): array
    {
        $stop = ['the', 'and', 'for', 'with', 'our', 'your', 'are', 'from', 'that', 'this', 'you', 'all'];

        return array_values(array_unique(array_filter(
            explode(' ', $this->words($value)),
            fn ($t) => strlen($t) >= 3 && ! in_array($t, $stop, true)
        )));
    }

    public function isValidValue(string $key, string $value): bool
    {
        if ($value === '' || mb_strlen($value) < 2) {
            return false;
        }

        return match ($key) {
            'phone' => strlen(preg_replace('/\D+/', '', $value) ?? '') >= 7 && strlen(preg_replace('/\D+/', '', $value) ?? '') <= 15,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            default => true,
        };
    }
}
