<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateWebsiteJob;
use App\Models\Business;
use App\Models\Website;
use App\Services\Websites\AiCopyWriter;
use App\Services\Websites\PublishingService;
use App\Services\Websites\ThemePresets;
use App\Services\Websites\WebsiteGenerationService;
use App\Services\Websites\WebsiteSchema;
use App\Services\Websites\WebsiteService;
use App\Support\Presenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteController extends Controller
{
    public function __construct(private WebsiteService $websites) {}

    public function index(Request $request): Response
    {
        $query = Website::query()->whereHas('business', fn ($q) => $q->where('user_id', $request->user()->id));
        $websites = WebsiteListQuery::apply($query, $request)
            ->with(['business.media', 'sections'])
            ->paginate(12)
            ->withQueryString()
            ->through(fn ($w) => Presenter::websiteCard($w));

        return Inertia::render('Websites/Index', [
            'websites' => $websites,
            'filters' => ['search' => (string) $request->query('search', ''), 'status' => (string) $request->query('status', 'all')],
        ]);
    }

    /** Create an additional website for an existing business. */
    public function store(Request $request, Business $business): RedirectResponse
    {
        $this->authorize('update', $business);
        $website = $this->websites->createForBusiness($business, $request->string('name')->limit(160)->toString() ?: null);

        return redirect()->route('businesses.research', $business)->with('success', 'New website created. Review the facts, then generate it.');
    }

    public function studio(Request $request, Website $website, WebsiteGenerationService $generator): Response
    {
        $this->authorize('update', $website);
        $website->load('business.media', 'sections');

        return Inertia::render('WebsiteStudio', [
            'website' => $this->websiteProps($website),
            'renderer' => $this->websites->rendererPayload($website),
            'media' => $website->business->media->map(fn ($m) => Presenter::media($m))->values(),
            'presets' => ThemePresets::PRESETS,
            'sectionTypes' => collect(WebsiteSchema::SECTION_LABELS)->map(fn ($label, $type) => ['type' => $type, 'label' => $label])->values(),
            'aiAvailable' => $generator->aiAvailable(),
            'tones' => array_keys(AiCopyWriter::TONES),
            'limits' => ['max_upload_mb' => round(config('siteforge.max_upload_kb') / 1024)],
        ]);
    }

    public function generate(Request $request, Website $website): RedirectResponse
    {
        $this->authorize('update', $website);
        $data = $request->validate([
            'tone' => ['nullable', Rule::in(array_keys(AiCopyWriter::TONES))],
            'instructions' => ['nullable', 'string', 'max:500'],
            'use_ai' => ['nullable', 'boolean'],
        ]);

        if ($website->isGenerating()) {
            return redirect()->route('websites.studio', $website)->with('error', 'This website is already being generated.');
        }

        $preferences = [
            'tone' => $data['tone'] ?? $request->user()->preference('default_tone', 'professional'),
            'instructions' => $data['instructions'] ?? '',
            'use_ai' => $data['use_ai'] ?? true,
        ];
        $website->forceFill(['generation_status' => Website::GEN_PENDING, 'generation_error' => null])->save();
        GenerateWebsiteJob::dispatch($website->id, $preferences);

        return redirect()->route('websites.studio', $website);
    }

    public function update(Request $request, Website $website, PublishingService $publishing): RedirectResponse
    {
        $this->authorize('update', $website);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'slug' => ['sometimes', 'required', 'string', 'max:80'],
        ]);
        if (isset($data['name'])) {
            $website->update(['name' => strip_tags($data['name'])]);
        }
        if (isset($data['slug']) && $data['slug'] !== $website->slug) {
            $publishing->updateSlug($website, $data['slug']);
        }

        return back()->with('success', 'Website updated.');
    }

    public function preview(Request $request, Website $website): Response
    {
        $this->authorize('view', $website);
        $website->load('business.media', 'sections');

        return Inertia::render('Preview', [
            'website' => $this->websiteProps($website),
            'renderer' => $this->websites->rendererPayload($website),
        ])->withViewData(['meta' => ['title' => 'Preview · '.$website->name, 'noindex' => true]]);
    }

    public function destroy(Request $request, Website $website): RedirectResponse
    {
        $this->authorize('delete', $website);
        $website->delete();

        return redirect()->route('websites.index')->with('success', 'Website deleted.');
    }

    public function websiteProps(Website $website): array
    {
        return [
            'id' => $website->id,
            'name' => $website->name,
            'slug' => $website->slug,
            'status' => $website->status,
            'generation_status' => $website->generation_status,
            'generation_error' => $website->generation_error,
            'generation_mode' => $website->generation_mode,
            'generation_preferences' => $website->generation_preferences,
            'published_at' => $website->published_at?->toIso8601String(),
            'public_url' => $website->publicUrl(),
            'is_published' => $website->isPublished(),
            'has_unpublished_changes' => $website->hasUnpublishedChanges(),
            'business' => ['id' => $website->business->id, 'name' => $website->business->name, 'city' => $website->business->city],
        ];
    }
}
