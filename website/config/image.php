<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Image Optimization Settings
    |--------------------------------------------------------------------------
    |
    | These values control how uploaded images are processed by the
    | ImageOptimizationService and the OptimizeImages middleware.
    |
    */

    // Maximum width in pixels. Images wider than this are scaled down.
    'max_width' => (int) env('IMAGE_MAX_WIDTH', 1920),

    // Maximum height in pixels. Images taller than this are scaled down.
    'max_height' => (int) env('IMAGE_MAX_HEIGHT', 1080),

    // WebP compression quality (1 = smallest file, 100 = best quality).
    'quality' => (int) env('IMAGE_QUALITY', 80),

    // Thumbnail square dimension in pixels.
    'thumbnail_size' => (int) env('IMAGE_THUMBNAIL_SIZE', 200),

    // Default storage subdirectory for optimised uploads.
    'upload_directory' => env('IMAGE_UPLOAD_DIRECTORY', 'images'),

];
