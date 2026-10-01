<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageThumbnailService
{
    /**
     * Get the relative thumbnail path for a given original file path.
     */
    public function getThumbnailPath(string $filePath): string
    {
        $pathInfo = pathinfo($filePath);
        $dirname = $pathInfo['dirname'] ?? '';
        $filename = $pathInfo['filename'] ?? 'thumb';

        $thumbDir = ($dirname === '.' || empty($dirname)) ? 'thumbnails' : $dirname.'/thumbnails';

        return $thumbDir.'/'.$filename.'.webp';
    }

    /**
     * Check if a thumbnail exists on the public disk.
     */
    public function thumbnailExists(string $filePath): bool
    {
        $thumbnailPath = $this->getThumbnailPath($filePath);

        return Storage::disk('public')->exists($thumbnailPath);
    }

    /**
     * Generate a thumbnail image and save it on the public disk.
     *
     * @param  string  $filePath  Relative path on the public disk
     * @param  int  $width  Target width
     * @param  int  $height  Target height
     * @param  int  $quality  WebP compression quality (0-100)
     * @return string|null The relative thumbnail path if successful, null on failure
     */
    public function generateThumbnail(string $filePath, int $width = 150, int $height = 150, int $quality = 80): ?string
    {
        if (! Storage::disk('public')->exists($filePath)) {
            return null;
        }

        try {
            $absolutePath = Storage::disk('public')->path($filePath);
            $thumbnailRelativePath = $this->getThumbnailPath($filePath);
            $thumbnailAbsolutePath = Storage::disk('public')->path($thumbnailRelativePath);

            $thumbnailDir = dirname($thumbnailAbsolutePath);
            if (! is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            $imageContent = file_get_contents($absolutePath);
            if (! $imageContent) {
                return null;
            }

            if (! extension_loaded('gd')) {
                return null;
            }

            $sourceImage = @imagecreatefromstring($imageContent);
            if (! $sourceImage) {
                return null;
            }

            $origWidth = imagesx($sourceImage);
            $origHeight = imagesy($sourceImage);

            if ($origWidth <= 0 || $origHeight <= 0) {
                imagedestroy($sourceImage);

                return null;
            }

            // Calculate aspect ratio preserving dimensions
            $ratio = min($width / $origWidth, $height / $origHeight);
            $newWidth = (int) round($origWidth * $ratio);
            $newHeight = (int) round($origHeight * $ratio);

            $targetImage = imagecreatetruecolor($newWidth, $newHeight);

            // Handle transparency for PNG / WebP
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
            imagefilledrectangle($targetImage, 0, 0, $newWidth, $newHeight, $transparent);

            imagecopyresampled(
                $targetImage,
                $sourceImage,
                0,
                0,
                0,
                0,
                $newWidth,
                $newHeight,
                $origWidth,
                $origHeight
            );

            // Save as WebP if supported, otherwise fallback to JPEG
            if (function_exists('imagewebp')) {
                imagewebp($targetImage, $thumbnailAbsolutePath, $quality);
            } else {
                $thumbnailRelativePath = preg_replace('/\.webp$/i', '.jpg', $thumbnailRelativePath);
                $thumbnailAbsolutePath = Storage::disk('public')->path($thumbnailRelativePath);
                imagejpeg($targetImage, $thumbnailAbsolutePath, $quality);
            }

            imagedestroy($sourceImage);
            imagedestroy($targetImage);

            return $thumbnailRelativePath;
        } catch (\Throwable $e) {
            Log::warning("Failed to generate thumbnail for {$filePath}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Delete thumbnail associated with a given file path.
     */
    public function deleteThumbnail(string $filePath): void
    {
        try {
            $thumbnailPath = $this->getThumbnailPath($filePath);
            if (Storage::disk('public')->exists($thumbnailPath)) {
                Storage::disk('public')->delete($thumbnailPath);
            }

            $jpgThumbnail = preg_replace('/\.webp$/i', '.jpg', $thumbnailPath);
            if (Storage::disk('public')->exists($jpgThumbnail)) {
                Storage::disk('public')->delete($jpgThumbnail);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to delete thumbnail for {$filePath}: ".$e->getMessage());
        }
    }
}
