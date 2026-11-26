<?php

namespace App\Services\Websites;

use App\Exceptions\AiException;
use App\Models\Website;
use App\Services\Ai\AiClient;
use Illuminate\Validation\ValidationException;

class WebsiteGenerationService
{
    public function __construct(
        private WebsiteContextBuilder $contexts,
        private AiCopyWriter $ai,
        private BasicCopyWriter $basic,
        private SiteAssembler $assembler,
        private WebsiteService $websites,
        private AiClient $gemini,
    ) {}

    public function aiAvailable(): bool
    {
        return $this->gemini->isConfigured();
    }

    /**
     * Generate and save a full website. Existing sections are only replaced
     * after the new content has been produced and validated.
     *
     * @return array{mode: string, removed_claims: string[]}
     */
    public function generate(Website $website, array $preferences = []): array
    {
        $business = $website->business;
        $ctx = $this->contexts->build($business);
        $useAi = ($preferences['use_ai'] ?? true) && $this->aiAvailable();

        $copy = $useAi ? $this->ai->writeSite($ctx, $preferences) : $this->basic->write($ctx);

        try {
            $result = $this->assembler->assemble($ctx, $copy);
        } catch (ValidationException $e) {
            if (! $useAi) {
                throw $e;
            }
            throw new AiException(AiException::INVALID_RESPONSE, 'AI output failed validation: '.json_encode($e->errors()),
                'The AI returned content that did not pass validation. Please try again.');
        }

        $this->websites->replaceSections($website, $result['sections'], [
            'generation_mode' => $useAi ? 'ai' : 'basic',
            'generated_at' => now(),
            'generation_preferences' => array_intersect_key($preferences, array_flip(['tone', 'instructions', 'use_ai'])),
        ]);

        return ['mode' => $useAi ? 'ai' : 'basic', 'removed_claims' => $result['removed_claims']];
    }
}
