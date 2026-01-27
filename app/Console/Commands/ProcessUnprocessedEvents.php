<?php

namespace App\Console\Commands;

use App\Services\AttendanceProcessingService;
use Illuminate\Console\Command;

class ProcessUnprocessedEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:process-events {--limit=100}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process unprocessed event logs and update attendance records';

    /**
     * @var AttendanceProcessingService
     */
    protected $attendanceService;

    /**
     * Create a new command instance.
     *
     * @param AttendanceProcessingService $attendanceService
     * @return void
     */
    public function __construct(AttendanceProcessingService $attendanceService)
    {
        parent::__construct();
        $this->attendanceService = $attendanceService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        
        $this->info("Processing unprocessed event logs (limit: {$limit})...");

        $results = $this->attendanceService->processBatchUnprocessedEvents($limit);

        $this->info("\n=== Processing Summary ===");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Events', $results['total']],
                ['Successfully Processed', $results['processed']],
                ['Failed', $results['failed']],
                ['Skipped', $results['skipped']]
            ]
        );

        if ($results['failed'] > 0) {
            $this->warn("\nSome events failed to process. Check logs for details.");
        }

        if ($results['processed'] > 0) {
            $this->info("\n✓ Successfully processed {$results['processed']} events!");
        }

        return 0;
    }
}
