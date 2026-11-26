<?php

namespace App\Services\Facts;

use App\Models\Business;
use App\Models\BusinessFact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owner-driven fact management: manual entry, editing, confirmation,
 * rejection and conflict resolution. Nothing here picks a value on its own.
 */
class FactService
{
    public function __construct(private FactNormalizer $normalizer, private ConflictDetector $conflicts) {}

    public function addManual(Business $business, string $key, string $value): BusinessFact
    {
        $value = $this->validated($key, $value);

        return $business->facts()->create([
            'source_id' => null,
            'source_type' => 'manual',
            'key' => $key,
            'value' => $value,
            'normalized_value' => $this->normalizer->normalize($key, $value),
            'status' => BusinessFact::STATUS_CONFIRMED,
            'extracted_at' => now(),
        ]);
    }

    /** Editing a value makes it the owner's own statement, so it becomes confirmed. */
    public function updateValue(BusinessFact $fact, string $value): BusinessFact
    {
        $value = $this->validated($fact->key, $value);
        $fact->update([
            'value' => $value,
            'normalized_value' => $this->normalizer->normalize($fact->key, $value),
            'status' => BusinessFact::STATUS_CONFIRMED,
        ]);

        return $fact;
    }

    public function setStatus(BusinessFact $fact, string $status): BusinessFact
    {
        if (! in_array($status, [BusinessFact::STATUS_CONFIRMED, BusinessFact::STATUS_REJECTED, BusinessFact::STATUS_EXTRACTED], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid status.']);
        }
        // Facts with the same value (from other sources) share the decision.
        $fact->business->facts()
            ->where('key', $fact->key)
            ->where('normalized_value', $fact->normalized_value)
            ->update(['status' => $status, 'updated_at' => now()]);

        return $fact->refresh();
    }

    /** Confirm every extracted fact that is not part of an unresolved conflict. */
    public function confirmAllUnconflicted(Business $business): int
    {
        $conflicting = $this->conflicts->conflictingKeys($business);

        return $business->facts()
            ->where('status', BusinessFact::STATUS_EXTRACTED)
            ->when($conflicting !== [], fn ($q) => $q->whereNotIn('key', $conflicting))
            ->update(['status' => BusinessFact::STATUS_CONFIRMED, 'updated_at' => now()]);
    }

    /**
     * Resolve a conflict: the owner picks one existing value, types a new one,
     * or (for phone/email) keeps every value.
     */
    public function resolveConflict(Business $business, string $key, ?int $factId, ?string $manualValue, bool $keepAll = false): void
    {
        if (! FactKeys::isSingle($key)) {
            throw ValidationException::withMessages(['key' => 'This information type cannot have conflicts.']);
        }

        DB::transaction(function () use ($business, $key, $factId, $manualValue, $keepAll) {
            $query = $business->facts()->where('key', $key)->where('status', '!=', BusinessFact::STATUS_REJECTED);

            if ($keepAll) {
                if (! in_array($key, FactKeys::MAY_KEEP_SEVERAL, true)) {
                    throw ValidationException::withMessages(['key' => 'Only one value can be kept for this information.']);
                }
                $query->update(['status' => BusinessFact::STATUS_CONFIRMED, 'updated_at' => now()]);

                return;
            }

            if ($manualValue !== null && trim($manualValue) !== '') {
                $chosen = $this->addManual($business, $key, $manualValue);
            } else {
                $chosen = $business->facts()->where('key', $key)->whereKey($factId)->first();
                if (! $chosen) {
                    throw ValidationException::withMessages(['fact_id' => 'Choose one of the values or enter the correct one.']);
                }
            }

            $business->facts()->where('key', $key)
                ->where('normalized_value', $chosen->normalized_value)
                ->update(['status' => BusinessFact::STATUS_CONFIRMED, 'updated_at' => now()]);

            $business->facts()->where('key', $key)
                ->where('normalized_value', '!=', $chosen->normalized_value)
                ->update(['status' => BusinessFact::STATUS_REJECTED, 'updated_at' => now()]);
        });
    }

    private function validated(string $key, string $value): string
    {
        if (! FactKeys::isValid($key)) {
            throw ValidationException::withMessages(['key' => 'Unknown information type.']);
        }
        $clean = $this->normalizer->clean($key, $value);
        if (! $this->normalizer->isValidValue($key, $clean)) {
            $message = match ($key) {
                'email' => 'Enter a valid email address.',
                'phone' => 'Enter a valid phone number (7–15 digits).',
                default => 'Enter a value.',
            };
            throw ValidationException::withMessages(['value' => $message]);
        }

        return $clean;
    }
}
