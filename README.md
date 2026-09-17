# Smart Media Library

[![Tests](https://github.com/sandeep816/smart-media-library/actions/workflows/tests.yml/badge.svg)](https://github.com/sandeep816/smart-media-library/actions/workflows/tests.yml)

A powerful, reusable media library for Laravel & Filament.

Smart Media Library provides a centralized WordPress-style media management experience for Laravel applications using Filament.

Designed as an independent, reusable package that can be dropped into any Laravel application running Filament 5 without coupling to specific host models or hardcoded panel configurations.

---

## Features

- **Central Media Management**: Dedicated Filament panel page with searchable, filterable grid and list views.
- **Universal MediaPicker Field**: Plug-and-play Filament form component supporting both Value Mode (UUID strings/arrays) and Attachment Mode (polymorphic relationships).
- **Zero-Coupling Storage**: Storage driver agnostic supporting local, public, and private disks (S3, MinIO, etc.) via Laravel's Filesystem abstraction.
- **Granular Authorization**: Flexible permission hooks supporting Closures, Laravel Model Policies on `Media`, and Laravel Gate abilities.
- **Multi-Panel Ready**: Register the plugin across multiple Filament panels with customizable navigation labels, groups, icons, titles, and subheadings.
- **Deterministic Ordering**: Visual reordering for multiple media selections.
- **Strict Type Validation**: Restrict selections and uploads to images, videos, documents, or custom MIME types.
- **Polymorphic Compatibility**: Robust support for integer IDs, BigIncrements, UUIDs, and ULIDs as primary keys on host models.
- **Asset Immutability**: Detaching or soft-deleting media relationships never deletes physical storage files or central records.
- **Image Processing & Derivatives**: Built-in conversion pipeline generating `thumbnail`, `small`, and `medium` variants.
- **Trash & Restore Lifecycle**: Soft-delete support preserving original files and conversion derivatives on deletion with instant restore.
- **Public & Private Disk Awareness**: Secure URL resolution with automatic privacy safeguards for non-public disks.

---

## Requirements & Compatibility

### Declared Constraints (`composer.json`)
- **PHP**: `^8.3`
- **Laravel Framework**: `^12.0 || ^13.0`
- **Filament**: `^5.8`
- **PHP Extensions**: `gd`, `exif`, `fileinfo`

### Verified Environment Matrix
- **Development Host**: PHP 8.3.30, Laravel 13.32.0, Filament 5.8.2, MySQL 8.0
- **Fresh-Host Clean Installation**: PHP 8.5.0, Laravel 13.32.0, Filament 5.8.2, SQLite
- **GitHub Actions CI Matrix (Verified)**:
  - PHP 8.3 + Laravel 12 + Filament 5.8: **PASS**
  - PHP 8.3 + Laravel 13 + Filament 5.8: **PASS**
  - PHP 8.4 + Laravel 12 + Filament 5.8: **PASS**
  - PHP 8.4 + Laravel 13 + Filament 5.8: **PASS**
  - PHP 8.5 + Laravel 13 + Filament 5.8: **PASS**

---

## Installation

### 1. Require the Package

Install the package via Composer:

```bash
composer require sandeep816/smart-media-library
```

#### Local Package Development

If developing or testing locally via a Composer path repository in your host application's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "/path/to/filament-media-library"
    }
]
```

Then require the development package:

```bash
composer require sandeep816/smart-media-library:@dev
```

### 2. Publish Configuration & Migrations

Publish the package configuration:

```bash
php artisan vendor:publish --tag="filament-media-library-config"
```

Publish and run the database migrations:

```bash
php artisan vendor:publish --tag="filament-media-library-migrations"
php artisan migrate
```

*(Optional)* If you wish to customize Blade views or front-end CSS:

```bash
php artisan vendor:publish --tag="filament-media-library-views"
php artisan vendor:publish --tag="filament-media-library-assets"
```

---

## Filament Panel Registration

Register the `MediaLibraryPlugin` in your Filament Panel Provider (e.g., `app/Providers/Filament/AdminPanelProvider.php`):

```php
use FilamentMediaLibrary\MediaLibraryPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->plugins([
            MediaLibraryPlugin::make(),
        ]);
}
```

### Fluent Plugin Customization

You can customize navigation, page headers, and authorization per panel:

```php
MediaLibraryPlugin::make()
    ->navigationLabel('Media Assets')
    ->navigationGroup('Content')
    ->navigationIcon('heroicon-o-folder-open')
    ->navigationSort(15)
    ->shouldRegisterNavigation(true)
    ->title('Digital Assets Library')
    ->subheading('Browse, search, and upload central media files.')
    ->authorizeUsing(function ($user, string $ability, ?Media $media = null): bool {
        return $user->hasRole('admin') || $user->hasRole('editor');
    });
```

---

## Configuration Reference

The published `config/filament-media-library.php` file provides extensive options:

```php
return [
    // Storage disk to store uploads (must exist in config/filesystems.php)
    'default_disk' => env('FILAMENT_MEDIA_LIBRARY_DISK', 'public'),

    // Base storage folder path on the disk
    'default_directory' => env('FILAMENT_MEDIA_LIBRARY_DIR', 'media'),

    // Table names
    'table_name' => 'media',
    'attachments_table_name' => 'media_attachments',

    // Upload constraints
    'upload' => [
        'max_size_kb' => (int) env('FILAMENT_MEDIA_LIBRARY_MAX_SIZE_KB', 10240), // 10 MB
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/svg+xml',
            'application/pdf',
            'video/mp4',
            'video/webm',
            'audio/mpeg',
            'audio/wav',
        ],
    ],

    // UI Pagination
    'pagination' => [
        'per_page' => 24,
    ],

    // Navigation & Page defaults
    'page_title' => 'Media Library',
    'page_subheading' => 'Manage and reuse your files from one central library.',
    'navigation_label' => 'Media Library',
    'navigation_group' => null,
    'navigation_icon' => 'heroicon-o-photo',
    'navigation_sort' => null,
    'should_register_navigation' => true,
];
```

---

## Authorization & Security

Smart Media Library provides three tiers of authorization:

### 1. Fluent Plugin Closure
Define rules directly when registering the plugin in your panel:

```php
MediaLibraryPlugin::make()
    ->authorizeUsing(function ($user, string $ability, ?Media $media = null): bool {
        if ($ability === 'upload') {
            return $user->can('upload_media');
        }
        return true;
    });
```

Or globally via the `MediaAuthorization` facade/class:

```php
use FilamentMediaLibrary\Support\MediaAuthorization;

MediaAuthorization::authorizeUsing('delete', fn ($user, $media) => $user->is_super_admin);
```

### 2. Laravel Model Policy
Define a standard Laravel policy for `FilamentMediaLibrary\Models\Media` in `AuthServiceProvider` or auto-discovery:

```php
namespace App\Policies;

use App\Models\User;
use FilamentMediaLibrary\Models\Media;

class MediaPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function create(User $user): bool { return $user->can('create_media'); }
    public function update(User $user, Media $media): bool { return $user->id === $media->uploaded_by; }
    public function delete(User $user, Media $media): bool { return $user->is_admin; }
    public function restore(User $user, Media $media): bool { return $user->is_admin; }
    public function forceDelete(User $user, Media $media): bool { return $user->is_super_admin; }
    public function view(User $user, Media $media): bool { return true; }
}
```

### 3. Laravel Gate Abilities
Define abilities prefixed with `filament-media-library.`:

```php
Gate::define('filament-media-library.upload', fn ($user) => $user->hasVerifiedEmail());
Gate::define('filament-media-library.delete', fn ($user, $media) => $user->is_admin);
```

*Supported abilities: `view-any`, `upload`, `update`, `trash`, `restore`, `delete`, `select`.*

---

## MediaPicker Form Component

The `MediaPicker` form component embeds seamlessly into any Filament Form or Resource.

### Value Mode (Single Media)
Stores the Media record's UUID string directly in a model attribute:

```php
use FilamentMediaLibrary\Filament\Forms\Components\MediaPicker;

MediaPicker::make('featured_image_uuid')
    ->label('Featured Image')
    ->image();
```

### Value Mode (Multiple Media)
Stores an array of UUID strings (typically in a JSON or text column):

```php
MediaPicker::make('gallery_uuids')
    ->label('Project Gallery')
    ->multiple()
    ->reorderable()
    ->maxItems(8)
    ->image();
```

### Attachment Mode (Polymorphic Relationships)
Synchronizes media attachments using the `media_attachments` table without altering your host model's schema:

```php
MediaPicker::make('hero_banner')
    ->label('Hero Banner')
    ->relationship('hero')
    ->image();

MediaPicker::make('documents')
    ->label('Case Study PDFs')
    ->relationship('case_studies')
    ->multiple()
    ->document();
```

### Type Constraints & Upload Control
Restrict what users can select or upload:

```php
// Only allow image selection
MediaPicker::make('avatar')->image();

// Only allow PDF documents
MediaPicker::make('manual')->document();

// Custom MIME types
MediaPicker::make('assets')->acceptedMediaTypes(['image/svg+xml', 'application/pdf']);

// Disable inline uploads inside the picker modal
MediaPicker::make('archive_photo')->allowUpload(false);
```

---

## Interacting with Attachments in Eloquent

Your Eloquent models can optionally use the `InteractsWithMediaAttachments` trait:

```php
namespace App\Models;

use FilamentMediaLibrary\Concerns\InteractsWithMediaAttachments;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use InteractsWithMediaAttachments;
}
```

### Querying Attached Media:

```php
$post = Post::find(1);

// Get single media record
$heroMedia = $post->singleMediaFor('hero');

// Get collection of media records ordered by sort order
$gallery = $post->mediaFor('gallery');

// Get array of UUIDs
$uuids = $post->mediaUuidsFor('gallery');
```

### Managing Attachments Programmatically:

```php
// Sync attachments for a collection: syncMedia(string $collection, array $mediaUuids)
$post->syncMedia('gallery', ['uuid-1', 'uuid-2']);

// Attach additional media: attachMedia(string $collection, string|array $mediaUuids)
$post->attachMedia('gallery', 'uuid-3');

// Detach specific media: detachMedia(string $collection, ?array $mediaUuids)
$post->detachMedia('gallery', ['uuid-3']);

// Detach entire collection
$post->detachMedia('gallery');
```

---

## Programmatic Uploading

You can upload files from controllers, CLI commands, or jobs using `MediaUploader`:

```php
use FilamentMediaLibrary\Contracts\MediaUploader;
use FilamentMediaLibrary\Support\MediaUploadOptions;

$media = app(MediaUploader::class)->upload(
    $request->file('avatar'),
    new MediaUploadOptions(
        disk: 'public',
        directory: 'avatars',
        title: 'User Profile Picture',
        altText: 'Headshot portrait',
        uploadedBy: (string) auth()->id(),
        metadata: ['ip' => $request->ip()],
    ),
);
```

---

## Image Processing & Derivatives

The package includes a built-in, lightweight, zero-dependency image derivative system designed to generate optimized previews and thumbnails without external third-party services or heavy dependencies:

- **Original Immutability Guarantee**: The original uploaded file is permanently preserved and remains completely immutable. Derivative generation never overwrites or alters original assets.
- **Default Presets**:
  - `thumbnail`: 150 × 150 (fit within bounds, aspect-ratio preserved, no upscaling)
  - `small`: 400 × 400 (fit within bounds, aspect-ratio preserved, no upscaling)
  - `medium`: 800 × 800 (fit within bounds, aspect-ratio preserved, no upscaling)
- **Safe Handling**:
  - **Animated GIFs**: Preserved without rasterization or frame flattening.
  - **SVGs**: Vector assets are passed through safely without raster distortion.
  - **Non-Images**: Documents, audio, video, and archives safely skip derivative generation without errors.
  - **Orientation & Transparency**: Automatically respects EXIF orientation for JPEGs and preserves full 8-bit alpha transparency for PNG and WebP images.
- **Public & Private Disk Safety**: Derivations are stored on the same disk as the source media. Private disk conversions are never exposed with insecure public URLs; secure temporary URLs or custom controllers can be used identically to original assets.
- **Pluggable Architecture**: Built on the `ImageProcessor` interface with an out-of-the-box `GdImageProcessor` (and easily replaceable with Imagick or custom cloud processors).

### Model Conversion API

The `Media` model provides intuitive methods for accessing derivatives:

```php
// Check if a conversion exists
if ($media->hasConversion('thumbnail')) {
    // Returns the URL for the conversion (or null)
    $thumbUrl = $media->conversionUrl('thumbnail');
}

// Retrieve full conversion metadata array
$medium = $media->getConversion('medium');
// [
//     'preset' => 'medium',
//     'path' => 'media/conversions/uuid-medium.webp',
//     'disk' => 'public',
//     'width' => 800,
//     'height' => 600,
//     'mime_type' => 'image/webp',
//     'size_bytes' => 45120,
//     'created_at' => 1718000000,
// ]
```

### Configuration & Custom Presets

Publish `filament-media-library.php` to customize presets or output formats:

```php
'conversions' => [
    'enabled' => true,
    'directory' => 'conversions',
    'presets' => [
        'thumbnail' => [
            'width' => 150,
            'height' => 150,
            'quality' => 80,
            'format' => 'webp', // 'webp', 'jpg', 'png', or null for original
        ],
        'small' => [
            'width' => 400,
            'height' => 400,
            'quality' => 85,
            'format' => 'webp',
        ],
        'medium' => [
            'width' => 800,
            'height' => 800,
            'quality' => 85,
            'format' => 'webp',
        ],
    ],
],
```

### CLI Command: Regenerate Derivatives

You can generate or rebuild derivatives at any time using the Artisan command:

```bash
# Regenerate missing conversions for all media
php artisan media-library:regenerate

# Force regeneration of all conversions (even if they exist)
php artisan media-library:regenerate --force

# Regenerate only a specific preset
php artisan media-library:regenerate --preset=thumbnail --force
```

---

## Clean Architecture & Boundary Isolation

- **No Application Coupling**: Zero runtime references to `App\` or specific host database tables.
- **Safe Compensating Transactions**: If database persistence fails during an upload, storage artifacts are automatically purged.
- **Asset Immutability**: Detaching attachments or deleting models never deletes physical media files. Central deletion must be explicitly initiated.
- **Strict Code Style**: 100% PSR-12 compliant, validated by Laravel Pint and covered by automated PHPUnit feature tests.

## Independence Disclaimer

Smart Media Library is an independent open-source community package for Filament. It is not affiliated with or endorsed by Filament.

---

## Development & Testing

Smart Media Library includes a standalone test suite powered by Orchestra Testbench and PHPUnit:

```bash
# Install dependencies
composer install

# Run test suite
composer test

# Run code style linter
composer lint
```

---

## License

The MIT License (MIT).
