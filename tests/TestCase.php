<?php

namespace Tests;

use App\Models\Business;
use App\Models\BusinessFact;
use App\Models\User;
use App\Models\Website;
use App\Services\Facts\FactNormalizer;
use App\Services\Websites\WebsiteService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // External services are never reached from tests.
        Http::preventStrayRequests();
        $this->withoutVite();
        config([
            'services.gemini.key' => null,
            'services.gemini.retry_delay_ms' => 0,
            'services.gemini.retries' => 1,
            'services.ai.provider' => null,
            'services.ai.retry_delay_ms' => 0,
            'services.groq.key' => null,
            'services.groq.retries' => 1,
            'services.search.provider' => 'none',
            'services.search.key' => null,
            // Faked hosts do not resolve in DNS; SSRF protection is tested separately.
            'siteforge.fetch.allow_private_hosts' => true,
            'siteforge.fetch.max_extra_pages' => 0,
        ]);
    }

    protected function enableGemini(): void
    {
        config(['services.gemini.key' => 'test-key', 'services.gemini.model' => 'gemini-test']);
    }

    /** A Gemini generateContent response whose text part is the given JSON. */
    protected function geminiResponse(array $json): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($json)]]], 'finishReason' => 'STOP']]];
    }

    protected function businessFor(?User $user = null, array $attrs = []): Business
    {
        return Business::factory()->for($user ?? User::factory()->create())->create($attrs + ['name' => 'Glow Salon', 'city' => 'Mumbai']);
    }

    protected function websiteFor(Business $business): Website
    {
        return app(WebsiteService::class)->createForBusiness($business);
    }

    protected function fact(Business $business, string $key, string $value, string $status = BusinessFact::STATUS_CONFIRMED, ?int $sourceId = null, string $sourceType = 'manual'): BusinessFact
    {
        return $business->facts()->create([
            'source_id' => $sourceId,
            'source_type' => $sourceType,
            'key' => $key,
            'value' => $value,
            'normalized_value' => app(FactNormalizer::class)->normalize($key, $value),
            'status' => $status,
            'extracted_at' => now(),
        ]);
    }
}
