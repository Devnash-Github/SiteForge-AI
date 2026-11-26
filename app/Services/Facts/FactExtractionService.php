<?php

namespace App\Services\Facts;

use App\Exceptions\AiException;
use App\Models\BusinessFact;
use App\Models\Source;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FactExtractionService
{
    public function __construct(
        private RuleBasedFactExtractor $rules,
        private AiFactExtractor $ai,
        private FactGrounding $grounding,
        private FactNormalizer $normalizer,
    ) {}

    /**
     * Extract facts from a processed source and store them.
     *
     * @return array{created: int, ai_used: bool, ai_error: ?string, mismatch: bool}
     */
    public function extractFromSource(Source $source): array
    {
        $text = (string) $source->extracted_content;
        $business = $source->business;
        $metadata = $source->metadata ?? [];

        $candidates = $this->rules->extract($text, $metadata);
        $aiUsed = false;
        $aiError = null;
        $mismatch = false;

        if ($this->ai->available() && mb_strlen(trim($text)) >= 30 && $source->type !== Source::TYPE_IMAGE) {
            try {
                $result = $this->ai->extract($business, $source, $text);
                $aiUsed = true;
                $mismatch = ! $result['about_this_business'];
                if (! $mismatch) {
                    $candidates = array_merge($candidates, $result['facts']);
                }
            } catch (AiException $e) {
                $aiError = $e->userMessage;
                Log::info('AI fact extraction skipped', ['source' => $source->id, 'reason' => $e->reason]);
            }
        }

        if ($mismatch && $source->type === Source::TYPE_SEARCH_RESULT) {
            // The page appears to describe a different business with a similar name.
            $source->identity_status = Source::IDENTITY_UNCERTAIN;
        }

        $created = DB::transaction(fn () => $this->store($source, $candidates, $text));

        $source->metadata = array_merge($metadata, [
            'ai_extraction' => $aiUsed,
            'ai_error' => $aiError,
            'facts_found' => $created,
            'identity_mismatch' => $mismatch,
        ]);
        $source->save();

        return ['created' => $created, 'ai_used' => $aiUsed, 'ai_error' => $aiError, 'mismatch' => $mismatch];
    }

    /** @param array<int, array{key: string, value: string}> $candidates */
    private function store(Source $source, array $candidates, string $text): int
    {
        // Re-processing replaces earlier undecided facts from this source; user decisions are kept.
        $source->facts()->whereIn('status', [BusinessFact::STATUS_EXTRACTED, BusinessFact::STATUS_NEEDS_REVIEW])->delete();

        $business = $source->business;
        $existing = $business->facts()->get(['key', 'normalized_value', 'status', 'source_id']);
        $trusted = (bool) data_get($source->metadata, 'trusted', false);
        $baseStatus = $trusted ? BusinessFact::STATUS_CONFIRMED
            : ($source->identity_status === Source::IDENTITY_UNCERTAIN ? BusinessFact::STATUS_NEEDS_REVIEW : BusinessFact::STATUS_EXTRACTED);

        $seen = [];
        $created = 0;
        foreach ($candidates as $candidate) {
            $key = $candidate['key'];
            if (! FactKeys::isValid($key)) {
                continue;
            }
            $value = $this->normalizer->clean($key, $candidate['value']);
            if (! $this->normalizer->isValidValue($key, $value)) {
                continue;
            }
            if (! $this->grounding->isGrounded($key, $value, $text)) {
                Log::info('Dropped ungrounded fact', ['source' => $source->id, 'key' => $key]);

                continue;
            }
            $normalized = $this->normalizer->normalize($key, $value);
            if ($normalized === '' || isset($seen[$key.'|'.$normalized])) {
                continue;
            }
            $seen[$key.'|'.$normalized] = true;

            // If the owner already decided on this exact value elsewhere, honour that decision.
            $decided = $existing->first(fn ($f) => $f->key === $key && $f->normalized_value === $normalized
                && in_array($f->status, [BusinessFact::STATUS_CONFIRMED, BusinessFact::STATUS_REJECTED], true)
                && $f->source_id !== $source->id);
            $status = $decided && $baseStatus !== BusinessFact::STATUS_NEEDS_REVIEW ? $decided->status : $baseStatus;

            $business->facts()->create([
                'source_id' => $source->id,
                'source_type' => $source->type,
                'key' => $key,
                'value' => $value,
                'normalized_value' => $normalized,
                'status' => $status,
                'extracted_at' => now(),
            ]);
            $created++;
        }

        return $created;
    }
}
