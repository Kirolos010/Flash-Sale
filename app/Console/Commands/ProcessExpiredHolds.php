<?php

namespace App\Console\Commands;

use App\Services\HoldExpiryService;
use Illuminate\Console\Command;

class ProcessExpiredHolds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'holds:process-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process expired holds and release their stock';

    /**
     * Execute the console command.
     */
    public function handle(HoldExpiryService $expiryService): int
    {
        $this->info('Processing expired holds...');

        $processed = $expiryService->processExpiredHolds();

        $this->info("Processed {$processed} expired holds.");

        return Command::SUCCESS;
    }
}


