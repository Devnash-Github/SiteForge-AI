<?php

namespace App\Jobs;

use App\Exceptions\AiException;
use App\Models\Website;
use App\Services\Websites\WebsiteGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateWebsiteJob implements ShouldQueue
{
    use Queueable;

    // The AI client retries internally; a second queue attempt would only duplicate cost.
    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $websiteId, public array $preferences = []) {}

    public function handle(WebsiteGenerationService $generator): void
    {
        $website = Website::with('business')->find($this->websiteId);
        if (! $website) {
            return;
        }

        $website->forceFill(['generation_status' => Website::GEN_PROCESSING, 'generation_error' => null])->save();

        try {
            $result = $generator->generate($website, $this->preferences);
            $website->forceFill(['generation_status' => Website::GEN_COMPLETED, 'generation_error' => null])->save();
            if ($result['removed_claims'] !== []) {
                Log::info('Removed unsupported claims from generated copy', ['website' => $website->id, 'count' => count($result['removed_claims'])]);
            }
        } catch (AiException $e) {
            $website->forceFill(['generation_status' => Website::GEN_FAILED, 'generation_error' => $e->userMessage])->save();
        } catch (Throwable $e) {
            report($e);
            $website->forceFill(['generation_status' => Website::GEN_FAILED, 'generation_error' => 'Website generation failed unexpectedly. Your existing content was not changed.'])->save();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Website::whereKey($this->websiteId)
            ->whereIn('generation_status', [Website::GEN_PENDING, Website::GEN_PROCESSING])
            ->update(['generation_status' => Website::GEN_FAILED, 'generation_error' => 'Website generation did not finish. Your existing content was not changed.']);
    }
}
