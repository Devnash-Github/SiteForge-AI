<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadRules;
use App\Jobs\ProcessSourceJob;
use App\Models\Business;
use App\Models\BusinessFact;
use App\Models\Source;
use App\Services\Business\BusinessService;
use App\Services\Sources\SourceProcessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SourceController extends Controller
{
    public function store(Request $request, Business $business, BusinessService $service): RedirectResponse
    {
        $this->authorize('update', $business);
        if ($request->filled('url') && ! preg_match('#^https?://#i', (string) $request->input('url'))) {
            $request->merge(['url' => 'https://'.trim((string) $request->input('url'))]);
        }
        $data = $request->validate([
            'url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'text' => ['nullable', 'string', 'max:20000'],
            'files' => ['nullable', 'array', 'max:'.config('siteforge.max_files_per_request', 20)],
            'files.*' => UploadRules::file(),
        ], UploadRules::messages());

        if (empty($data['url']) && empty($data['text']) && ! $request->hasFile('files')) {
            return back()->withErrors(['url' => 'Add a link, some text or a file.']);
        }

        $sources = $service->addSources($business, $data['url'] ?? null, $data['text'] ?? null, $request->file('files', []));

        return back()->with('success', count($sources) === 1 ? 'Source added and queued for processing.' : count($sources).' sources added and queued for processing.');
    }

    public function retry(Request $request, Business $business, Source $source): RedirectResponse
    {
        $this->authorize('update', $business);
        if ($source->type === Source::TYPE_SEARCH_RESULT && $source->identity_status === Source::IDENTITY_UNCERTAIN) {
            return back()->with('error', 'Confirm that this result is your business before processing it.');
        }
        $source->forceFill(['status' => Source::STATUS_PENDING, 'error_message' => null])->save();
        $business->forceFill(['status' => Business::STATUS_RESEARCHING])->save();
        ProcessSourceJob::dispatch($source->id);

        return back()->with('success', 'Processing restarted.');
    }

    public function identity(Request $request, Business $business, Source $source, SourceProcessingService $processing): RedirectResponse
    {
        $this->authorize('update', $business);
        $data = $request->validate(['decision' => ['required', 'in:confirm,reject']]);

        if ($data['decision'] === 'reject') {
            $source->facts()->delete();
            $source->forceFill(['identity_status' => Source::IDENTITY_REJECTED])->save();

            return back()->with('success', 'Marked as a different business. Its information will not be used.');
        }

        $source->forceFill(['identity_status' => Source::IDENTITY_CONFIRMED])->save();
        $source->facts()->where('status', BusinessFact::STATUS_NEEDS_REVIEW)->update(['status' => BusinessFact::STATUS_EXTRACTED]);

        if (data_get($source->metadata, 'snippet_only') && SourceProcessingService::typeForUrl((string) $source->url) !== Source::TYPE_SOCIAL) {
            // Now that the owner confirmed it, read the full page.
            $source->forceFill(['status' => Source::STATUS_PENDING, 'metadata' => array_merge($source->metadata ?? [], ['snippet_only' => false])])->save();
            ProcessSourceJob::dispatch($source->id);
        }

        return back()->with('success', 'Confirmed as your business.');
    }

    public function destroy(Request $request, Business $business, Source $source, SourceProcessingService $processing): RedirectResponse
    {
        $this->authorize('update', $business);

        // Undecided facts from this source go with it; confirmed/rejected decisions are kept.
        $source->facts()->whereIn('status', [BusinessFact::STATUS_EXTRACTED, BusinessFact::STATUS_NEEDS_REVIEW])->delete();
        if ($source->type === Source::TYPE_PDF && $source->file_path) {
            Storage::disk((string) data_get($source->metadata, 'disk', config('siteforge.media_disk')))->delete($source->file_path);
        }
        $source->delete();
        $processing->refreshBusinessStatus($business);

        return back()->with('success', 'Source removed.');
    }
}
