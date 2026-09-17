# Changelog

All notable changes to `smart-media-library` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-09-17

### Added
- **Central Media Library**: Full-featured digital asset management page with searchable, filterable grid and list views.
- **Secure Upload Pipeline**: Validated MIME allowlist, date-partitioned storage paths, original file immutability, and compensating cleanup on database failures.
- **Public & Private Filesystem Support**: Native Laravel Filesystem integration with automatic URL concealment on private disks.
- **Universal MediaPicker**: Embeddable Filament form component supporting single UUID, multiple UUID arrays, item removal, and drag-and-drop reordering.
- **Reusable Polymorphic Attachments**: `InteractsWithMediaAttachments` trait and attacher service supporting integer IDs, BigIncrements, UUIDs, and ULIDs.
- **Granular Authorization**: Three-tier authorization using fluent closures, Laravel Model Policies on `Media`, or Laravel Gate abilities.
- **Image Conversions & Derivatives**: Built-in zero-dependency GD conversion pipeline generating `thumbnail`, `small`, and `medium` variants.
- **Conversion Safety**: Safe bypass for animated GIFs, SVGs, and non-images; EXIF orientation normalization; PNG/WebP alpha transparency preservation.
- **Regeneration Command**: `php artisan media-library:regenerate` Artisan command with `--preset` and `--force` options.
- **Soft Delete & Restore**: Moving media to trash preserves all storage files and conversion derivatives with instant restore capability.
- **Standalone Test Suite**: Independent test harness using Orchestra Testbench with 125 tests and 593 assertions.
- **GitHub Actions CI Matrix**: Automated matrix testing across PHP 8.3, 8.4, 8.5 with Laravel 12 & 13 and Filament 5.8.
