<?php

namespace App\Console\Commands;

use App\Models\ArchivedWebhookEvent;
use App\Models\ProcessedWebhookEvent;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveProcessedWebhooksCommand extends Command
{
    protected $signature = 'webhooks:archive 
                            {--days=90 : Minimum age in days for completed webhooks to be archived}
                            {--chunk=500 : Batch size for processing}
                            {--dry-run : Report count without performing archival}';

    protected $description = 'Archive completed webhook inbox records older than retention window to archived_webhook_events';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $this->error('The --days option must be a positive integer.');
            return 1;
        }

        $chunkSize = (int) $this->option('chunk');
        if ($chunkSize <= 0) {
            $chunkSize = 500;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);

        $this->info("===============================================================");
        $this->info("WEBHOOK INBOX RETENTION & ARCHIVAL");
        $this->info("===============================================================");
        $this->info("Retention Threshold: {$days} days (Cutoff: {$cutoff->toIso8601String()})");
        $this->info("Target Status:       completed ONLY (failed, unmatched & processing retained)");
        $this->info("Execution Mode:      " . ($isDryRun ? "DRY-RUN (No changes applied)" : "LIVE"));
        $this->info("===============================================================");

        $baseQuery = ProcessedWebhookEvent::where('status', 'completed')
            ->where(function ($q) use ($cutoff) {
                $q->where('processed_at', '<=', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('processed_at')->where('created_at', '<=', $cutoff);
                  });
            });

        $eligibleCount = (clone $baseQuery)->count();

        if ($eligibleCount === 0) {
            $this->info('No completed webhook events exceed the retention threshold.');
            return 0;
        }

        if ($isDryRun) {
            $this->info("[DRY-RUN] Found {$eligibleCount} completed webhook event(s) eligible for archival.");
            return 0;
        }

        $this->info("Archiving {$eligibleCount} completed webhook event(s) in batches of {$chunkSize}...");
        $totalArchived = 0;

        do {
            $records = (clone $baseQuery)
                ->orderBy('id', 'asc')
                ->limit($chunkSize)
                ->get();

            if ($records->isEmpty()) {
                break;
            }

            DB::transaction(function () use ($records, &$totalArchived) {
                $archiveData = [];
                $idsToDelete = [];
                $now = Carbon::now();

                foreach ($records as $record) {
                    $archiveData[] = [
                        'original_id' => $record->id,
                        'event_id' => $record->event_id,
                        'event_type' => $record->event_type,
                        'payload_hash' => $record->payload_hash,
                        'status' => $record->status,
                        'error_reason' => $record->error_reason,
                        'attempts' => $record->attempts,
                        'payload' => $record->payload,
                        'processed_at' => $record->processed_at,
                        'original_created_at' => $record->created_at,
                        'original_updated_at' => $record->updated_at,
                        'archived_at' => $now,
                    ];
                    $idsToDelete[] = $record->id;
                }

                ArchivedWebhookEvent::insert($archiveData);
                ProcessedWebhookEvent::whereIn('id', $idsToDelete)->delete();

                $totalArchived += count($idsToDelete);
            });

            $this->line("Archived batch of " . $records->count() . " events. Total so far: {$totalArchived}/{$eligibleCount}");
        } while ($records->count() >= $chunkSize);

        $this->info("Archival Complete: {$totalArchived} completed webhook event(s) archived successfully.");
        return 0;
    }
}
