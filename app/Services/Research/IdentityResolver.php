<?php

namespace App\Services\Research;

use App\Models\Business;
use App\Services\Facts\FactNormalizer;

/**
 * Decides whether a search result is about *this* business. A name match
 * alone is never enough: the city, the owner's own domain/handles or a
 * known phone number must also line up.
 */
class IdentityResolver
{
    public const MATCH_THRESHOLD = 0.75;

    public const UNCERTAIN_THRESHOLD = 0.4;

    public function __construct(private FactNormalizer $normalizer) {}

    /**
     * @param  string[]  $knownPhones  digits of phones already associated with the business
     * @return array{score: float, verdict: string, reasons: string[]}
     */
    public function score(Business $business, SearchResult $result, array $knownPhones = []): array
    {
        $haystack = $this->normalizer->words($result->title.' '.$result->snippet.' '.$result->url);
        $score = 0.0;
        $reasons = [];

        $nameTokens = $this->normalizer->tokens($business->name);
        if ($nameTokens !== []) {
            $present = count(array_filter($nameTokens, fn ($t) => str_contains($haystack, $t)));
            $ratio = $present / count($nameTokens);
            if ($ratio >= 0.99) {
                $score += 0.45;
                $reasons[] = 'Business name matches';
            } elseif ($ratio >= 0.5) {
                $score += 0.2;
                $reasons[] = 'Business name partly matches';
            }
        }

        $cityWords = $this->normalizer->words($business->city);
        if ($cityWords !== '' && str_contains($haystack, $cityWords)) {
            $score += 0.3;
            $reasons[] = 'City matches';
        }

        $resultHost = $this->host($result->url);
        foreach (array_filter([$business->website_url, $business->instagram_url, $business->facebook_url, $business->linkedin_url]) as $known) {
            $knownHost = $this->host($known);
            if ($knownHost !== '' && $resultHost === $knownHost) {
                $isSocialHost = (bool) preg_match('/(instagram|facebook|linkedin)\.com$/', $knownHost);
                if (! $isSocialHost || rtrim(strtolower((string) parse_url($known, PHP_URL_PATH)), '/') === rtrim(strtolower((string) parse_url($result->url, PHP_URL_PATH)), '/')) {
                    $score += 0.5;
                    $reasons[] = 'Matches a link you provided';
                    break;
                }
            }
        }

        $digits = preg_replace('/\D+/', '', $result->snippet) ?? '';
        foreach ($knownPhones as $phone) {
            if (strlen($phone) >= 7 && str_contains($digits, $phone)) {
                $score += 0.3;
                $reasons[] = 'Phone number matches';
                break;
            }
        }

        $score = min(1.0, round($score, 2));
        $verdict = $score >= self::MATCH_THRESHOLD ? 'matched' : ($score >= self::UNCERTAIN_THRESHOLD ? 'uncertain' : 'unrelated');

        return ['score' => $score, 'verdict' => $verdict, 'reasons' => $reasons];
    }

    private function host(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return preg_replace('/^(www\.|m\.)/', '', $host) ?? $host;
    }
}
