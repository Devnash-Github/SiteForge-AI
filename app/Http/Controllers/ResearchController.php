<?php

namespace App\Http\Controllers;

use App\Jobs\ResearchBusinessJob;
use App\Models\Business;
use App\Models\BusinessFact;
use App\Models\Source;
use App\Services\Facts\ConflictDetector;
use App\Services\Facts\FactKeys;
use App\Services\Research\ResearchService;
use App\Services\Websites\WebsiteGenerationService;
use App\Support\Presenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResearchController extends Controller
{
    public function show(Request $request, Business $business, ConflictDetector $detector, ResearchService $research, WebsiteGenerationService $generator): Response
    {
        $this->authorize('view', $business);

        $sources = $business->sources()->latest('id')->get();
        $facts = $business->facts()->with('source:id,type,label,url,file_name')->orderBy('key')->orderBy('id')->get();
        $conflicts = $detector->detect($business, $facts);
        $conflictKeys = array_column($conflicts, 'key');

        $missing = [];
        foreach (FactKeys::IMPORTANT as $key) {
            $keyFacts = $facts->where('key', $key)->where('status', '!=', BusinessFact::STATUS_REJECTED);
            if ($key === 'service') {
                $keyFacts = $facts->whereIn('key', ['service', 'product'])->where('status', '!=', BusinessFact::STATUS_REJECTED);
            }
            if ($keyFacts->isEmpty()) {
                $missing[] = ['key' => $key, 'label' => FactKeys::label($key)];
            }
        }

        $stalled = $sources->contains(fn (Source $s) => $s->status === Source::STATUS_PENDING && $s->created_at->lt(now()->subSeconds(45)));
        $website = $business->websites()->latest('id')->first();

        return Inertia::render('ResearchReview', [
            'business' => Presenter::businessSummary($business),
            'sources' => $sources->map(fn ($s) => Presenter::source($s)),
            'facts' => $facts->map(fn ($f) => Presenter::fact($f, $detector)),
            'conflicts' => $conflicts,
            'conflictKeys' => $conflictKeys,
            'missing' => $missing,
            'factKeys' => collect(FactKeys::all())->map(fn ($k) => ['key' => $k, 'label' => FactKeys::label($k), 'single' => FactKeys::isSingle($k)])->values(),
            'research' => [
                'available' => $research->isAvailable(),
                'provider' => $research->providerName(),
                'status' => $business->research_status,
                'error' => $business->research_error,
            ],
            'processing' => $sources->contains(fn (Source $s) => $s->isInProgress()) || in_array($business->research_status, ['pending', 'processing'], true),
            'queueStalled' => $stalled,
            'aiAvailable' => $generator->aiAvailable(),
            'website' => $website ? ['id' => $website->id, 'generation_status' => $website->generation_status, 'has_content' => $website->sections()->exists()] : null,
            'limits' => ['max_upload_mb' => round(config('siteforge.max_upload_kb') / 1024)],
        ]);
    }

    public function rerun(Request $request, Business $business, ResearchService $research): RedirectResponse
    {
        $this->authorize('update', $business);
        if (! $research->isAvailable()) {
            return back()->with('error', 'Web research is not configured on this server.');
        }
        $business->forceFill(['research_status' => 'pending', 'status' => Business::STATUS_RESEARCHING])->save();
        ResearchBusinessJob::dispatch($business->id);

        return back()->with('success', 'Web research started.');
    }
}
