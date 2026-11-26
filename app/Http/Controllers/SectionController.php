<?php

namespace App\Http\Controllers;

use App\Exceptions\AiException;
use App\Models\Website;
use App\Models\WebsiteSection;
use App\Services\Websites\AiCopyWriter;
use App\Services\Websites\SectionRegenerationService;
use App\Services\Websites\WebsiteSchema;
use App\Services\Websites\WebsiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** JSON endpoints used by the Website Studio (autosave, reorder, AI rewrite). */
class SectionController extends Controller
{
    public function __construct(private WebsiteService $websites) {}

    public function store(Request $request, Website $website): JsonResponse
    {
        $this->authorize('update', $website);
        $data = $request->validate(['type' => ['required', Rule::in(WebsiteSchema::SECTION_TYPES)]]);
        $section = $this->websites->addSection($website, $data['type']);

        return response()->json($this->payload($website));
    }

    public function update(Request $request, Website $website, WebsiteSection $section): JsonResponse
    {
        $this->authorize('update', $website);
        $data = $request->validate([
            'content' => ['sometimes', 'array'],
            'is_visible' => ['sometimes', 'boolean'],
        ]);
        $this->websites->updateSection($section, $data['content'] ?? null, $data['is_visible'] ?? null);

        return response()->json($this->payload($website));
    }

    public function destroy(Request $request, Website $website, WebsiteSection $section): JsonResponse
    {
        $this->authorize('update', $website);
        $this->websites->removeSection($section);

        return response()->json($this->payload($website));
    }

    public function reorder(Request $request, Website $website): JsonResponse
    {
        $this->authorize('update', $website);
        $data = $request->validate(['order' => ['required', 'array', 'min:1'], 'order.*' => ['integer']]);
        $this->websites->reorder($website, $data['order']);

        return response()->json($this->payload($website));
    }

    public function updateTheme(Request $request, Website $website): JsonResponse
    {
        $this->authorize('update', $website);
        $data = $request->validate(['theme' => ['required', 'array']]);
        $this->websites->updateTheme($website, $data['theme']);

        return response()->json($this->payload($website));
    }

    /** Returns a proposal only — the section is not changed until the owner accepts. */
    public function regenerate(Request $request, Website $website, WebsiteSection $section, SectionRegenerationService $regenerator): JsonResponse
    {
        $this->authorize('update', $website);
        $data = $request->validate([
            'instructions' => ['nullable', 'string', 'max:500'],
            'tone' => ['nullable', Rule::in(array_keys(AiCopyWriter::TONES))],
        ]);

        try {
            $result = $regenerator->propose($section, (string) ($data['instructions'] ?? ''), [
                'tone' => $data['tone'] ?? data_get($website->generation_preferences, 'tone', $request->user()->preference('default_tone', 'professional')),
            ]);
        } catch (AiException $e) {
            return response()->json(['message' => $e->userMessage, 'reason' => $e->reason], $e->reason === AiException::RATE_LIMITED ? 429 : 503);
        }

        return response()->json([
            'section_id' => $section->id,
            'proposal' => $result['content'],
            'removed_claims' => $result['removed_claims'],
        ]);
    }

    private function payload(Website $website): array
    {
        $website->refresh()->load('sections', 'business.media');

        return [
            'renderer' => $this->websites->rendererPayload($website),
            'has_unpublished_changes' => $website->hasUnpublishedChanges(),
            'saved_at' => now()->toIso8601String(),
        ];
    }
}
