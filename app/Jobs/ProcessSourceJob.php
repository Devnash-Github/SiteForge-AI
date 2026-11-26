<?php

namespace App\Jobs;

use App\Models\Source;
use App\Services\Sources\SourceProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessSourceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public array $backoff = [15];

    public function __construct(public int $sourceId, public bool $snippetOnly = false) {}

    public function handle(SourceProcessingService $service): void
    {
        $source = Source::with('business')->find($this->sourceId);
        if (! $source || ! $source->business) {
            return; // deleted in the meantime
        }

        $this->snippetOnly ? $service->processSnippet($source) : $service->process($source);
    }

    public function failed(?Throwable $exception): void
    {
        $source = Source::with('business')->find($this->sourceId);
        if ($source && $source->isInProgress()) {
            $service = app(SourceProcessingService::class);
            $service->markFinished($source, Source::STATUS_FAILED, 'Processing did not finish (the background job failed). You can retry this source.');
            $service->refreshBusinessStatus($source->business);
        }
    }
}
