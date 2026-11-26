<?php

namespace App\Services\Facts;

/**
 * Anti-hallucination check: an extracted value must be traceable to the
 * source text it supposedly came from.
 */
class FactGrounding
{
    public function __construct(private FactNormalizer $normalizer) {}

    public function isGrounded(string $key, string $value, string $sourceText): bool
    {
        if ($key === 'category') {
            return true; // a classification label, not a factual claim shown verbatim
        }

        if ($key === 'phone') {
            $digits = $this->normalizer->phoneDigits($value);
            $sourceDigits = preg_replace('/\D+/', '', $sourceText) ?? '';

            return strlen($digits) >= 7 && str_contains($sourceDigits, $digits);
        }

        if ($key === 'email') {
            return str_contains(mb_strtolower($sourceText), mb_strtolower(trim($value)));
        }

        $tokens = $this->normalizer->tokens($value);
        if ($tokens === []) {
            // Short values ("Spa") – require a literal match.
            return str_contains($this->normalizer->words($sourceText), $this->normalizer->words($value));
        }

        $haystack = ' '.$this->normalizer->words($sourceText).' ';
        $present = 0;
        foreach ($tokens as $token) {
            if (str_contains($haystack, ' '.$token) || str_contains($haystack, $token)) {
                $present++;
            }
        }

        $required = match ($key) {
            'description' => 0.5,
            'opening_hours' => 0.5,
            default => 0.7,
        };

        return $present / count($tokens) >= $required;
    }
}
