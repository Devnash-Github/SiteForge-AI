<?php

namespace App\Services\Research;

use App\Exceptions\SourceProcessingException;
use App\Jobs\ProcessSourceJob;
use App\Models\Business;
use App\Models\BusinessFact;
use App\Models\Source;
use App\Services\Facts\FactNormalizer;
use App\Services\Sources\SourceProcessingService;
use Illuminate\Support\Facades\Log;

class ResearchService
{
    private const MAX_NEW_SOURCES = 6;

    public function __construct(
        private SearchProvider $provider,
        private IdentityResolver $identity,
        private FactNormalizer $normalizer,
        private SourceProcessingService $sources,
    ) {}

    public function isAvailable(): bool
    {
        return $this->provider->isConfigured();
    }

    public function providerName(): string
    {
        return $this->provider->name();
    }

    /** @return string[] */
    public function queries(Business $business): array
    {
        $queries = ["\"{$business->name}\" {$business->city}"];
        if ($business->category) {
            $queries[] = "{$business->name} {$business->category} {$business->city}";
        }

        return $queries;
    }

    public function run(Business $business): void
    {
        if (! $this->isAvailable()) {
            $business->forceFill([
                'research_status' => 'unavailable',
                'research_error' => 'Web research is not configured. Your own information and links are still used.',
            ])->save();
            $this->sources->refreshBusinessStatus($business);

            return;
        }

        $business->forceFill(['research_status' => 'processing', 'research_error' => null])->save();

        try {
            $limit = (int) config('services.search.max_results', 8);
            $results = [];
            foreach ($this->queries($business) as $query) {
                foreach ($this->provider->search($query, $limit) as $result) {
                    $results[$this->canonical($result->url)] ??= $result;
                }
            }

            $known = $business->sources()->whereNotNull('url')->pluck('url')->map(fn ($u) => $this->canonical($u))->all();
            $phones = $business->facts()->where('key', 'phone')->where('status', '!=', BusinessFact::STATUS_REJECTED)
                ->pluck('value')->map(fn ($p) => $this->normalizer->phoneDigits($p))->all();

            $created = 0;
            $uncertain = 0;
            foreach ($results as $canonical => $result) {
                if ($created >= self::MAX_NEW_SOURCES || in_array($canonical, $known, true)) {
                    continue;
                }
                $match = $this->identity->score($business, $result, $phones);
                if ($match['verdict'] === 'unrelated') {
                    continue;
                }

                $type = SourceProcessingService::typeForUrl($result->url, Source::TYPE_SEARCH_RESULT);
                $isMatched = $match['verdict'] === 'matched';
                $source = $business->sources()->create([
                    'type' => Source::TYPE_SEARCH_RESULT,
                    'label' => mb_substr($result->title ?: $result->url, 0, 250),
                    'url' => $result->url,
                    'status' => Source::STATUS_PENDING,
                    'identity_status' => $isMatched ? Source::IDENTITY_MATCHED : Source::IDENTITY_UNCERTAIN,
                    'metadata' => [
                        'title' => $result->title,
                        'snippet' => $result->snippet,
                        'match_score' => $match['score'],
                        'match_reasons' => $match['reasons'],
                        'provider' => $this->provider->name(),
                        'snippet_only' => $type === Source::TYPE_SOCIAL || ! $isMatched,
                    ],
                ]);
                $created++;
                $uncertain += $isMatched ? 0 : 1;

                if ($isMatched && $type !== Source::TYPE_SOCIAL) {
                    ProcessSourceJob::dispatch($source->id);
                } else {
                    // Uncertain or social results: keep only the public snippet until the owner confirms.
                    $source->forceFill(['extracted_content' => trim("Search result title: {$result->title}\nSearch result snippet: {$result->snippet}")])->save();
                    ProcessSourceJob::dispatch($source->id, snippetOnly: true);
                }
            }

            $business->forceFill([
                'research_status' => 'completed',
                'researched_at' => now(),
                'research_error' => $created === 0 ? 'No matching public information was found for this business and city.' : null,
            ])->save();

            Log::info('Research completed', ['business' => $business->id, 'sources' => $created, 'uncertain' => $uncertain]);
        } catch (SourceProcessingException $e) {
            $business->forceFill(['research_status' => 'failed', 'research_error' => $e->getMessage()])->save();
        }

        $this->sources->refreshBusinessStatus($business);
    }

    private function canonical(string $url): string
    {
        $host = preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST))) ?? '';
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');

        return $host.$path;
    }
}
