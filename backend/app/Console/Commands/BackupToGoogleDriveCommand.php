<?php

namespace App\Console\Commands;

use App\Services\GoogleDriveBackupService;
use Illuminate\Console\Command;

class BackupToGoogleDriveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:google-drive {--dry-run : Create local encrypted backup archive without uploading to Google Drive}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an atomic, encrypted database backup and synchronize to Google Drive offsite vault';

    /**
     * Execute the console command.
     */
    public function handle(GoogleDriveBackupService $backupService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info("================================================================================");
        $this->info("      VICTORIOUS MARKETPLACE - OFFSITE DISASTER RECOVERY BACKUP ENGINE         ");
        $this->info("================================================================================");

        if ($dryRun) {
            $this->warn("MODE: DRY RUN (Local archive generation only, remote Google Drive API skipped)");
        }

        $this->comment("1. Dumping database and packaging encrypted archive...");
        $result = $backupService->executeBackup($dryRun);

        $this->table(
            ['Metric / Parameter', 'Value / Audit State'],
            [
                ['Database Engine', strtoupper($result['connection'])],
                ['Timestamp', $result['timestamp']],
                ['Raw Snapshot Size', number_format($result['dump_size_bytes'] / 1024, 2) . ' KB (' . $result['dump_size_bytes'] . ' bytes)'],
                ['Encrypted Zip Archive', $result['zip_filename']],
                ['Compressed Archive Size', number_format($result['zip_size_bytes'] / 1024, 2) . ' KB (' . $result['zip_size_bytes'] . ' bytes)'],
                ['AES-256 Encryption', $result['is_encrypted'] ? 'ACTIVE (Password Protected)' : 'NONE (Plain Archive)'],
                ['Google Drive Upload Status', $result['status']],
                ['Google Drive File ID', $result['google_drive_file_id'] ?? 'N/A (Local Only)'],
                ['30-Day Pruned Archives', $result['pruned_archives_count'] . ' expired backups removed'],
            ]
        );

        if ($result['success']) {
            $this->info("✓ Disaster recovery backup completed successfully.");
            return Command::SUCCESS;
        }

        $this->error("✗ Backup failed: " . ($result['status'] ?? 'Unknown error'));
        return Command::FAILURE;
    }
}
