<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageOptimizationService
{
    /**
     * Resolve public CDN / URL for any stored media path.
     * Automatically routes through Cloudflare R2 when configured.
     */
    public static function url(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $mediaDisk = config('filesystems.media_disk', 'public');
        if ($mediaDisk !== 'public') {
            $relative = str_starts_with($path, 'storage/') ? substr($path, 8) : $path;
            $baseUrl = config("filesystems.disks.{$mediaDisk}.url");
            if ($baseUrl) {
                return rtrim($baseUrl, '/') . '/' . ltrim($relative, '/');
            }
            $cdnUrl = Storage::disk($mediaDisk)->url($relative);
            if ($cdnUrl) {
                return $cdnUrl;
            }
        }

        return str_starts_with($path, 'storage/') ? url($path) : url('storage/' . $path);
    }

    /**
     * Default maximum dimension (width or height) for stored proofs and catalog images.
     */
    public const DEFAULT_MAX_DIMENSION = 1600;

    /**
     * Default thumbnail dimension.
     */
    public const THUMBNAIL_DIMENSION = 320;

    /**
     * Default WebP and JPEG quality level (0 - 100).
     */
    public const DEFAULT_QUALITY = 82;

    /**
     * Store and optimize an uploaded file into a target storage directory.
     * Generates an optimized WebP master and a fast lightweight thumbnail.
     *
     * @param UploadedFile $file
     * @param string $directory (e.g. 'doorstep_deliveries', 'pickup_proofs', 'products')
     * @param array $options [max_dimension => 1600, quality => 82, generate_thumb => true, format => 'webp']
     * @return array Proof audit metadata
     */
    /**
     * Alias for optimizeAndStoreProof
     */
    public function optimizeAndStore(UploadedFile $file, string $directory = 'proofs', array $options = []): array
    {
        return $this->optimizeAndStoreProof($file, $directory, $options);
    }
    public function optimizeAndStoreProof(UploadedFile $file, string $directory, array $options = []): array
    {
        $maxDimension = (int) ($options['max_dimension'] ?? self::DEFAULT_MAX_DIMENSION);
        $quality = (int) ($options['quality'] ?? self::DEFAULT_QUALITY);
        $generateThumb = (bool) ($options['generate_thumb'] ?? true);
        $format = (string) ($options['format'] ?? 'webp'); // 'webp', 'jpg', or 'preserve'

        $originalSize = $file->getSize() ?: (file_exists($file->getRealPath()) ? filesize($file->getRealPath()) : 0);
        $tempSource = $file->getRealPath();

        // 1. Create GD image resource from source
        $resource = $this->createResourceFromFile($tempSource);

        if (!$resource) {
            // Fallback: If GD cannot parse format, store normally
            $fallbackPath = $file->store($directory, 'public');
            $fullPath = 'storage/' . $fallbackPath;
            return [
                'success' => true,
                'path' => $fullPath,
                'thumbnail_path' => null,
                'original_size' => $originalSize,
                'optimized_size' => $originalSize,
                'bytes_saved' => 0,
                'reduction_percent' => 0.0,
                'format' => $file->getClientOriginalExtension(),
                'is_fallback' => true,
            ];
        }

        // 2. Correct EXIF orientation before stripping metadata
        $resource = $this->autoRotateExif($resource, $tempSource);

        $srcWidth = imagesx($resource);
        $srcHeight = imagesy($resource);

        // 3. Resize if exceeds max dimension
        $optimizedResource = $this->resizeResource($resource, $srcWidth, $srcHeight, $maxDimension);
        $optWidth = imagesx($optimizedResource);
        $optHeight = imagesy($optimizedResource);

        // 4. Save optimized master file to storage
        $extension = ($format === 'webp') ? 'webp' : (($format === 'jpg') ? 'jpg' : ($file->getClientOriginalExtension() ?: 'webp'));
        $filename = Str::random(40) . '.' . $extension;
        $relativeDir = trim($directory, '/');
        $relativeStoragePath = $relativeDir . '/' . $filename;
        $absoluteDiskPath = Storage::disk('public')->path($relativeStoragePath);

        // Ensure target directory exists
        $dirPath = dirname($absoluteDiskPath);
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        $targetDisk = $options['disk'] ?? config('filesystems.media_disk', 'public');

        $this->saveResourceToDisk($optimizedResource, $absoluteDiskPath, $extension, $quality);
        $optimizedSize = file_exists($absoluteDiskPath) ? filesize($absoluteDiskPath) : $originalSize;

        // If target disk is external (Cloudflare R2 or S3), upload optimized binary to cloud
        if ($targetDisk !== 'public') {
            $masterBinary = file_get_contents($absoluteDiskPath);
            Storage::disk($targetDisk)->put($relativeStoragePath, $masterBinary, 'public');
            $baseUrl = config("filesystems.disks.{$targetDisk}.url");
            if ($baseUrl) {
                $finalPath = rtrim($baseUrl, '/') . '/' . ltrim($relativeStoragePath, '/');
            } else {
                $finalPath = Storage::disk($targetDisk)->url($relativeStoragePath) ?: ('storage/' . $relativeStoragePath);
            }
        } else {
            $finalPath = 'storage/' . $relativeStoragePath;
        }

        // 5. Generate lightweight thumbnail if requested
        $thumbRelativePath = null;
        if ($generateThumb) {
            $thumbFilename = 'thumb_' . $filename;
            $thumbRelativeStoragePath = $relativeDir . '/thumbs/' . $thumbFilename;
            $thumbAbsoluteDiskPath = Storage::disk('public')->path($thumbRelativeStoragePath);
            $thumbDir = dirname($thumbAbsoluteDiskPath);
            if (!is_dir($thumbDir)) {
                mkdir($thumbDir, 0755, true);
            }

            $thumbResource = $this->resizeResource($optimizedResource, $optWidth, $optHeight, self::THUMBNAIL_DIMENSION);
            $this->saveResourceToDisk($thumbResource, $thumbAbsoluteDiskPath, $extension, 75);
            imagedestroy($thumbResource);

            if ($targetDisk !== 'public') {
                $thumbBinary = file_get_contents($thumbAbsoluteDiskPath);
                Storage::disk($targetDisk)->put($thumbRelativeStoragePath, $thumbBinary, 'public');
                $baseUrl = config("filesystems.disks.{$targetDisk}.url");
                if ($baseUrl) {
                    $thumbRelativePath = rtrim($baseUrl, '/') . '/' . ltrim($thumbRelativeStoragePath, '/');
                } else {
                    $thumbRelativePath = Storage::disk($targetDisk)->url($thumbRelativeStoragePath) ?: ('storage/' . $thumbRelativeStoragePath);
                }
            } else {
                $thumbRelativePath = 'storage/' . $thumbRelativeStoragePath;
            }
        }

        // Cleanup resources
        if ($optimizedResource !== $resource) {
            imagedestroy($optimizedResource);
        }
        imagedestroy($resource);

        $bytesSaved = max(0, $originalSize - $optimizedSize);
        $reductionPercent = $originalSize > 0 ? round(($bytesSaved / $originalSize) * 100, 2) : 0.0;

        return [
            'success' => true,
            'path' => $finalPath,
            'thumbnail_path' => $thumbRelativePath,
            'original_size' => $originalSize,
            'optimized_size' => $optimizedSize,
            'bytes_saved' => $bytesSaved,
            'reduction_percent' => $reductionPercent,
            'width' => $optWidth,
            'height' => $optHeight,
            'format' => $extension,
            'is_fallback' => false,
        ];
    }

    /**
     * Optimize an existing physical image file on disk in place or convert it to WebP.
     *
     * @param string $absolutePath
     * @param int $maxDimension
     * @param int $quality
     * @param bool $convertToWebp
     * @return array Audit proof metrics
     */
    public function optimizeFileOnDisk(
        string $absolutePath,
        int $maxDimension = self::DEFAULT_MAX_DIMENSION,
        int $quality = self::DEFAULT_QUALITY,
        bool $convertToWebp = false
    ): array {
        if (!file_exists($absolutePath) || !is_file($absolutePath)) {
            return [
                'success' => false,
                'message' => "File not found: {$absolutePath}",
            ];
        }

        $originalSize = filesize($absolutePath);
        $resource = $this->createResourceFromFile($absolutePath);

        if (!$resource) {
            return [
                'success' => false,
                'message' => 'Unable to parse image format via GD.',
                'path' => $absolutePath,
                'original_size' => $originalSize,
                'optimized_size' => $originalSize,
                'bytes_saved' => 0,
            ];
        }

        $resource = $this->autoRotateExif($resource, $absolutePath);
        $srcWidth = imagesx($resource);
        $srcHeight = imagesy($resource);

        $optimizedResource = $this->resizeResource($resource, $srcWidth, $srcHeight, $maxDimension);

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $targetExt = $convertToWebp ? 'webp' : $ext;
        $targetPath = $convertToWebp ? preg_replace('/\.[^.]+$/', '.webp', $absolutePath) : $absolutePath;

        $tempTarget = $targetPath . '.opt_tmp';
        $this->saveResourceToDisk($optimizedResource, $tempTarget, $targetExt, $quality);

        if ($optimizedResource !== $resource) {
            imagedestroy($optimizedResource);
        }
        imagedestroy($resource);

        $newSize = file_exists($tempTarget) ? filesize($tempTarget) : $originalSize;

        // If optimized file is indeed smaller or converted, replace original
        if ($newSize < $originalSize || $convertToWebp) {
            rename($tempTarget, $targetPath);
            if ($convertToWebp && $targetPath !== $absolutePath && file_exists($absolutePath)) {
                unlink($absolutePath);
            }
            $finalSize = $newSize;
        } else {
            // Keep original if it was already optimal
            if (file_exists($tempTarget)) {
                unlink($tempTarget);
            }
            $finalSize = $originalSize;
        }

        $bytesSaved = max(0, $originalSize - $finalSize);
        $reductionPercent = $originalSize > 0 ? round(($bytesSaved / $originalSize) * 100, 2) : 0.0;

        return [
            'success' => true,
            'path' => $targetPath,
            'original_size' => $originalSize,
            'optimized_size' => $finalSize,
            'bytes_saved' => $bytesSaved,
            'reduction_percent' => $reductionPercent,
            'format' => $targetExt,
        ];
    }

    /**
     * Batch optimize an entire directory recursively and return a consolidated proof dossier.
     */
    public function batchOptimizeDirectory(string $directoryPath, int $maxDimension = 1600, int $quality = 82): array
    {
        if (!is_dir($directoryPath)) {
            return ['success' => false, 'message' => "Directory not found: {$directoryPath}"];
        }

        $results = [];
        $totalOriginal = 0;
        $totalOptimized = 0;

        try {
            $dirIterator = new \RecursiveDirectoryIterator($directoryPath, \RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = new \RecursiveIteratorIterator($dirIterator);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $ext = strtolower($item->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                continue;
            }

            $filePath = $item->getRealPath();
            $proof = $this->optimizeFileOnDisk($filePath, $maxDimension, $quality, false);

            if ($proof['success']) {
                $totalOriginal += $proof['original_size'];
                $totalOptimized += $proof['optimized_size'];
                $results[] = $proof;
            }
        }

        $totalSaved = max(0, $totalOriginal - $totalOptimized);
        $totalReduction = $totalOriginal > 0 ? round(($totalSaved / $totalOriginal) * 100, 2) : 0.0;

        return [
            'success' => true,
            'directory' => $directoryPath,
            'files_processed' => count($results),
            'total_original_bytes' => $totalOriginal,
            'total_optimized_bytes' => $totalOptimized,
            'total_bytes_saved' => $totalSaved,
            'overall_reduction_percent' => $totalReduction,
            'details' => $results,
        ];
    }

    /**
     * Create a GD image resource from file path safely supporting WebP, JPEG, PNG, GIF.
     */
    protected function createResourceFromFile(string $filePath)
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        $imageInfo = @getimagesize($filePath);
        if (!$imageInfo) {
            return false;
        }

        $mime = $imageInfo['mime'] ?? '';

        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($filePath),
            'image/png' => @imagecreatefrompng($filePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : false,
            'image/gif' => @imagecreatefromgif($filePath),
            default => false,
        };
    }

    /**
     * Correct photo orientation using EXIF header before stripping metadata.
     */
    protected function autoRotateExif($resource, string $filePath)
    {
        if (!function_exists('exif_read_data')) {
            return $resource;
        }

        $exif = @exif_read_data($filePath);
        if (empty($exif['Orientation'])) {
            return $resource;
        }

        return match ($exif['Orientation']) {
            3 => imagerotate($resource, 180, 0),
            6 => imagerotate($resource, -90, 0),
            8 => imagerotate($resource, 90, 0),
            default => $resource,
        };
    }

    /**
     * Resize image resource preserving aspect ratio up to max dimension.
     */
    protected function resizeResource($resource, int $srcWidth, int $srcHeight, int $maxDimension)
    {
        if ($srcWidth <= $maxDimension && $srcHeight <= $maxDimension) {
            return $resource;
        }

        if ($srcWidth >= $srcHeight) {
            $newWidth = $maxDimension;
            $newHeight = (int) round(($srcHeight / $srcWidth) * $maxDimension);
        } else {
            $newHeight = $maxDimension;
            $newWidth = (int) round(($srcWidth / $srcHeight) * $maxDimension);
        }

        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve PNG/WebP alpha transparency
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);

        imagecopyresampled($newImage, $resource, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

        return $newImage;
    }

    /**
     * Save GD resource to disk in specified format with high-efficiency compression.
     */
    protected function saveResourceToDisk($resource, string $destinationPath, string $format, int $quality): bool
    {
        $dir = dirname($destinationPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return match (strtolower($format)) {
            'webp' => function_exists('imagewebp') ? imagewebp($resource, $destinationPath, $quality) : imagejpeg($resource, $destinationPath, $quality),
            'png' => imagepng($resource, $destinationPath, (int) round(9 - ($quality * 9 / 100))),
            default => imagejpeg($resource, $destinationPath, $quality),
        };
    }
}
