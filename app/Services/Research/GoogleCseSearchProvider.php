<?php

namespace App\Services\Research;

use App\Exceptions\SourceProcessingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Google Programmable Search Engine (Custom Search JSON API). */
class GoogleCseSearchProvider implements SearchProvider
{
    public function name(): string
    {
        return 'google_cse';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.search.key')) && filled(config('services.search.google_cse_id'));
    }

    public function search(string $query, int $limit): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.search.timeout', 20))
                ->get('https://www.googleapis.com/customsearch/v1', [
                    'key' => (string) config('services.search.key'),
                    'cx' => (string) config('services.search.google_cse_id'),
                    'q' => $query,
                    'num' => min(max($limit, 1), 10),
                ]);
        } catch (ConnectionException) {
            throw new SourceProcessingException('The search service could not be reached.');
        }

        if ($response->failed()) {
            throw new SourceProcessingException('The search service returned an error (HTTP '.$response->status().'). Check SEARCH_API_KEY and GOOGLE_CSE_ID.');
        }

        $results = [];
        foreach ((array) $response->json('items', []) as $item) {
            if (! empty($item['link'])) {
                $results[] = new SearchResult((string) ($item['title'] ?? ''), (string) $item['link'], (string) ($item['snippet'] ?? ''));
            }
        }

        return $results;
    }
}
