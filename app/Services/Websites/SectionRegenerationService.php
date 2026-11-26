<?php

namespace App\Services\Websites;

use App\Exceptions\AiException;
use App\Models\WebsiteSection;
use Illuminate\Validation\ValidationException;

/**
 * Produces a *proposal* for one section. Nothing is saved here: the owner
 * accepts (saved via the normal section update) or discards it.
 */
class SectionRegenerationService
{
    /** Fields the AI may rewrite per section; everything else is kept from the current content. */
    private const WRITABLE = [
        'hero' => ['headline', 'subheadline', 'cta_text'],
        'about' => ['heading', 'body', 'highlights'],
        'services' => ['title', 'intro', 'items'],
        'gallery' => ['title'],
        'contact' => ['heading', 'intro', 'cta_text'],
        'footer' => ['tagline'],
    ];

    public function __construct(
        private WebsiteContextBuilder $contexts,
        private AiCopyWriter $ai,
        private SiteAssembler $assembler,
        private ClaimGuard $claims,
        private WebsiteSchema $schema,
        private WebsiteService $websites,
    ) {}

    /** @return array{content: array, removed_claims: string[]} */
    public function propose(WebsiteSection $section, string $instructions, array $preferences = []): array
    {
        $website = $section->website;
        $ctx = $this->contexts->build($website->business);
        $current = $section->content;

        $copy = $this->ai->writeSection($ctx, $section->type, $this->writableSubset($section->type, $current), trim($instructions), $preferences);

        $evidence = $ctx->evidence()."\n".json_encode($current, JSON_UNESCAPED_UNICODE);
        $removed = [];
        $guard = function (string $text) use ($evidence, &$removed): string {
            $r = $this->claims->filter($text, $evidence);
            array_push($removed, ...$r['removed']);

            return $r['text'];
        };

        $proposal = $current;
        foreach (self::WRITABLE[$section->type] as $field) {
            if (! array_key_exists($field, $copy)) {
                continue;
            }
            $value = $copy[$field];
            $proposal[$field] = match ($field) {
                'items' => $this->assembler->matchServiceItems(
                    array_values(array_unique(array_merge(array_column($current['items'] ?? [], 'name'), $ctx->offerings()))),
                    (array) $value, $guard),
                'highlights' => $this->assembler->matchAllowed(array_values(array_unique(array_merge($current['highlights'] ?? [], $ctx->values('highlight')))), (array) $value),
                'cta_text' => $this->schema->plain($value, 40) ?: ($current['cta_text'] ?? ''),
                default => is_string($value) ? ($guard($value) ?: ($current[$field] ?? '')) : ($current[$field] ?? ''),
            };
        }

        try {
            $content = $this->schema->section($section->type, $proposal, $this->websites->allowedMediaIds($website));
        } catch (ValidationException $e) {
            throw new AiException(AiException::INVALID_RESPONSE, 'Regenerated section failed validation: '.json_encode($e->errors()),
                'The AI returned content that did not pass validation. Please try again.');
        }

        return ['content' => $content, 'removed_claims' => array_values(array_unique($removed))];
    }

    private function writableSubset(string $type, array $content): array
    {
        return array_intersect_key($content, array_flip(self::WRITABLE[$type] ?? []));
    }
}
