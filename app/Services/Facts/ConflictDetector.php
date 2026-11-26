<?php

namespace App\Services\Facts;

use App\Models\Business;
use App\Models\BusinessFact;
use Illuminate\Support\Collection;

class ConflictDetector
{
    /**
     * A conflict exists for a single-value key when its active (non-rejected)
     * facts hold more than one distinct value and the owner has not confirmed
     * all of them.
     *
     * @return array<int, array{key: string, label: string, options: array<int, array{value: string, normalized: string, fact_ids: int[], sources: array<int, array{id: ?int, type: string, label: string}>, confirmed: bool}>, may_keep_several: bool}>
     */
    public function detect(Business $business, ?Collection $facts = null): array
    {
        $facts ??= $business->facts()->with('source:id,type,label,url,file_name')->get();
        $conflicts = [];

        foreach (FactKeys::SINGLE as $key) {
            $active = $facts->where('key', $key)->where('status', '!=', BusinessFact::STATUS_REJECTED);
            $groups = $active->groupBy('normalized_value');
            if ($groups->count() < 2) {
                continue;
            }
            if ($active->every(fn ($f) => $f->status === BusinessFact::STATUS_CONFIRMED)) {
                continue; // the owner deliberately kept several values
            }

            $options = [];
            foreach ($groups as $normalized => $group) {
                $options[] = [
                    'value' => $group->first()->value,
                    'normalized' => (string) $normalized,
                    'fact_ids' => $group->pluck('id')->all(),
                    'sources' => $group->map(fn ($f) => [
                        'id' => $f->source_id,
                        'type' => $f->source_type,
                        'label' => $f->source?->label ?? $this->sourceTypeLabel($f->source_type),
                    ])->unique(fn ($s) => $s['id'].'|'.$s['type'])->values()->all(),
                    'confirmed' => $group->contains(fn ($f) => $f->status === BusinessFact::STATUS_CONFIRMED),
                ];
            }

            $conflicts[] = [
                'key' => $key,
                'label' => FactKeys::label($key),
                'options' => $options,
                'may_keep_several' => in_array($key, FactKeys::MAY_KEEP_SEVERAL, true),
            ];
        }

        return $conflicts;
    }

    /** @return string[] keys currently in conflict */
    public function conflictingKeys(Business $business, ?Collection $facts = null): array
    {
        return array_column($this->detect($business, $facts), 'key');
    }

    public function sourceTypeLabel(string $type): string
    {
        return match ($type) {
            'manual' => 'Added by you',
            'owner' => 'Your business details',
            'text' => 'Your description',
            'pdf' => 'PDF document',
            'website' => 'Website',
            'social' => 'Social profile',
            'google_business' => 'Google Business Profile',
            'search_result' => 'Web search',
            default => ucfirst($type),
        };
    }
}
