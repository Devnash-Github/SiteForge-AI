<?php

namespace App\Services\Facts;

use App\Models\Business;
use App\Models\Source;
use App\Services\Ai\AiClient;
use App\Services\Ai\PromptGuard;

/**
 * Uses Gemini to pull facts out of unstructured source text. Output is
 * restricted to known keys and later grounded against the source text.
 */
class AiFactExtractor
{
    public function __construct(private AiClient $gemini, private PromptGuard $guard) {}

    public function available(): bool
    {
        return $this->gemini->isConfigured();
    }

    /**
     * @return array{about_this_business: bool, facts: array<int, array{key: string, value: string}>}
     */
    public function extract(Business $business, Source $source, string $text): array
    {
        $system = PromptGuard::UNTRUSTED_RULES."\n\n".<<<'TXT'
You are a careful fact extractor for a small-business website builder.
Extract ONLY facts that are explicitly stated in the untrusted document about the target business.
- Never guess, infer, embellish or complete missing details. Copy values as written (you may tidy spacing).
- Allowed keys: business_name, category, description, phone, email, address, opening_hours, service, product, highlight.
- "service" / "product": one item per fact, as named in the document (include a price only if the document states it next to the item).
- "highlight": a concrete, verifiable feature explicitly stated (e.g. "Free home delivery", "Wheelchair accessible"). Never awards, ratings or reviews unless explicitly stated.
- "description": at most two sentences summarising what the document itself says the business does.
- If the document is clearly about a different business (different name or city), set about_this_business to false and return no facts.
- Return at most 40 facts.
TXT;

        $prompt = "Target business: \"{$business->name}\" located in \"{$business->city}\".\n"
            .'Source type: '.$source->type."\n\n"
            .$this->guard->wrap($text, $source->type.' '.($source->url ?? $source->file_name ?? ''), (int) config('siteforge.ai_source_char_limit', 15000))
            ."\n\nReturn the JSON object.";

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'about_this_business' => ['type' => 'BOOLEAN'],
                'facts' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'key' => ['type' => 'STRING', 'enum' => FactKeys::all()],
                            'value' => ['type' => 'STRING'],
                        ],
                        'required' => ['key', 'value'],
                    ],
                ],
            ],
            'required' => ['about_this_business', 'facts'],
        ];

        $result = $this->gemini->generateJson($system, $prompt, $schema, 0.1);

        $facts = [];
        foreach (array_slice((array) ($result['facts'] ?? []), 0, 40) as $fact) {
            if (is_array($fact) && is_string($fact['key'] ?? null) && is_string($fact['value'] ?? null)) {
                $facts[] = ['key' => $fact['key'], 'value' => $fact['value']];
            }
        }

        return [
            'about_this_business' => ($result['about_this_business'] ?? true) !== false,
            'facts' => $facts,
        ];
    }
}
