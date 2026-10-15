<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ImageOptimizationService
{
    protected int $maxWidth = 1920;
    protected int $maxHeight = 1080;
    protected int $quality = 80;
    protected int $thumbnailSize = 200;

    /**
     * Optimize an uploaded image: resize, convert to WebP, and generate a thumbnail.
     *
     * @param  UploadedFile  $file       The uploaded image file.
     * @param  string        $directory  Target storage subdirectory (within the public disk).
     * @return array{path: string, thumbnail: string|null, width: int|null, height: int|null, size: int}
     */
    public function optimize(UploadedFile $file, string $directory = 'images'): array
    {
        try {
            $image = Image::read($file);
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $filename = $originalName . '_' . uniqid() . '.webp';

            // Resize if too large (maintain aspect ratio)
            if ($image->width() > $this->maxWidth || $image->height() > $this->maxHeight) {
                $image->scaleDown(width: $this->maxWidth, height: $this->maxHeight);
            }

            // Save optimized version as WebP
            $optimizedPath = "{$directory}/{$filename}";
            $encoded = $image->toWebp($this->quality);
            Storage::disk('public')->put($optimizedPath, (string) $encoded);

            // Generate thumbnail
            $thumbFilename = $originalName . '_thumb_' . uniqid() . '.webp';
            $thumbPath = "{$directory}/thumbs/{$thumbFilename}";
            $thumb = Image::read($file)->cover($this->thumbnailSize, $this->thumbnailSize);
            Storage::disk('public')->put($thumbPath, (string) $thumb->toWebp(70));

            return [
                'path' => $optimizedPath,
                'thumbnail' => $thumbPath,
                'width' => $image->width(),
                'height' => $image->height(),
                'size' => Storage::disk('public')->size($optimizedPath),
            ];
        } catch (\Throwable $e) {
            Log::warning('Image optimization failed, storing original', [
                'error' => $e->getMessage(),
            ]);

            // Fallback: store original without optimization
            $path = $file->store($directory, 'public');

            return [
                'path' => $path,
                'thumbnail' => null,
                'width' => null,
                'height' => null,
                'size' => $file->getSize(),
            ];
        }
    }

    /**
     * Override the maximum dimensions for resizing.
     */
    public function setMaxDimensions(int $width, int $height): self
    {
        $this->maxWidth = $width;
        $this->maxHeight = $height;

        return $this;
    }

    /**
     * Override the WebP compression quality (1-100).
     */
    public function setQuality(int $quality): self
    {
        $this->quality = max(1, min(100, $quality));

        return $this;
    }

    /**
     * Override the thumbnail size (square crop).
     */
    public function setThumbnailSize(int $size): self
    {
        $this->thumbnailSize = max(16, $size);

        return $this;
    }
}
