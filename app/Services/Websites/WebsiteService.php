<?php

namespace App\Services\Websites;

use App\Models\Business;
use App\Models\Website;
use App\Models\WebsiteSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WebsiteService
{
    public function __construct(private WebsiteSchema $schema, private SlugService $slugs) {}

    public function createForBusiness(Business $business, ?string $name = null): Website
    {
        return $business->websites()->create([
            'name' => $name ?: $business->name,
            'slug' => $this->slugs->suggest($business->name, $business->city),
            'theme' => ThemePresets::default(),
            'status' => Website::STATUS_DRAFT,
            'generation_status' => Website::GEN_IDLE,
        ]);
    }

    /** @return int[] */
    public function allowedMediaIds(Website $website): array
    {
        return $website->business->media()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Replace all sections atomically (used after a successful generation).
     *
     * @param  array<int, array{type: string, content: array, is_visible: bool}>  $sections
     */
    public function replaceSections(Website $website, array $sections, array $attributes = []): void
    {
        $allowed = $this->allowedMediaIds($website);
        $validated = [];
        foreach (array_values($sections) as $i => $section) {
            $validated[] = [
                'type' => $section['type'],
                'content' => $this->schema->section($section['type'], $section['content'], $allowed),
                'is_visible' => (bool) $section['is_visible'],
                'position' => $i,
            ];
        }

        DB::transaction(function () use ($website, $validated, $attributes) {
            $website->sections()->delete();
            foreach ($validated as $row) {
                $website->sections()->create($row);
            }
            $website->forceFill(array_merge($attributes, ['content_updated_at' => now(), 'content_version' => (int) $website->content_version + 1]))->save();
        });
    }

    public function updateSection(WebsiteSection $section, ?array $content, ?bool $visible): WebsiteSection
    {
        $website = $section->website;
        if ($content !== null) {
            $section->content = $this->schema->section($section->type, $content, $this->allowedMediaIds($website));
        }
        if ($visible !== null) {
            $section->is_visible = $visible;
        }
        $section->save();
        $website->touchContent();

        return $section;
    }

    public function addSection(Website $website, string $type): WebsiteSection
    {
        if (! in_array($type, WebsiteSchema::SECTION_TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Unsupported section type.']);
        }
        if (in_array($type, WebsiteSchema::UNIQUE_TYPES, true) && $website->sections()->where('type', $type)->exists()) {
            throw ValidationException::withMessages(['type' => 'This website already has a '.WebsiteSchema::SECTION_LABELS[$type].' section.']);
        }

        return DB::transaction(function () use ($website, $type) {
            $sections = $website->sections()->get();
            $footer = $sections->firstWhere('type', 'footer');
            $position = $type === 'footer' || ! $footer ? $sections->count() : $footer->position;
            if ($footer && $type !== 'footer') {
                $footer->update(['position' => $footer->position + 1]);
            }

            $section = $website->sections()->create([
                'type' => $type,
                'content' => $this->schema->section($type, WebsiteSchema::defaultContent($type, $website->business->name), null),
                'position' => $position,
                'is_visible' => true,
            ]);
            $website->touchContent();

            return $section;
        });
    }

    public function removeSection(WebsiteSection $section): void
    {
        $website = $section->website;
        DB::transaction(function () use ($section, $website) {
            $section->delete();
            $this->normalisePositions($website);
        });
        $website->touchContent();
    }

    /** @param int[] $orderedIds */
    public function reorder(Website $website, array $orderedIds): void
    {
        $existing = $website->sections()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $given = collect($orderedIds)->map(fn ($id) => (int) $id);
        if ($given->sort()->values()->all() !== $existing) {
            throw ValidationException::withMessages(['order' => 'The section list is out of date. Reload the page and try again.']);
        }

        DB::transaction(function () use ($website, $given) {
            foreach ($given->values() as $position => $id) {
                $website->sections()->whereKey($id)->update(['position' => $position]);
            }
        });
        $website->touchContent();
    }

    public function updateTheme(Website $website, array $theme): Website
    {
        $website->forceFill(['theme' => $this->schema->theme($theme), 'content_updated_at' => now(), 'content_version' => (int) $website->content_version + 1])->save();

        return $website;
    }

    /**
     * Data for the shared React renderer. Uses the live editor content, or
     * the published snapshot when $published is true.
     */
    public function rendererPayload(Website $website, bool $published = false): array
    {
        if ($published) {
            return $website->published_content ?? [];
        }

        $website->loadMissing('sections', 'business.media');
        $sections = $website->sections->map->toRenderer()->values()->all();

        return [
            'theme' => $website->theme ?? ThemePresets::default(),
            'sections' => $sections,
            'assets' => $this->assetsFor($website, $sections),
            'business' => ['name' => $website->business->name, 'city' => $website->business->city],
        ];
    }

    /** Only images referenced by sections are exposed. */
    public function assetsFor(Website $website, array $sections): array
    {
        $ids = [];
        foreach ($sections as $s) {
            $c = $s['content'] ?? [];
            if (! empty($c['image_id'])) {
                $ids[] = (int) $c['image_id'];
            }
            foreach ($c['image_ids'] ?? [] as $id) {
                $ids[] = (int) $id;
            }
        }
        $ids = array_unique($ids);

        return $website->business->media
            ->whereIn('id', $ids)
            ->mapWithKeys(fn ($m) => [$m->id => $m->toRendererAsset()])
            ->all();
    }

    private function normalisePositions(Website $website): void
    {
        foreach ($website->sections()->get() as $i => $s) {
            if ($s->position !== $i) {
                $s->update(['position' => $i]);
            }
        }
    }
}
