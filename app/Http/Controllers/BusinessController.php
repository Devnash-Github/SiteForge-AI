<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessRequest;
use App\Models\Business;
use App\Services\Business\BusinessService;
use App\Support\Presenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function __construct(private BusinessService $service) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $businesses = $request->user()->businesses()
            ->withCount(['websites', 'sources', 'media', 'facts as confirmed_facts_count' => fn ($q) => $q->where('status', 'confirmed')])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%")))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Business $b) => [
                ...Presenter::businessSummary($b),
                'websites_count' => $b->websites_count,
                'sources_count' => $b->sources_count,
                'media_count' => $b->media_count,
                'confirmed_facts_count' => $b->confirmed_facts_count,
            ]);

        return Inertia::render('Businesses/Index', ['businesses' => $businesses, 'filters' => ['search' => $search]]);
    }

    public function create(): Response
    {
        return Inertia::render('CreateWebsite', [
            'limits' => [
                'max_upload_mb' => round(config('siteforge.max_upload_kb') / 1024),
                'max_files' => config('siteforge.max_files_per_request'),
            ],
        ]);
    }

    public function store(BusinessRequest $request): RedirectResponse
    {
        $result = $this->service->create($request->user(), $request->validated(), $request->file('files', []));

        return redirect()->route('businesses.research', $result['business'])
            ->with('success', 'Business saved. We’re reading your information now.');
    }

    public function show(Request $request, Business $business): Response
    {
        $this->authorize('view', $business);
        $business->load(['websites' => fn ($q) => $q->with('sections')->latest(), 'media']);

        return Inertia::render('Businesses/Show', [
            'business' => Presenter::businessSummary($business),
            'websites' => $business->websites->map(fn ($w) => Presenter::websiteCard($w->setRelation('business', $business))),
            'counts' => [
                'sources' => $business->sources()->count(),
                'facts' => $business->facts()->where('status', 'confirmed')->count(),
                'media' => $business->media->count(),
            ],
        ]);
    }

    public function update(BusinessRequest $request, Business $business): RedirectResponse
    {
        $this->service->update($business, $request->validated());

        return back()->with('success', 'Business details updated.');
    }

    public function destroy(Request $request, Business $business): RedirectResponse
    {
        $this->authorize('delete', $business);
        $this->service->delete($business);

        return redirect()->route('businesses.index')->with('success', 'Business and its websites were deleted.');
    }
}
