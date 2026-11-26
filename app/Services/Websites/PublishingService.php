<?php

namespace App\Services\Websites;

use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishingService
{
    public function __construct(
        private SlugService $slugs,
        private WebsiteService $websites,
        private WebsiteSchema $schema,
    ) {}

    /**
     * Publish (or re-publish) the current editor content as an immutable
     * snapshot served at /sites/{slug}.
     */
    public function publish(Website $website, ?string $slug = null): Website
    {
        if ($website->isGenerating()) {
            throw ValidationException::withMessages(['website' => 'Wait for generation to finish before publishing.']);
        }

        $slug = $slug !== null ? strtolower(trim($slug)) : $website->slug;
        if (! $this->slugs->isValid($slug)) {
            throw ValidationException::withMessages(['slug' => 'Use 3–80 lowercase letters, numbers and single hyphens (e.g. glow-salon-mumbai).']);
        }

        return DB::transaction(function () use ($website, $slug) {
            // Lock to avoid two websites claiming the same slug concurrently.
            $taken = Website::where('slug', $slug)->whereKeyNot($website->id)->lockForUpdate()->exists();
            if ($taken || ! $this->slugs->isAvailable($slug, $website->id)) {
                throw ValidationException::withMessages(['slug' => 'This address is already taken. Try another one.']);
            }

            $website->load('sections', 'business.media');
            $visible = $website->sections->where('is_visible', true);
            if ($visible->isEmpty()) {
                throw ValidationException::withMessages(['website' => 'Add or show at least one section before publishing.']);
            }

            // Re-validate everything that goes public.
            $allowed = $website->business->media->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($website->sections as $section) {
                $this->schema->section($section->type, $section->content, $allowed);
            }
            $theme = $this->schema->theme($website->theme ?? ThemePresets::default());

            $website->slug = $slug;
            $payload = $this->websites->rendererPayload($website);
            $payload['theme'] = $theme;
            $payload['sections'] = array_values(array_filter($payload['sections'], fn ($s) => $s['visible']));
            $payload['assets'] = $this->websites->assetsFor($website, $payload['sections']);

            $now = now();
            $website->refresh();
            $website->slug = $slug;
            $website->forceFill([
                'slug' => $slug,
                'status' => Website::STATUS_PUBLISHED,
                'published_content' => $payload,
                'published_at' => $now,
                'published_version' => (int) $website->content_version,
            ])->save();

            return $website;
        });
    }

    public function unpublish(Website $website): Website
    {
        $website->forceFill(['status' => Website::STATUS_UNPUBLISHED])->save();

        return $website;
    }

    /** Change the address of a draft/unpublished website (published ones change it via publish()). */
    public function updateSlug(Website $website, string $slug): Website
    {
        $slug = strtolower(trim($slug));
        if (! $this->slugs->isValid($slug)) {
            throw ValidationException::withMessages(['slug' => 'Use 3–80 lowercase letters, numbers and single hyphens.']);
        }
        if (! $this->slugs->isAvailable($slug, $website->id)) {
            throw ValidationException::withMessages(['slug' => 'This address is already taken. Try another one.']);
        }
        $website->forceFill(['slug' => $slug])->save();

        return $website;
    }
}
