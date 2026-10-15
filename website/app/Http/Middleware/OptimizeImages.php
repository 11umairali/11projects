<?php

namespace App\Http\Middleware;

use App\Services\ImageOptimizationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that automatically optimizes uploaded image files.
 *
 * Attach to routes that accept image uploads. The middleware intercepts
 * image files before they reach the controller, optimises them via
 * ImageOptimizationService, and replaces the raw upload with the
 * optimised result stored in `$request->attributes`.
 *
 * Usage:
 *   Route::post('/upload', Controller::class)->middleware('optimize.images');
 *
 * In your controller you can then access:
 *   $optimized = $request->attributes->get('optimized_images');
 */
class OptimizeImages
{
    public function __construct(
        protected ImageOptimizationService $optimizer,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasFile('image') && ! $request->hasFile('images') && ! $request->hasFile('avatar')) {
            return $next($request);
        }

        $optimized = [];
        $directory = config('image.upload_directory', 'images');
        $maxWidth  = config('image.max_width', 1920);
        $maxHeight = config('image.max_height', 1080);
        $quality   = config('image.quality', 80);

        $this->optimizer
            ->setMaxDimensions($maxWidth, $maxHeight)
            ->setQuality($quality);

        // Process single image field
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $optimized['image'] = $this->optimizer->optimize(
                $request->file('image'),
                $directory,
            );
        }

        // Process avatar field
        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            $optimized['avatar'] = $this->optimizer
                ->setMaxDimensions(512, 512)
                ->optimize($request->file('avatar'), 'avatars');
        }

        // Process multiple images field
        if ($request->hasFile('images')) {
            $files = $request->file('images');
            $optimized['images'] = [];

            foreach ($files as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $optimized['images'][] = $this->optimizer->optimize($file, $directory);
                }
            }
        }

        // Attach optimised metadata so controllers can access it
        $request->attributes->set('optimized_images', $optimized);

        return $next($request);
    }
}
