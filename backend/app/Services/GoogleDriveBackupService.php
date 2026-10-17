<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class GoogleDriveBackupService
{
    protected const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    protected const DRIVE_UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart';
    protected const DRIVE_FILES_URL = 'https://www.googleapis.com/drive/v3/files';

    /**
     * Run full database backup, package into encrypted zip, and upload to Google Drive.
     *
     * @param bool $dryRun If true, create local archive only without remote API call
     * @return array Backup execution metrics
     */
    public function executeBackup(bool $dryRun = false): array
    {
        $timestamp = Carbon::now()->format('Y-m-d_His');
        $connection = Config::get('database.default', 'sqlite');
        $backupDir = storage_path('app/backups');

        if (!File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        // 1. Generate atomic raw database dump
        $dumpPath = $this->generateDatabaseDump($connection, $backupDir, $timestamp);
        $dumpSize = File::exists($dumpPath) ? File::size($dumpPath) : 0;

        // 2. Package into timestamped Zip Archive with AES-256 encryption
        $zipFilename = "vmarket_backup_{$connection}_{$timestamp}.zip";
        $zipPath = $backupDir . DIRECTORY_SEPARATOR . $zipFilename;
        $zipPassword = Config::get('services.backup.encryption_key', env('BACKUP_ENCRYPTION_KEY', null));

        // Fail-Closed: Mandatory encryption in production environment
        if (empty($zipPassword) && app()->environment('production')) {
            throw new \RuntimeException(
                'Disaster Recovery Security Violation: BACKUP_ENCRYPTION_KEY must be configured in production to prevent unencrypted database backup exposure.'
            );
        }

        $this->packageZipArchive($dumpPath, $zipPath, $zipPassword, [
            'backup_timestamp' => Carbon::now()->toIso8601String(),
            'database_connection' => $connection,
            'git_commit' => $this->resolveGitCommitHash(),
        ]);

        $zipSize = File::exists($zipPath) ? File::size($zipPath) : 0;

        // Cleanup temporary raw uncompressed dump
        if (File::exists($dumpPath)) {
            File::delete($dumpPath);
        }

        // 3. Upload to Google Drive (or complete dry-run)
        $remoteFileId = null;
        $prunedCount = 0;
        $uploadStatus = 'LOCAL_SAVED';

        if (!$dryRun && $this->isConfigured()) {
            try {
                $accessToken = $this->getServiceAccountAccessToken();
                $folderId = Config::get('services.google_drive.folder_id', env('GOOGLE_DRIVE_FOLDER_ID'));

                $uploadResponse = $this->uploadFileToDrive($accessToken, $zipPath, $zipFilename, $folderId);
                $remoteFileId = $uploadResponse['id'] ?? null;
                $uploadStatus = 'UPLOADED_TO_GOOGLE_DRIVE';

                // 4. Prune backups older than retention policy (30 days)
                $retentionDays = (int) Config::get('services.backup.retention_days', 30);
                $prunedCount = $this->pruneOldBackups($accessToken, $folderId, $retentionDays);

                Log::info("Google Drive database backup successful: {$zipFilename} (ID: {$remoteFileId})");
            } catch (Exception $e) {
                Log::error("Google Drive backup upload failed: " . $e->getMessage());
                $uploadStatus = 'UPLOAD_FAILED: ' . $e->getMessage();
            }
        } elseif ($dryRun) {
            $uploadStatus = 'DRY_RUN_COMPLETED';
        } else {
            $uploadStatus = 'LOCAL_SAVED_CREDENTIALS_MISSING';
        }

        $isOffsiteVerified = ($uploadStatus === 'UPLOADED_TO_GOOGLE_DRIVE');

        return [
            'success' => in_array($uploadStatus, ['UPLOADED_TO_GOOGLE_DRIVE', 'DRY_RUN_COMPLETED', 'LOCAL_SAVED', 'LOCAL_SAVED_CREDENTIALS_MISSING']),
            'offsite_verified' => $isOffsiteVerified,
            'local_saved' => File::exists($zipPath),
            'connection' => $connection,
            'timestamp' => $timestamp,
            'dump_size_bytes' => $dumpSize,
            'zip_path' => $zipPath,
            'zip_filename' => $zipFilename,
            'zip_size_bytes' => $zipSize,
            'status' => $uploadStatus,
            'google_drive_file_id' => $remoteFileId,
            'pruned_archives_count' => $prunedCount,
            'is_encrypted' => !empty($zipPassword),
        ];
    }

    /**
     * Check if Google Drive Service Account credentials are configured.
     */
    public function isConfigured(): bool
    {
        $jsonPath = Config::get('services.google_drive.service_account_json', env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON'));
        if ($jsonPath && File::exists($jsonPath)) {
            return true;
        }

        $email = Config::get('services.google_drive.client_email', env('GOOGLE_DRIVE_CLIENT_EMAIL'));
        $key = Config::get('services.google_drive.private_key', env('GOOGLE_DRIVE_PRIVATE_KEY'));

        return !empty($email) && !empty($key);
    }

    /**
     * Generate raw SQL / SQLite database dump file.
     */
    protected function generateDatabaseDump(string $connection, string $backupDir, string $timestamp): string
    {
        $dumpPath = $backupDir . DIRECTORY_SEPARATOR . "dump_{$connection}_{$timestamp}.sql";

        if ($connection === 'sqlite') {
            $database = Config::get('database.connections.sqlite.database');
            if ($database === ':memory:') {
                // In-memory SQLite: export schema and tables
                $content = "-- Victorious Marketplace In-Memory SQLite Dump {$timestamp}\n";
                $tables = DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                foreach ($tables as $table) {
                    $content .= $table->sql . ";\n";
                    $rows = DB::table($table->name)->get();
                    foreach ($rows as $row) {
                        $values = array_map(function ($val) {
                            return is_null($val) ? 'NULL' : "'" . addslashes((string)$val) . "'";
                        }, (array)$row);
                        $content .= "INSERT INTO `{$table->name}` VALUES (" . implode(',', $values) . ");\n";
                    }
                }
                File::put($dumpPath, $content);
            } elseif (File::exists($database)) {
                File::copy($database, $dumpPath);
            } else {
                File::put($dumpPath, "-- Empty SQLite database placeholder at {$timestamp}\n");
            }
            return $dumpPath;
        }

        // MySQL / MariaDB Dump
        if ($connection === 'mysql') {
            $host = (string) Config::get('database.connections.mysql.host', '127.0.0.1');
            $port = (string) Config::get('database.connections.mysql.port', '3306');
            $database = (string) Config::get('database.connections.mysql.database');
            $username = (string) Config::get('database.connections.mysql.username');
            $password = (string) Config::get('database.connections.mysql.password');

            $dumpBinary = env('MYSQLDUMP_PATH', 'mysqldump');

            // Security Hardening: Write ALL connection credentials to a temporary 
            // permissioned options file to eliminate any sensitive parameters from ps aux / process list
            $tempCnf = tempnam(sys_get_temp_dir(), 'mycnf_');
            $normalizedCnf = str_replace('\\', '/', $tempCnf);
            $escapedPassword = addcslashes($password, "\\\"");
            $escapedUsername = addcslashes($username, "\\\"");

            $cnfContent = "[client]\n"
                . "user=\"{$escapedUsername}\"\n"
                . "password=\"{$escapedPassword}\"\n"
                . "host=\"{$host}\"\n"
                . "port={$port}\n";

            File::put($tempCnf, $cnfContent);
            @chmod($tempCnf, 0600);

            $returnVar = -1;
            try {
                $cmd = sprintf(
                    '"%s" --defaults-extra-file="%s" --single-transaction --quick --skip-lock-tables %s > "%s"',
                    $dumpBinary,
                    $normalizedCnf,
                    escapeshellarg($database),
                    $dumpPath
                );

                @exec($cmd, $output, $returnVar);
            } finally {
                if (File::exists($tempCnf)) {
                    @File::delete($tempCnf);
                }
            }

            // Validate both exit code AND file integrity
            if ($returnVar !== 0 || !File::exists($dumpPath) || File::size($dumpPath) === 0) {
                if (File::exists($dumpPath)) {
                    @File::delete($dumpPath);
                }
                // Fallback table-by-table dump via Eloquent / PDO
                $this->fallbackDatabaseExport($dumpPath);
            }
            return $dumpPath;
        }

        $this->fallbackDatabaseExport($dumpPath);
        return $dumpPath;
    }

    /**
     * Fallback database table export via PDO when external CLI dump tools are unavailable.
     * Streams via chunks to prevent PHP memory exhaustion.
     */
    protected function fallbackDatabaseExport(string $dumpPath): void
    {
        $handle = fopen($dumpPath, 'w');
        fwrite($handle, "-- Victorious Marketplace Fallback SQL Dump\n");
        fwrite($handle, "-- Generated: " . Carbon::now()->toIso8601String() . "\n\n");

        $driver = DB::getDriverName();
        $tableNames = [];

        if ($driver === 'sqlite') {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            foreach ($tables as $t) {
                if (!empty($t->name)) {
                    $tableNames[] = $t->name;
                }
            }
        } else {
            $tables = DB::select('SHOW TABLES');
            foreach ($tables as $tableObj) {
                $values = array_values((array) $tableObj);
                if (!empty($values[0])) {
                    $tableNames[] = $values[0];
                }
            }
        }

        foreach ($tableNames as $table) {
            fwrite($handle, "-- Table: `{$table}`\n");
            try {
                DB::table($table)->orderBy(DB::raw('1'))->chunk(250, function ($rows) use ($handle, $table) {
                    foreach ($rows as $row) {
                        $cols = array_keys((array) $row);
                        $values = array_map(function ($val) {
                            return is_null($val) ? 'NULL' : "'" . addslashes((string) $val) . "'";
                        }, (array) $row);
                        $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $values) . ");\n";
                        fwrite($handle, $sql);
                    }
                });
            } catch (\Throwable $e) {
                // If orderBy 1 fails on certain table schemas, fall back to unchunked query
                $rows = DB::table($table)->limit(5000)->get();
                foreach ($rows as $row) {
                    $cols = array_keys((array) $row);
                    $values = array_map(function ($val) {
                        return is_null($val) ? 'NULL' : "'" . addslashes((string) $val) . "'";
                    }, (array) $row);
                    $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $values) . ");\n";
                    fwrite($handle, $sql);
                }
            }
        }
        fclose($handle);
    }

    /**
     * Compress raw database dump into timestamped ZIP archive with optional password.
     */
    protected function packageZipArchive(string $dumpPath, string $zipPath, ?string $password, array $metadata): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Unable to create zip archive at {$zipPath}");
        }

        if (!empty($password)) {
            $zip->setPassword($password);
        }

        $zip->addFile($dumpPath, basename($dumpPath));

        if (!empty($password)) {
            $zip->setEncryptionName(basename($dumpPath), ZipArchive::EM_AES_256);
        }

        // Add metadata JSON manifest
        $manifestContent = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $zip->addFromString('manifest.json', $manifestContent);

        $zip->close();
    }

    /**
     * Authenticate via Google Service Account JWT and acquire OAuth2 Bearer Access Token.
     */
    public function getServiceAccountAccessToken(): string
    {
        $credentials = $this->resolveServiceAccountCredentials();
        $clientEmail = $credentials['client_email'];
        $privateKey = $credentials['private_key'];

        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claim = [
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/drive',
            'aud' => self::OAUTH_TOKEN_URL,
            'exp' => $now + 3600,
            'iat' => $now,
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header));
        $encodedClaim = $this->base64UrlEncode(json_encode($claim));
        $signatureInput = $encodedHeader . '.' . $encodedClaim;

        $binarySignature = '';
        if (!openssl_sign($signatureInput, $binarySignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new Exception("Failed to sign Google Service Account JWT assertion with private key: " . openssl_error_string());
        }

        $jwt = $signatureInput . '.' . $this->base64UrlEncode($binarySignature);

        $response = Http::asForm()->post(self::OAUTH_TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (!$response->successful()) {
            throw new Exception("Google OAuth2 token exchange error: " . $response->body());
        }

        $data = $response->json();
        return $data['access_token'];
    }

    /**
     * Upload zip file to Google Drive via v3 Multipart Upload API.
     */
    protected function uploadFileToDrive(string $accessToken, string $filePath, string $fileName, ?string $folderId): array
    {
        $metadata = ['name' => $fileName];
        if (!empty($folderId)) {
            $metadata['parents'] = [$folderId];
        }

        $boundary = '-------' . md5(time());
        $delimiter = "\r\n--" . $boundary . "\r\n";
        $closeDelimiter = "\r\n--" . $boundary . "--";

        $fileContent = File::get($filePath);
        $mimeType = 'application/zip';

        $body = $delimiter
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . json_encode($metadata)
            . $delimiter
            . "Content-Type: {$mimeType}\r\n\r\n"
            . $fileContent
            . $closeDelimiter;

        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'multipart/related; boundary=' . $boundary,
                'Content-Length' => strlen($body),
            ])
            ->send('POST', self::DRIVE_UPLOAD_URL, [
                'body' => $body,
            ]);

        if (!$response->successful()) {
            throw new Exception("Google Drive upload API failed: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Prune backups in Google Drive folder older than specified retention days.
     */
    protected function pruneOldBackups(string $accessToken, ?string $folderId, int $retentionDays): int
    {
        if (empty($folderId)) {
            return 0;
        }

        $cutoffDate = Carbon::now()->subDays($retentionDays)->toRfc3339String();
        $query = "'{$folderId}' in parents and trashed = false and createdTime < '{$cutoffDate}'";

        $response = Http::withToken($accessToken)->get(self::DRIVE_FILES_URL, [
            'q' => $query,
            'fields' => 'files(id, name, createdTime)',
        ]);

        if (!$response->successful()) {
            Log::warning("Could not list Google Drive files for pruning: " . $response->body());
            return 0;
        }

        $files = $response->json('files') ?? [];
        $deletedCount = 0;

        foreach ($files as $file) {
            $delRes = Http::withToken($accessToken)->delete(self::DRIVE_FILES_URL . '/' . $file['id']);
            if ($delRes->successful()) {
                $deletedCount++;
                Log::info("Pruned expired Google Drive backup archive: {$file['name']} ({$file['id']})");
            }
        }

        return $deletedCount;
    }

    /**
     * Resolve service account credentials from JSON file or individual .env settings.
     */
    protected function resolveServiceAccountCredentials(): array
    {
        $jsonPath = Config::get('services.google_drive.service_account_json', env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON'));
        if ($jsonPath && File::exists($jsonPath)) {
            $parsed = json_decode(File::get($jsonPath), true);
            if (isset($parsed['client_email'], $parsed['private_key'])) {
                return $parsed;
            }
        }

        $email = Config::get('services.google_drive.client_email', env('GOOGLE_DRIVE_CLIENT_EMAIL'));
        $rawKey = Config::get('services.google_drive.private_key', env('GOOGLE_DRIVE_PRIVATE_KEY'));

        if (empty($email) || empty($rawKey)) {
            throw new Exception("Google Drive Service Account credentials are not configured.");
        }

        // Clean up escaped newlines if passed in .env string
        $privateKey = str_replace('\n', "\n", $rawKey);

        return [
            'client_email' => $email,
            'private_key' => $privateKey,
        ];
    }

    /**
     * URL-safe base64 encoding without padding.
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Resolve current git commit hash for backup manifest metadata.
     */
    protected function resolveGitCommitHash(): string
    {
        try {
            $headPath = base_path('../.git/refs/heads/main');
            if (File::exists($headPath)) {
                return trim(File::get($headPath));
            }
        } catch (Exception $e) {
            // Ignore git lookup in sandboxed environment
        }
        return 'main-production';
    }
}
