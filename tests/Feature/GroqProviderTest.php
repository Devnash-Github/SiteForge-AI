<?php

namespace Tests\Feature;

use App\Jobs\GenerateWebsiteJob;
use App\Models\User;
use App\Models\Website;
use App\Services\Ai\AiClient;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\GroqClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqProviderTest extends TestCase
{
    use RefreshDatabase;

    private function groqResponse(array $json): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($json)], 'finish_reason' => 'stop']]];
    }

    public function test_provider_is_selected_automatically_from_the_configured_key(): void
    {
        $this->assertInstanceOf(GeminiClient::class, app(AiClient::class));

        config(['services.groq.key' => 'gsk_test']);
        $this->assertInstanceOf(GroqClient::class, app(AiClient::class));

        config(['services.gemini.key' => 'g-key']);
        $this->assertInstanceOf(GeminiClient::class, app(AiClient::class), 'Gemini wins when both keys are set');

        config(['services.ai.provider' => 'groq']);
        $this->assertInstanceOf(GroqClient::class, app(AiClient::class), 'AI_PROVIDER forces a provider');
    }

    public function test_website_generation_works_with_groq_and_stays_validated(): void
    {
        config(['services.groq.key' => 'gsk_test']);
        $user = User::factory()->create();
        $business = $this->businessFor($user);
        $this->fact($business, 'phone', '+91 98200 12345');
        $this->fact($business, 'service', 'Bridal makeup');
        $website = $this->websiteFor($business);

        Http::fake(['api.groq.com/*' => Http::response($this->groqResponse([
            'hero' => ['headline' => 'Beauty in Bandra', 'subheadline' => 'Rated #1 by 500 clients. Friendly and relaxed.', 'cta_text' => 'Call us'],
            'about' => ['heading' => 'About us', 'body' => 'A friendly salon.', 'highlights' => []],
            'services' => ['title' => 'Services', 'intro' => '', 'items' => [['name' => 'Bridal makeup', 'description' => 'For your big day.'], ['name' => 'Botox', 'description' => 'Invented.']]],
            'gallery' => ['title' => 'Gallery'],
            'contact' => ['heading' => 'Contact', 'intro' => 'Get in touch.', 'cta_text' => 'Call'],
            'footer' => ['tagline' => 'Bandra'],
        ]))]);

        GenerateWebsiteJob::dispatchSync($website->id, ['use_ai' => true]);

        $website->refresh()->load('sections');
        $this->assertSame(Website::GEN_COMPLETED, $website->generation_status, (string) $website->generation_error);
        $this->assertSame('ai', $website->generation_mode);
        $s = $website->sections->keyBy('type');
        $this->assertSame('Beauty in Bandra', $s['hero']->content['headline']);
        $this->assertSame('Friendly and relaxed.', $s['hero']->content['subheadline']);
        $this->assertSame(['Bridal makeup'], array_column($s['services']->content['items'], 'name'));
        $this->assertSame('+91 98200 12345', $s['contact']->content['phone']);

        Http::assertSent(function (Request $r) {
            $body = $r->data();

            return $r->hasHeader('Authorization', 'Bearer gsk_test')
                && $body['model'] === 'llama-3.3-70b-versatile'
                && $body['response_format'] === ['type' => 'json_object']
                && str_contains($body['messages'][0]['content'], '"type":"object"')
                && str_contains($body['messages'][0]['content'], 'Never invent');
        });
    }

    public function test_groq_errors_are_mapped_and_invalid_json_is_retried(): void
    {
        config(['services.groq.key' => 'gsk_test']);
        $business = $this->businessFor();
        $website = $this->websiteFor($business);

        Http::fake(['api.groq.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'Failed to generate JSON', 'code' => 'json_validate_failed']], 400)
            ->push(['error' => ['message' => 'Failed to generate JSON', 'code' => 'json_validate_failed']], 400)
            ->push(['error' => ['message' => 'Invalid API Key', 'code' => 'invalid_api_key']], 401)]);

        GenerateWebsiteJob::dispatchSync($website->id, []);
        $this->assertStringContainsString('unreadable', $website->fresh()->generation_error);

        GenerateWebsiteJob::dispatchSync($website->id, []);
        $this->assertStringContainsString('GROQ_API_KEY', $website->fresh()->generation_error);
        Http::assertSentCount(3);
    }

    public function test_settings_show_the_active_provider(): void
    {
        config(['services.groq.key' => 'gsk_test']);
        $this->actingAs(User::factory()->create())->get('/settings')->assertInertia(fn ($p) => $p
            ->where('integrations.ai.configured', true)
            ->where('integrations.ai.provider', 'groq')
            ->where('integrations.ai.model', 'llama-3.3-70b-versatile'));
    }
}
