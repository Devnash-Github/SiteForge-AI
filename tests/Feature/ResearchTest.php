<?php

namespace Tests\Feature;

use App\Jobs\ResearchBusinessJob;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_research_is_marked_unavailable_without_a_provider(): void
    {
        $business = $this->businessFor();
        ResearchBusinessJob::dispatchSync($business->id);

        $this->assertSame('unavailable', $business->fresh()->research_status);
        $this->assertSame(0, $business->sources()->count());
    }

    public function test_research_separates_matching_and_same_name_results(): void
    {
        config(['services.search.provider' => 'serper', 'services.search.key' => 'k']);
        Http::fake([
            'google.serper.dev/*' => Http::response(['organic' => [
                ['title' => 'Glow Salon Mumbai | Hair & Beauty', 'link' => 'https://glowsalon-mumbai.in/', 'snippet' => 'Glow Salon in Bandra, Mumbai. Call 98200 12345.'],
                ['title' => 'Glow Salon - Austin, Texas', 'link' => 'https://glowsalon-austin.com/', 'snippet' => 'Glow Salon is the best salon in Austin.'],
                ['title' => 'Weather in Mumbai', 'link' => 'https://weather.example/mumbai', 'snippet' => 'Rain expected.'],
            ]]),
            '*glowsalon-mumbai.in*' => Http::response('<html><body><p>Glow Salon, Bandra West, Mumbai. Phone: 98200 12345</p></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $user = User::factory()->create();
        $business = $this->businessFor($user);
        ResearchBusinessJob::dispatchSync($business->id);

        $this->assertSame('completed', $business->fresh()->research_status);
        $matched = $business->sources()->where('url', 'https://glowsalon-mumbai.in/')->first();
        $uncertain = $business->sources()->where('url', 'https://glowsalon-austin.com/')->first();

        $this->assertNotNull($matched);
        $this->assertSame(Source::IDENTITY_MATCHED, $matched->identity_status);
        $this->assertSame(Source::STATUS_COMPLETED, $matched->status);
        $this->assertDatabaseHas('business_facts', ['source_id' => $matched->id, 'key' => 'phone', 'status' => 'extracted']);

        // Same name, different city: kept for the owner to judge, its page is never fetched.
        $this->assertNotNull($uncertain);
        $this->assertSame(Source::IDENTITY_UNCERTAIN, $uncertain->identity_status);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'glowsalon-austin.com'));

        $this->assertDatabaseMissing('sources', ['url' => 'https://weather.example/mumbai']);

        // Owner rejects the same-name business.
        $this->actingAs($user)->post("/businesses/{$business->id}/sources/{$uncertain->id}/identity", ['decision' => 'reject'])->assertSessionHasNoErrors();
        $this->assertSame(Source::IDENTITY_REJECTED, $uncertain->fresh()->identity_status);
        $this->assertSame(0, $uncertain->facts()->count());
    }

    public function test_confirming_an_uncertain_result_releases_its_facts(): void
    {
        $user = User::factory()->create();
        $business = $this->businessFor($user);
        $source = $business->sources()->create([
            'type' => 'search_result', 'url' => 'https://www.instagram.com/glow', 'status' => 'completed',
            'identity_status' => Source::IDENTITY_UNCERTAIN, 'metadata' => ['snippet_only' => true],
        ]);
        $fact = $this->fact($business, 'phone', '9820012345', 'needs_review', $source->id, 'search_result');

        $this->actingAs($user)->post("/businesses/{$business->id}/sources/{$source->id}/identity", ['decision' => 'confirm'])->assertSessionHasNoErrors();

        $this->assertSame(Source::IDENTITY_CONFIRMED, $source->fresh()->identity_status);
        $this->assertSame('extracted', $fact->fresh()->status);
    }

    public function test_search_api_errors_are_reported_not_hidden(): void
    {
        config(['services.search.provider' => 'serper', 'services.search.key' => 'bad']);
        Http::fake(['google.serper.dev/*' => Http::response(['message' => 'Unauthorized'], 403)]);

        $business = $this->businessFor();
        ResearchBusinessJob::dispatchSync($business->id);

        $this->assertSame('failed', $business->fresh()->research_status);
        $this->assertStringContainsString('SEARCH_API_KEY', $business->fresh()->research_error);
    }
}
