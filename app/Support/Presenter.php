<?php

namespace App\Support;

use App\Models\Business;
use App\Models\BusinessFact;
use App\Models\Media;
use App\Models\Source;
use App\Models\Website;
use App\Services\Facts\ConflictDetector;
use App\Services\Facts\FactKeys;

/** Consistent array shapes for Inertia props. */
final class Presenter
{
    public static function websiteCard(Website $website): array
    {
        $business = $website->business;
        $sections = $website->relationLoaded('sections') ? $website->sections : collect();
        $preview = $sections->where('is_visible', true)->take(2)->values();
        $mediaIds = $preview->flatMap(fn ($s) => array_filter([data_get($s->content, 'image_id')]))->all();

        return [
            'id' => $website->id,
            'name' => $website->name,
            'slug' => $website->slug,
            'status' => $website->status,
            'generation_status' => $website->generation_status,
            'business' => ['id' => $business->id, 'name' => $business->name, 'city' => $business->city],
            'updated_at' => ($website->content_updated_at ?? $website->updated_at)?->toIso8601String(),
            'published_at' => $website->published_at?->toIso8601String(),
            'public_url' => $website->isPublished() ? $website->publicUrl() : null,
            'has_unpublished_changes' => $website->hasUnpublishedChanges(),
            'section_count' => $sections->count(),
            'thumbnail' => $sections->isEmpty() ? null : [
                'theme' => $website->theme,
                'sections' => $preview->map->toRenderer()->all(),
                'assets' => $business->relationLoaded('media')
                    ? $business->media->whereIn('id', $mediaIds)->mapWithKeys(fn ($m) => [$m->id => $m->toRendererAsset()])->all()
                    : [],
                'business' => ['name' => $business->name, 'city' => $business->city],
            ],
        ];
    }

    public static function businessSummary(Business $business): array
    {
        return [
            'id' => $business->id,
            'name' => $business->name,
            'city' => $business->city,
            'category' => $business->category,
            'description' => $business->description,
            'additional_info' => $business->additional_info,
            'website_url' => $business->website_url,
            'instagram_url' => $business->instagram_url,
            'facebook_url' => $business->facebook_url,
            'linkedin_url' => $business->linkedin_url,
            'google_business_url' => $business->google_business_url,
            'other_urls' => $business->other_urls ?? [],
            'status' => $business->status,
            'research_status' => $business->research_status,
            'research_error' => $business->research_error,
            'updated_at' => $business->updated_at?->toIso8601String(),
        ];
    }

    public static function source(Source $source): array
    {
        return [
            'id' => $source->id,
            'type' => $source->type,
            'label' => $source->label ?: ($source->url ?: $source->file_name),
            'url' => $source->url,
            'file_name' => $source->file_name,
            'status' => $source->status,
            'error_message' => $source->error_message,
            'identity_status' => $source->identity_status,
            'match_reasons' => data_get($source->metadata, 'match_reasons', []),
            'snippet' => data_get($source->metadata, 'snippet'),
            'facts_found' => data_get($source->metadata, 'facts_found'),
            'ai_error' => data_get($source->metadata, 'ai_error'),
            'page_error' => data_get($source->metadata, 'page_error'),
            'pages' => data_get($source->metadata, 'pages') ? (is_array(data_get($source->metadata, 'pages')) ? count(data_get($source->metadata, 'pages')) : (int) data_get($source->metadata, 'pages')) : null,
            'content_length' => mb_strlen((string) $source->extracted_content),
            'created_at' => $source->created_at?->toIso8601String(),
            'processed_at' => $source->processed_at?->toIso8601String(),
        ];
    }

    public static function fact(BusinessFact $fact, ConflictDetector $detector): array
    {
        return [
            'id' => $fact->id,
            'key' => $fact->key,
            'label' => FactKeys::label($fact->key),
            'value' => $fact->value,
            'normalized_value' => $fact->normalized_value,
            'status' => $fact->status,
            'source_id' => $fact->source_id,
            'source_type' => $fact->source_type,
            'source_label' => $fact->source?->label ?? $detector->sourceTypeLabel($fact->source_type),
            'extracted_at' => $fact->extracted_at?->toIso8601String(),
        ];
    }

    public static function media(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url,
            'file_name' => $media->file_name,
            'alt_text' => $media->alt_text,
            'size' => $media->size,
            'width' => $media->width,
            'height' => $media->height,
            'mime_type' => $media->mime_type,
        ];
    }
}
