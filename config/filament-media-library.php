<?php

use Filament\Support\Icons\Heroicon;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    |
    | The default filesystem disk on which uploaded media items are stored.
    | This disk must be configured in your application's config/filesystems.php.
    |
    | Type: string
    | Default: 'public'
    |
    */
    'default_disk' => 'public',

    /*
    |--------------------------------------------------------------------------
    | Base Storage Directory
    |--------------------------------------------------------------------------
    |
    | The base directory within the storage disk where media files are saved.
    | Media items will be organized into year/month subdirectories beneath this
    | path (e.g., media/2026/09/...).
    |
    | Type: string|null
    | Default: 'media'
    |
    */
    'directory' => 'media',

    /*
    |--------------------------------------------------------------------------
    | Upload Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Controls file upload constraints, including maximum size in kibibytes (KB)
    | and strict allowlist of permitted MIME types. MIME types are validated
    | server-side using file inspection rather than client-reported headers.
    |
    */
    'upload' => [
        // Maximum upload size in kibibytes (KB), where 1 KB equals 1024 bytes.
        // Type: int
        // Default: 10240 (10 MB)
        'max_size_kb' => 10240,

        // Allowlist of MIME types permitted for upload.
        // Type: array<int, string>
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'video/mp4',
            'video/webm',
            'audio/mpeg',
            'audio/wav',
            'audio/x-wav',
            'audio/ogg',
            'application/pdf',
            'text/plain',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Number of media cards displayed per page in both the central Media
    | Library interface and the MediaPicker selection modal.
    |
    | Type: int
    | Default: 24
    |
    */
    'pagination' => [
        'per_page' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    |
    | Configurable table names for media records and polymorphic media
    | attachments. Configure these before running package migrations.
    |
    | Type: array{media: string, attachments: string}
    |
    */
    'table_names' => [
        'media' => 'media',
        'attachments' => 'media_attachments',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Navigation & Page Appearance
    |--------------------------------------------------------------------------
    |
    | Configures default display attributes for the central Media Library page
    | within the Filament panel. These can also be overridden per-panel using
    | fluent methods on the MediaLibraryPlugin instance.
    |
    */
    'page_title' => 'Media Library',
    'page_subheading' => 'Manage and reuse your files from one central library.',

    'should_register_navigation' => true,
    'navigation_label' => 'Media Library',
    'navigation_icon' => Heroicon::OutlinedPhoto,
    'navigation_group' => null,
    'navigation_sort' => null,

    /*
    |--------------------------------------------------------------------------
    | Image Conversions (Derivatives)
    |--------------------------------------------------------------------------
    |
    | Configuration for generating optimized image derivatives/conversions.
    | The original uploaded image remains immutable. Presets define maximum
    | width and height bounds; aspect ratios are strictly preserved without
    | distortion or upscaling.
    |
    */
    'conversions' => [
        'enabled' => env('FILAMENT_MEDIA_LIBRARY_CONVERSIONS_ENABLED', true),

        'directory' => 'conversions',

        'presets' => [
            'thumbnail' => [
                'width' => 150,
                'height' => 150,
                'quality' => 80,
                'format' => null, // null preserves source format; 'webp' converts with automatic fallback
            ],
            'small' => [
                'width' => 400,
                'height' => 400,
                'quality' => 85,
                'format' => null,
            ],
            'medium' => [
                'width' => 800,
                'height' => 800,
                'quality' => 85,
                'format' => null,
            ],
        ],
    ],
];
