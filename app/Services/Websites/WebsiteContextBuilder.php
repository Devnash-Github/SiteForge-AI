<?php

namespace App\Services\Websites;

use App\Models\Business;
use App\Models\BusinessFact;

class WebsiteContextBuilder
{
    public function build(Business $business): WebsiteContext
    {
        $facts = [];
        $confirmed = $business->facts()
            ->where('status', BusinessFact::STATUS_CONFIRMED)
            ->orderByRaw("CASE WHEN source_type IN ('manual','owner','text') THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get(['key', 'value', 'normalized_value']);

        foreach ($confirmed->groupBy('key') as $key => $group) {
            $facts[$key] = $group->unique('normalized_value')->pluck('value')->values()->all();
        }

        $images = $business->media()->where('mime_type', 'like', 'image/%')->orderBy('id')->get();

        $ownerNotes = trim(implode("\n\n", array_filter([$business->description, $business->additional_info])));

        return new WebsiteContext($business, $facts, $images, $ownerNotes);
    }
}
