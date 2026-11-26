<?php

namespace App\Services\Research;

use App\Exceptions\SourceProcessingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** https://serper.dev — Google search results API. */
class SerperSearchProvider implements SearchProvider
{
    public function name(): string
    {
        return 'serper';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.search.key'));
    }

    public function search(string $query, int $limit): array
    {
        try {
            $response = Http::withHeaders(['X-API-KEY' => (string) config('services.search.key')])
                ->acceptJson()
                ->timeout((int) config('services.search.timeout', 20))
                ->post('https://google.serper.dev/search', ['q' => $query, 'num' => min(max($limit, 1), 10)]);
        } catch (ConnectionException) {
            throw new SourceProcessingException('The search service could not be reached.');
        }

        if ($response->status() === 429) {
            throw new SourceProcessingException('The search service rate limit was reached. Try again later.');
        }
        if ($response->failed()) {
            throw new SourceProcessingException('The search service returned an error (HTTP '.$response->status().'). Check SEARCH_API_KEY.');
        }

        $results = [];
        foreach ((array) $response->json('organic', []) as $item) {
            if (! empty($item['link'])) {
                $results[] = new SearchResult((string) ($item['title'] ?? ''), (string) $item['link'], (string) ($item['snippet'] ?? ''));
            }
        }
        $place = $response->json('knowledgeGraph');
        if (is_array($place) && ! empty($place['website'])) {
            $snippet = trim(implode(' · ', array_filter([
                $place['type'] ?? null, $place['description'] ?? null,
                isset($place['attributes']) && is_array($place['attributes']) ? collect($place['attributes'])->map(fn ($v, $k) => "{$k}: {$v}")->implode(' · ') : null,
            ])));
            array_unshift($results, new SearchResult((string) ($place['title'] ?? ''), (string) $place['website'], $snippet));
        }

        return array_slice($results, 0, $limit);
    }
}
