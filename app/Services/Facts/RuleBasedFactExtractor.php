<?php

namespace App\Services\Facts;

/**
 * Deterministic extraction: structured data (JSON-LD), tel:/mailto: links and
 * conservative regexes for phone numbers and emails in plain text.
 */
class RuleBasedFactExtractor
{
    /**
     * @param  array<string, mixed>  $metadata  source metadata (may contain 'structured', 'phones', 'emails')
     * @return array<int, array{key: string, value: string}>
     */
    public function extract(string $text, array $metadata = []): array
    {
        $facts = [];
        $structured = $metadata['structured'] ?? [];

        $map = ['name' => 'business_name', 'telephone' => 'phone', 'email' => 'email', 'address' => 'address', 'opening_hours' => 'opening_hours', 'description' => 'description'];
        foreach ($map as $from => $key) {
            if (! empty($structured[$from]) && is_string($structured[$from])) {
                $facts[] = ['key' => $key, 'value' => $structured[$from]];
            }
        }

        foreach ((array) ($metadata['phones'] ?? []) as $phone) {
            $facts[] = ['key' => 'phone', 'value' => (string) $phone];
        }
        foreach ((array) ($metadata['emails'] ?? []) as $email) {
            $facts[] = ['key' => 'email', 'value' => (string) $email];
        }

        foreach ($this->emails($text) as $email) {
            $facts[] = ['key' => 'email', 'value' => $email];
        }
        foreach ($this->phones($text) as $phone) {
            $facts[] = ['key' => 'phone', 'value' => $phone];
        }

        return $facts;
    }

    /** @return string[] */
    public function emails(string $text): array
    {
        preg_match_all('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,24}\b/i', $text, $m);

        return array_values(array_unique(array_filter(
            array_map('strtolower', $m[0]),
            fn ($e) => ! preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', $e)
                && ! preg_match('/@(example|sentry|wixpress)\./i', $e)
                && filter_var($e, FILTER_VALIDATE_EMAIL)
        )));
    }

    /**
     * Phone candidates are accepted only when they look intentional: an
     * international prefix, a nearby phone keyword, or an Indian mobile pattern.
     *
     * @return string[]
     */
    public function phones(string $text): array
    {
        $found = [];
        if (! preg_match_all('/(?<![\w\/])(\+?\(?\d[\d\s().-]{6,18}\d)(?![\w\/])/u', $text, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        foreach ($m[1] as [$candidate, $offset]) {
            $digits = preg_replace('/\D+/', '', $candidate) ?? '';
            $len = strlen($digits);
            if ($len < 8 || $len > 13) {
                continue;
            }
            // Dates, years and ranges such as 2019-2024 or 10.00-18.00 are not phone numbers.
            if (preg_match('/^\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}$/', trim($candidate)) || preg_match('/^(19|20)\d{2}\s*-\s*(19|20)\d{2}$/', trim($candidate))) {
                continue;
            }
            if (preg_match('/^\d{1,2}[.:]\d{2}\s*-\s*\d{1,2}[.:]\d{2}$/', trim($candidate))) {
                continue;
            }

            $context = strtolower(substr($text, max(0, $offset - 40), 40));
            $hasKeyword = (bool) preg_match('/(phone|ph\.?|tel|call|mobile|mob\.?|whatsapp|contact|cell|landline|reach us)/', $context);
            $international = str_starts_with(trim($candidate), '+');
            $indianMobile = (bool) preg_match('/^(?:91|0)?[6-9]\d{9}$/', $digits);

            if ($hasKeyword || $international || $indianMobile) {
                $found[] = trim(preg_replace('/\s+/', ' ', $candidate) ?? $candidate);
            }
        }

        return array_values(array_unique($found));
    }
}
