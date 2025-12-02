<?php

namespace App\Jobs;

use App\Services\HoldExpiryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessExpiredHoldsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(HoldExpiryService $expiryService): void
    {
        Log::info('Processing expired holds job started');

        $processed = $expiryService->processExpiredHolds();

        Log::info('Processing expired holds job completed', [
            'processed_count' => $processed,
        ]);
    }
}


