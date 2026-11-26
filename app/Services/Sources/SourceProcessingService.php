<?php

namespace App\Services\Sources;

use App\Exceptions\SourceBlockedException;
use App\Exceptions\SourceProcessingException;
use App\Models\Business;
use App\Models\Source;
use App\Services\Facts\FactExtractionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SourceProcessingService
{
    private const SOCIAL_HOSTS = ['instagram.com', 'facebook.com', 'fb.com', 'm.facebook.com', 'linkedin.com', 'x.com', 'twitter.com', 'tiktok.com', 'youtube.com', 'threads.net', 'pinterest.com'];

    public function __construct(
        private PdfTextExtractor $pdf,
        private UrlFetcher $fetcher,
        private HtmlContentExtractor $html,
        private FactExtractionService $facts,
    ) {}

    public function process(Source $source): Source
    {
        $source->forceFill(['status' => Source::STATUS_PROCESSING, 'error_message' => null])->save();

        try {
            match ($source->type) {
                Source::TYPE_TEXT => $this->processText($source),
                Source::TYPE_PDF => $this->processPdf($source),
                Source::TYPE_IMAGE => null, // stored as media for the gallery; no text to extract
                Source::TYPE_WEBSITE, Source::TYPE_SOCIAL, Source::TYPE_GOOGLE_BUSINESS, Source::TYPE_SEARCH_RESULT => $this->processUrl($source),
                default => throw new SourceProcessingException('Unsupported source type.'),
            };

            $source->forceFill(['status' => Source::STATUS_COMPLETED, 'processed_at' => now()])->save();

            if ($source->type !== Source::TYPE_IMAGE && filled($source->extracted_content)) {
                $this->facts->extractFromSource($source);
            }
        } catch (SourceBlockedException $e) {
            $this->markFinished($source, Source::STATUS_BLOCKED, $e->getMessage().' You can add these details manually instead.');
        } catch (SourceProcessingException $e) {
            $this->markFinished($source, Source::STATUS_FAILED, $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Source processing crashed', ['source' => $source->id, 'error' => $e->getMessage()]);
            report($e);
            $this->markFinished($source, Source::STATUS_FAILED, 'Something went wrong while processing this source. You can retry it.');
        }

        $this->refreshBusinessStatus($source->business);

        return $source->refresh();
    }

    /** Search results the owner has not confirmed yet: keep only the public snippet, never fetch the page. */
    public function processSnippet(Source $source): Source
    {
        $source->forceFill([
            'status' => Source::STATUS_COMPLETED,
            'processed_at' => now(),
            'error_message' => null,
        ])->save();

        if (filled($source->extracted_content)) {
            try {
                $this->facts->extractFromSource($source);
            } catch (Throwable $e) {
                report($e);
            }
        }
        $this->refreshBusinessStatus($source->business);

        return $source->refresh();
    }

    public function markFinished(Source $source, string $status, string $message): void
    {
        $source->forceFill(['status' => $status, 'error_message' => $message, 'processed_at' => now()])->save();
    }

    public function refreshBusinessStatus(Business $business): void
    {
        $busy = $business->sources()->whereIn('status', [Source::STATUS_PENDING, Source::STATUS_PROCESSING])->exists()
            || in_array($business->research_status, ['pending', 'processing'], true);

        $business->forceFill(['status' => $busy ? Business::STATUS_RESEARCHING : Business::STATUS_READY])->save();
    }

    public static function typeForUrl(string $url, string $declared = Source::TYPE_WEBSITE): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        if (preg_match('/(^|\.)google\.[a-z.]+$/', $host) && str_contains(strtolower($url), 'maps') || in_array($host, ['maps.app.goo.gl', 'g.page', 'goo.gl'], true)) {
            return Source::TYPE_GOOGLE_BUSINESS;
        }
        foreach (self::SOCIAL_HOSTS as $social) {
            if ($host === $social || str_ends_with($host, '.'.$social)) {
                return Source::TYPE_SOCIAL;
            }
        }

        return $declared === Source::TYPE_SEARCH_RESULT ? Source::TYPE_SEARCH_RESULT : Source::TYPE_WEBSITE;
    }

    private function processText(Source $source): void
    {
        if (trim((string) $source->extracted_content) === '') {
            throw new SourceProcessingException('This text source is empty.');
        }
    }

    private function processPdf(Source $source): void
    {
        $disk = Storage::disk((string) data_get($source->metadata, 'disk', config('siteforge.media_disk')));
        if (! $source->file_path || ! $disk->exists($source->file_path)) {
            throw new SourceProcessingException('The uploaded file could not be found. Please upload it again.');
        }

        $result = $this->pdf->extract($disk->path($source->file_path));
        $source->extracted_content = $result['text'];
        $source->metadata = array_merge($source->metadata ?? [], ['pages' => $result['pages'], 'truncated' => $result['truncated']]);
        $source->save();
    }

    private function processUrl(Source $source): void
    {
        $isSocial = in_array($source->type, [Source::TYPE_SOCIAL, Source::TYPE_GOOGLE_BUSINESS], true);
        $snippet = (string) data_get($source->metadata, 'snippet', '');

        try {
            $page = $this->fetcher->fetch((string) $source->url);
        } catch (SourceProcessingException $e) {
            if ($source->type === Source::TYPE_SEARCH_RESULT && $snippet !== '') {
                // Keep the search snippet even when the page itself is not reachable.
                $source->extracted_content = $this->searchDocument($source, null);
                $source->metadata = array_merge($source->metadata ?? [], ['page_error' => $e->getMessage()]);
                $source->save();

                return;
            }
            throw $e;
        }

        if ($this->fetcher->looksLikeLoginPage($page['url'])) {
            throw new SourceBlockedException('This page redirected to a sign-in screen, so its content is not publicly accessible.');
        }

        $extracted = $this->html->extract($page['body'], $page['url']);
        $document = $this->html->toDocument($extracted);

        if ($isSocial) {
            $useful = trim(($extracted['title'] ?? '').' '.($extracted['description'] ?? ''));
            $loginWall = preg_match('/\b(log ?in|sign ?up|create an account)\b/i', mb_substr($extracted['text'], 0, 600)) && mb_strlen($extracted['text']) < 1500;
            if (mb_strlen($useful) < 25 && ($loginWall || mb_strlen($extracted['text']) < 200)) {
                throw new SourceBlockedException('This profile only shows content to signed-in visitors.');
            }
            // Social pages: rely on the public profile metadata only.
            $document = trim(implode("\n", array_filter([
                $extracted['title'] ? 'Profile title: '.$extracted['title'] : null,
                $extracted['description'] ? 'Profile description: '.$extracted['description'] : null,
                $extracted['structured'] ? $this->html->toDocument(['structured' => $extracted['structured']]) : null,
            ])));
        }

        $pages = [$page['url']];
        if ($source->type === Source::TYPE_WEBSITE) {
            foreach (array_slice($extracted['links'], 0, (int) config('siteforge.fetch.max_extra_pages', 3)) as $link) {
                if (in_array($link, $pages, true)) {
                    continue;
                }
                try {
                    $sub = $this->fetcher->fetch($link);
                    $subExtracted = $this->html->extract($sub['body'], $sub['url']);
                    $document .= "\n\n--- Page: ".parse_url($sub['url'], PHP_URL_PATH)." ---\n".$this->html->toDocument($subExtracted);
                    $extracted['phones'] = array_values(array_unique(array_merge($extracted['phones'], $subExtracted['phones'])));
                    $extracted['emails'] = array_values(array_unique(array_merge($extracted['emails'], $subExtracted['emails'])));
                    $extracted['structured'] = $extracted['structured'] ?: $subExtracted['structured'];
                    $pages[] = $sub['url'];
                } catch (SourceProcessingException) {
                    continue;
                }
            }
        }

        if ($source->type === Source::TYPE_SEARCH_RESULT) {
            $document = $this->searchDocument($source, $document);
        }

        if (mb_strlen(trim($document)) < 20) {
            throw new SourceProcessingException('The page loaded but contained no readable business information (it may rely on JavaScript).');
        }

        $source->extracted_content = mb_substr($document, 0, 60000);
        $source->metadata = array_merge($source->metadata ?? [], [
            'final_url' => $page['url'],
            'pages' => $pages,
            'page_title' => $extracted['title'],
            'structured' => $extracted['structured'],
            'phones' => $isSocial ? [] : $extracted['phones'],
            'emails' => $isSocial ? [] : $extracted['emails'],
        ]);
        $source->save();
    }

    private function searchDocument(Source $source, ?string $page): string
    {
        $parts = array_filter([
            'Search result title: '.data_get($source->metadata, 'title', ''),
            'Search result snippet: '.data_get($source->metadata, 'snippet', ''),
            $page ? "\n".$page : null,
        ]);

        return trim(implode("\n", $parts));
    }
}
