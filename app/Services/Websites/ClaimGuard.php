<?php

namespace App\Services\Websites;

use App\Services\Facts\FactNormalizer;

/**
 * Removes sentences that make specific factual claims (years in business,
 * awards, ratings, customer counts, guarantees, certifications…) that are
 * not backed by the business's confirmed facts.
 */
class ClaimGuard
{
    private const RISKY_PATTERNS = [
        '/\b\d+\s*\+?\s*(years?|yrs?|decades?)\b/i',
        '/\bsince\s+(19|20)\d{2}\b/i',
        '/\b(established|founded|est\.)\s+(in\s+)?(19|20)\d{2}\b/i',
        '/\baward(s|-winning|ed)?\b/i',
        '/\bcertified|certification|accredited|licensed\b/i',
        '/\b\d[\d,.]*\s*\+?\s*(happy\s+)?(customers|clients|patients|students|members|projects|reviews|orders)\b/i',
        '/\b(rated|rating|stars?|5-star|five-star)\b/i',
        // Superiority claims ("the best", "best salon in Mumbai", "#1") — but not "look your best".
        '/(#\s?1\b|\bno\.?\s?1\b|\bnumber one\b|\bthe\s+best\b|\bbest\s+[a-z]+\s+in\b|\bleading\b|\btop-rated\b)/i',
        '/\b(guarantee[sd]?|warranty|money-back)\b/i',
        '/\btestimonial|review(s|ed)? by\b/i',
        '/\b\d+\s*%\b/',
        '/(₹|rs\.?|inr|\$|€|£)\s?\d/i',
    ];

    public function __construct(private FactNormalizer $normalizer) {}

    /**
     * @param  string  $evidence  concatenated confirmed facts / owner text that claims may rely on
     * @return array{text: string, removed: string[]}
     */
    public function filter(string $text, string $evidence): array
    {
        if (trim($text) === '') {
            return ['text' => $text, 'removed' => []];
        }

        $evidenceWords = ' '.$this->normalizer->words($evidence).' ';
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [$text];
        $kept = [];
        $removed = [];

        foreach ($sentences as $sentence) {
            if ($this->isUnsupported($sentence, $evidenceWords)) {
                $removed[] = $sentence;
            } else {
                $kept[] = $sentence;
            }
        }

        return ['text' => trim(implode(' ', $kept)), 'removed' => $removed];
    }

    private function isUnsupported(string $sentence, string $evidenceWords): bool
    {
        foreach (self::RISKY_PATTERNS as $pattern) {
            if (! preg_match($pattern, $sentence, $m)) {
                continue;
            }
            $claim = $this->normalizer->words($m[0]);
            // Supported only if the exact claim (e.g. "since 1998", "award") appears in the evidence.
            if ($claim === '' || ! str_contains($evidenceWords, ' '.$claim.' ') && ! str_contains($evidenceWords, $claim)) {
                return true;
            }
        }

        return false;
    }
}
