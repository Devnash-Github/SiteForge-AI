<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadRules;
use App\Models\Business;
use App\Models\Media;
use App\Models\Website;
use App\Services\Sources\MediaService;
use App\Support\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function __construct(private MediaService $media) {}

    public function store(Request $request, Business $business): JsonResponse
    {
        $this->authorize('update', $business);
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:'.config('siteforge.max_files_per_request', 20)],
            'files.*' => UploadRules::file(imagesOnly: true),
        ], UploadRules::messages() + ['files.*.mimes' => 'Only JPG, PNG and WebP images are supported.']);

        $created = [];
        foreach ($request->file('files') as $file) {
            $created[] = Presenter::media($this->media->storeImage($business, $file));
        }

        return response()->json(['media' => $created], 201);
    }

    public function update(Request $request, Business $business, Media $media): JsonResponse
    {
        $this->authorize('update', $business);
        $data = $request->validate(['alt_text' => ['nullable', 'string', 'max:255']]);
        $media->update(['alt_text' => strip_tags((string) ($data['alt_text'] ?? ''))]);

        return response()->json(['media' => Presenter::media($media)]);
    }

    public function destroy(Request $request, Business $business, Media $media): JsonResponse
    {
        $this->authorize('update', $business);

        // Remove references from draft sections so nothing points at a missing image.
        foreach ($business->websites()->with('sections')->get() as $website) {
            foreach ($website->sections as $section) {
                $content = $section->content;
                $changed = false;
                if (($content['image_id'] ?? null) === $media->id) {
                    $content['image_id'] = null;
                    $changed = true;
                }
                if (isset($content['image_ids']) && in_array($media->id, $content['image_ids'], true)) {
                    $content['image_ids'] = array_values(array_diff($content['image_ids'], [$media->id]));
                    $changed = true;
                }
                if ($changed) {
                    $section->update(['content' => $content]);
                    $website->touchContent();
                }
            }
        }
        // Published snapshots must not point at a file that no longer exists.
        foreach ($business->websites()->whereNotNull('published_content')->get() as $website) {
            $snapshot = $website->published_content;
            if (! array_key_exists($media->id, (array) ($snapshot['assets'] ?? []))) {
                continue;
            }
            unset($snapshot['assets'][$media->id]);
            foreach ($snapshot['sections'] ?? [] as $i => $section) {
                if (($section['content']['image_id'] ?? null) === $media->id) {
                    $snapshot['sections'][$i]['content']['image_id'] = null;
                }
                if (isset($section['content']['image_ids'])) {
                    $snapshot['sections'][$i]['content']['image_ids'] = array_values(array_diff($section['content']['image_ids'], [$media->id]));
                }
            }
            $website->forceFill(['published_content' => $snapshot])->save();
        }

        $this->media->delete($media);

        return response()->json(['deleted' => true]);
    }

    /**
     * Images are served through this route: owners can always see them; the
     * public can only see images that appear on a currently published site.
     */
    public function show(Request $request, string $uuid): StreamedResponse
    {
        $media = Media::where('uuid', $uuid)->firstOrFail();
        $business = $media->business;

        $isOwner = $request->user() && $business->user_id === $request->user()->id;
        $isPublic = ! $isOwner && $business->websites()
            ->where('status', Website::STATUS_PUBLISHED)
            ->get(['id', 'published_content'])
            ->contains(fn ($w) => array_key_exists((string) $media->id, (array) data_get($w->published_content, 'assets', [])));

        abort_unless($isOwner || $isPublic, 404);

        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->file_path), 404);

        return $disk->response($media->file_path, $media->file_name, [
            'Content-Type' => $media->mime_type,
            'Cache-Control' => $isPublic ? 'public, max-age=86400' : 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ], 'inline');
    }
}
