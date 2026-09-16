# Changelog

All notable changes to `smart-media-library` will be documented in this file.

## [1.0.0] - Unreleased

### Added
- **Central Media Library**: Full-featured digital asset library with searchable, filterable grid and list views.
- **Secure Upload Engine**: Upload validation, MIME type allowlist, disk normalization, safe path generation, and transaction-safe compensating deletions.
- **Media Details & Metadata**: View and edit titles, alt text, captions, descriptions, and custom JSON metadata.
- **Trash & Restore Lifecycle**: Soft-delete support preserving original files and conversion derivatives on deletion with instant restore.
- **Public & Private Disk Support**: Native integration with Laravel's Filesystem abstraction, with private disk safety guarantees.
- **Universal MediaPicker**: Embeddable Filament form component supporting both Value Mode (single/multiple UUIDs) and Attachment Mode (polymorphic relationships).
- **Interactive Selection**: Single selection, multiple selection, limit bounds, and drag-and-drop visual reordering.
- **Polymorphic Attachments**: Reusable model attachments supporting standard integers, BigIncrements, UUIDs, and ULIDs.
- **Granular Authorization**: Flexible permission hooks supporting Closures, Laravel Model Policies on `Media`, and Laravel Gate abilities.
- **Multi-Panel Architecture**: Support for registering across multiple Filament panels with customizable navigation, routes, and page headers.
- **Image Processing & Derivatives**: Built-in conversion pipeline generating `thumbnail`, `small`, and `medium` variants.
- **Original Immutability**: Guarantees original uploaded files are never altered, cropped, or overwritten.
- **Conversion Safety**: Safe bypass for animated GIFs and SVGs, automatic EXIF orientation normalization, and PNG/WebP alpha transparency preservation.
- **CLI Regeneration**: Artisan command `php artisan media-library:regenerate` with `--preset` and `--force` support.
