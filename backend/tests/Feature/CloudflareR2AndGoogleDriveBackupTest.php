<?php

namespace Tests\Feature;

use App\Services\GoogleDriveBackupService;
use App\Services\ImageOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class CloudflareR2AndGoogleDriveBackupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Cloudflare R2 disk is properly registered with S3 driver and CDN settings
     */
    public function test_cloudflare_r2_disk_configuration_is_valid_and_s3_compatible(): void
    {
        $r2Config = Config::get('filesystems.disks.r2');

        $this->assertIsArray($r2Config);
        $this->assertEquals('s3', $r2Config['driver']);
        $this->assertEquals('auto', $r2Config['region']);
        $this->assertTrue($r2Config['use_path_style_endpoint']);
        $this->assertEquals('public', $r2Config['visibility']);
        $this->assertEquals('public', Config::get('filesystems.media_disk'));
    }

    /**
     * Test 2: ImageOptimizationService stores WebP to Cloudflare R2 disk and resolves CDN URL
     */
    public function test_image_optimization_service_supports_r2_disk_and_cdn_url_resolution(): void
    {
        Storage::fake('r2');
        Config::set('filesystems.disks.r2.url', 'https://media.victoriousmarketplace.com');

        $service = app(ImageOptimizationService::class);
        $file = UploadedFile::fake()->image('test_product.jpg', 1200, 800);

        $result = $service->optimizeAndStoreProof(
            $file,
            'products',
            [
                'format' => 'webp',
                'max_dimension' => 1600,
                'quality' => 82,
                'generate_thumb' => true,
                'disk' => 'r2',
            ]
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('webp', $result['format']);
        $this->assertStringContainsString('https://media.victoriousmarketplace.com/products/', $result['path']);
        $this->assertStringContainsString('https://media.victoriousmarketplace.com/products/thumbs/', $result['thumbnail_path']);

        // Verify stored in fake R2 disk
        $relativePath = str_replace('https://media.victoriousmarketplace.com/', '', $result['path']);
        Storage::disk('r2')->assertExists($relativePath);

        // Test static URL resolver
        Config::set('filesystems.media_disk', 'r2');
        $resolvedUrl = ImageOptimizationService::url('products/banner_sample.webp');
        $this->assertEquals('https://media.victoriousmarketplace.com/products/banner_sample.webp', $resolvedUrl);
    }

    /**
     * Test 3: Artisan backup:google-drive generates encrypted database dump and manifest
     */
    public function test_artisan_backup_command_generates_atomic_zip_with_manifest(): void
    {
        $backupDir = storage_path('app/backups');
        if (File::isDirectory($backupDir)) {
            File::deleteDirectory($backupDir);
        }

        Config::set('services.backup.encryption_key', 'VictoriousSecretKey2026!');

        $exitCode = Artisan::call('backup:google-drive', ['--dry-run' => true]);
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('VICTORIOUS MARKETPLACE - OFFSITE DISASTER RECOVERY BACKUP ENGINE', $output);
        $this->assertStringContainsString('DRY_RUN_COMPLETED', $output);
        $this->assertStringContainsString('ACTIVE (Password Protected)', $output);

        // Verify physical zip archive creation
        $zipFiles = File::glob($backupDir . '/*.zip');
        $this->assertNotEmpty($zipFiles);

        $latestZip = $zipFiles[0];
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($latestZip) === true);

        // Verify manifest exists inside archive
        $manifestContent = $zip->getFromName('manifest.json');
        $this->assertNotEmpty($manifestContent);

        $manifestData = json_decode($manifestContent, true);
        $this->assertIsArray($manifestData);
        $this->assertEquals('sqlite', $manifestData['database_connection']);
        $this->assertArrayHasKey('backup_timestamp', $manifestData);
        $this->assertArrayHasKey('git_commit', $manifestData);

        $zip->close();
    }

    /**
     * Test 4: GoogleDriveBackupService detects missing credentials gracefully
     */
    public function test_google_drive_service_handles_missing_credentials_gracefully(): void
    {
        Config::set('services.google_drive.client_email', null);
        Config::set('services.google_drive.private_key', null);
        Config::set('services.google_drive.service_account_json', null);

        $service = app(GoogleDriveBackupService::class);
        $this->assertFalse($service->isConfigured());

        $result = $service->executeBackup(dryRun: false);
        $this->assertTrue($result['success']);
        $this->assertEquals('LOCAL_SAVED_CREDENTIALS_MISSING', $result['status']);
        $this->assertFileExists($result['zip_path']);
    }

    /**
     * Test 5: Nightly backup schedule is registered in routes/console.php
     */
    public function test_nightly_backup_schedule_is_registered(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = collect($schedule->events());

        $backupEvent = $events->first(function ($event) {
            return str_contains($event->command ?? '', 'backup:google-drive');
        });

        $this->assertNotNull($backupEvent, 'backup:google-drive is registered in schedule');
        $this->assertEquals('0 2 * * *', $backupEvent->expression);
    }

    /**
     * Test 6: Missing BACKUP_ENCRYPTION_KEY throws RuntimeException in production environment
     */
    public function test_missing_encryption_key_fails_closed_in_production(): void
    {
        $this->app->detectEnvironment(fn() => 'production');
        \Illuminate\Support\Facades\Config::set('services.backup.encryption_key', null);

        $service = app(GoogleDriveBackupService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('BACKUP_ENCRYPTION_KEY must be configured in production');

        $service->executeBackup(dryRun: true);
    }

    /**
     * Test 7: Restore Drill & Cryptographic Integrity Verification (Finding 8)
     */
    public function test_backup_archive_cryptographic_integrity_and_restore_verification(): void
    {
        $password = 'TestRestoreDrillPass123!@#';
        \Illuminate\Support\Facades\Config::set('services.backup.encryption_key', $password);

        $service = app(GoogleDriveBackupService::class);
        $result = $service->executeBackup(dryRun: false);

        $this->assertTrue($result['success']);
        $zipPath = $result['zip_path'];
        $this->assertFileExists($zipPath);

        // Perform restore verification drill
        $drill = $service->verifyBackupIntegrity($zipPath, $password);

        $this->assertTrue($drill['valid']);
        $this->assertNotEmpty($drill['manifest']['backup_timestamp']);
        $this->assertGreaterThan(0, $drill['extracted_dump_bytes']);

        // Negative check: wrong password fails decryption
        $failedDrill = $service->verifyBackupIntegrity($zipPath, 'WrongPassword999!');
        $this->assertFalse($failedDrill['valid']);
    }
}
