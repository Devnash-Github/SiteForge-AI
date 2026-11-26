<?php

namespace App\Jobs;

use App\Models\Business;
use App\Services\Research\ResearchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ResearchBusinessJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public array $backoff = [30];

    public function __construct(public int $businessId) {}

    public function handle(ResearchService $research): void
    {
        $business = Business::find($this->businessId);
        if ($business) {
            $research->run($business);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Business::whereKey($this->businessId)->update([
            'research_status' => 'failed',
            'research_error' => 'Web research did not finish. You can run it again.',
        ]);
    }
}
