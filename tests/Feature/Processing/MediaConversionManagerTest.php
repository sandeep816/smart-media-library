<?php

namespace FilamentMediaLibrary\Tests\Feature\Processing;

use FilamentMediaLibrary\Contracts\MediaConversionManager;
use FilamentMediaLibrary\Contracts\MediaStorage;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;

class MediaConversionManagerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public', [
            'url' => 'https://example.test/storage',
            'visibility' => 'public',
        ]);
        Storage::fake('private');
    }

    public function test_generates_all_configured_presets_for_valid_image(): void
    {
        $manager = app(MediaConversionManager::class);
        $media = $this->createImageMedia(1200, 800, 'test-photo.jpg');

        $results = $manager->generate($media);

        $this->assertArrayHasKey('thumbnail', $results);
        $this->assertArrayHasKey('small', $results);
        $this->assertArrayHasKey('medium', $results);

        // Verify storage paths
        $this->assertTrue(Storage::disk('public')->exists($results['thumbnail']['path']));
        $this->assertTrue(Storage::disk('public')->exists($results['small']['path']));
        $this->assertTrue(Storage::disk('public')->exists($results['medium']['path']));

        // Verify metadata was persisted to Media model
        $media->refresh();
        $this->assertTrue($media->hasConversion('thumbnail'));
        $this->assertTrue($media->hasConversion('small'));
        $this->assertTrue($media->hasConversion('medium'));

        // Thumbnail bounds: 150x150, input: 1200x800 (3:2) => 150x100
        $thumbnail = $media->getConversion('thumbnail');
        $this->assertSame(150, $thumbnail['width']);
        $this->assertSame(100, $thumbnail['height']);
        $this->assertGreaterThan(0, $thumbnail['size']);
    }

    public function test_generates_single_requested_preset(): void
    {
        $manager = app(MediaConversionManager::class);
        $media = $this->createImageMedia(800, 600, 'single-preset.jpg');

        $results = $manager->generate($media, 'thumbnail');

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('thumbnail', $results);
        $this->assertArrayNotHasKey('small', $results);

        $media->refresh();
        $this->assertTrue($media->hasConversion('thumbnail'));
        $this->assertFalse($media->hasConversion('small'));
    }

    public function test_skips_non_image_media_gracefully(): void
    {
        $manager = app(MediaConversionManager::class);

        $docMedia = Media::query()->create([
            'disk' => 'public',
            'directory' => 'media/2026/09',
            'filename' => 'document.pdf',
            'original_filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 2048,
        ]);

        $results = $manager->generate($docMedia);

        $this->assertSame([], $results);
        $this->assertFalse($docMedia->hasConversion('thumbnail'));
    }

    public function test_handles_missing_original_file_gracefully(): void
    {
        $manager = app(MediaConversionManager::class);

        $ghostMedia = Media::query()->create([
            'disk' => 'public',
            'directory' => 'media/2026/09',
            'filename' => 'missing-on-disk.jpg',
            'original_filename' => 'missing-on-disk.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 1024,
        ]);

        $results = $manager->generate($ghostMedia);

        $this->assertSame([], $results);
    }

    public function test_regenerate_overwrites_existing_conversions(): void
    {
        $manager = app(MediaConversionManager::class);
        $media = $this->createImageMedia(1000, 1000, 'regenerate.jpg');

        $manager->generate($media);
        $media->refresh();
        $firstGeneratedAt = $media->getConversion('thumbnail')['generated_at'];

        $this->travel(2)->seconds();

        $manager->regenerate($media);
        $media->refresh();
        $secondGeneratedAt = $media->getConversion('thumbnail')['generated_at'];

        $this->assertNotSame($firstGeneratedAt, $secondGeneratedAt);
    }

    public function test_delete_conversions_removes_physical_files_and_cleans_metadata(): void
    {
        $manager = app(MediaConversionManager::class);
        $media = $this->createImageMedia(800, 800, 'to-delete.jpg');

        $manager->generate($media);
        $media->refresh();

        $thumbPath = $media->getConversion('thumbnail')['path'];
        $this->assertTrue(Storage::disk('public')->exists($thumbPath));

        $manager->deleteConversions($media);
        $media->refresh();

        $this->assertFalse(Storage::disk('public')->exists($thumbPath));
        $this->assertFalse($media->hasConversion('thumbnail'));
        // Original remains untouched
        $this->assertTrue(Storage::disk('public')->exists(app(MediaStorage::class)->path($media)));
    }

    public function test_conversion_url_resolves_for_public_disk_and_null_for_private_disk(): void
    {
        $manager = app(MediaConversionManager::class);

        // 1. Public Disk
        $publicMedia = $this->createImageMedia(800, 800, 'public.jpg', 'public');
        $manager->generate($publicMedia);

        $publicUrl = $manager->getConversionUrl($publicMedia, 'thumbnail');
        $this->assertNotNull($publicUrl);
        $this->assertStringContainsString('conversions/', $publicUrl);

        // 2. Private Disk (without public URL configured)
        $privateMedia = $this->createImageMedia(800, 800, 'private.jpg', 'private');
        $manager->generate($privateMedia);

        $privateUrl = $manager->getConversionUrl($privateMedia, 'thumbnail');
        $this->assertNull($privateUrl, 'Private disk conversion URL must not be leaked publicly.');
    }

    public function test_media_model_conversion_url_falls_back_to_original_when_conversion_missing(): void
    {
        $media = $this->createImageMedia(500, 500, 'fallback.jpg', 'public');

        // Conversion not generated yet
        $url = $media->conversionUrl('thumbnail');

        // Should fall back to the original media URL
        $storage = app(MediaStorage::class);
        $this->assertSame($storage->url($media), $url);
    }

    public function test_soft_delete_and_restore_preserves_conversions_and_metadata(): void
    {
        $manager = app(MediaConversionManager::class);
        $storage = app(MediaStorage::class);
        $media = $this->createImageMedia(800, 600, 'preserve-on-trash.jpg', 'public');

        $manager->generate($media);
        $media->refresh();

        $originalPath = $storage->path($media);
        $thumbPath = $media->getConversion('thumbnail')['path'];
        $smallPath = $media->getConversion('small')['path'];
        $mediumPath = $media->getConversion('medium')['path'];

        $this->assertTrue(Storage::disk('public')->exists($originalPath));
        $this->assertTrue(Storage::disk('public')->exists($thumbPath));
        $this->assertTrue(Storage::disk('public')->exists($smallPath));
        $this->assertTrue(Storage::disk('public')->exists($mediumPath));
        $this->assertTrue($media->hasConversion('thumbnail'));

        // 1. Move to Trash / Soft Delete
        $media->delete();
        $this->assertTrue($media->trashed());

        // Invariant: Soft delete MUST NOT physically delete original or conversions
        $this->assertTrue(Storage::disk('public')->exists($originalPath), 'Original file must remain intact on soft delete.');
        $this->assertTrue(Storage::disk('public')->exists($thumbPath), 'Thumbnail must remain intact on soft delete.');
        $this->assertTrue(Storage::disk('public')->exists($smallPath), 'Small derivative must remain intact on soft delete.');
        $this->assertTrue(Storage::disk('public')->exists($mediumPath), 'Medium derivative must remain intact on soft delete.');

        // Metadata must remain intact while in trash
        $freshTrashed = Media::withTrashed()->find($media->id);
        $this->assertNotNull($freshTrashed);
        $this->assertTrue($freshTrashed->hasConversion('thumbnail'));
        $this->assertTrue($freshTrashed->hasConversion('small'));
        $this->assertTrue($freshTrashed->hasConversion('medium'));

        // 2. Restore from Trash
        $freshTrashed->restore();
        $media->refresh();

        $this->assertFalse($media->trashed());
        $this->assertTrue(Storage::disk('public')->exists($originalPath), 'Original file must still exist after restore.');
        $this->assertTrue(Storage::disk('public')->exists($thumbPath), 'Thumbnail must still exist after restore.');
        $this->assertTrue(Storage::disk('public')->exists($smallPath), 'Small derivative must still exist after restore.');
        $this->assertTrue(Storage::disk('public')->exists($mediumPath), 'Medium derivative must still exist after restore.');

        // URLs resolve immediately without requiring regeneration
        $resolvedThumbUrl = $media->conversionUrl('thumbnail');
        $this->assertNotNull($resolvedThumbUrl);
        $this->assertStringContainsString('conversions/', $resolvedThumbUrl);
        $this->assertTrue($media->hasConversion('thumbnail'));
    }

    public function test_metadata_mutation_preserves_unrelated_metadata_and_other_presets(): void
    {
        $manager = app(MediaConversionManager::class);
        $media = $this->createImageMedia(1000, 800, 'meta-test.jpg', 'public');

        // Seed unrelated custom metadata
        $media->metadata = [
            'source' => 'field-research',
            'photographer' => 'Jane Doe',
            'tags' => ['temple', 'archaeology'],
            'camera' => ['iso' => 200, 'aperture' => 'f/2.8'],
        ];
        $media->save();

        // 1. Generate all conversions
        $manager->generate($media);
        $media->refresh();

        // Verify unrelated metadata was preserved
        $this->assertSame('field-research', $media->metadata['source']);
        $this->assertSame('Jane Doe', $media->metadata['photographer']);
        $this->assertSame(['temple', 'archaeology'], $media->metadata['tags']);
        $this->assertSame(['iso' => 200, 'aperture' => 'f/2.8'], $media->metadata['camera']);

        // Verify conversions were added to metadata
        $this->assertTrue($media->hasConversion('thumbnail'));
        $this->assertTrue($media->hasConversion('small'));
        $this->assertTrue($media->hasConversion('medium'));

        $smallPath = $media->getConversion('small')['path'];
        $mediumPath = $media->getConversion('medium')['path'];

        // 2. Regenerate ONLY the 'thumbnail' preset
        $manager->generate($media, 'thumbnail', force: true);
        $media->refresh();

        // Verify unrelated metadata is STILL preserved
        $this->assertSame('field-research', $media->metadata['source']);
        $this->assertSame('Jane Doe', $media->metadata['photographer']);
        $this->assertSame(['temple', 'archaeology'], $media->metadata['tags']);
        $this->assertSame(['iso' => 200, 'aperture' => 'f/2.8'], $media->metadata['camera']);

        // Verify other presets were NOT lost
        $this->assertTrue($media->hasConversion('thumbnail'));
        $this->assertTrue($media->hasConversion('small'));
        $this->assertTrue($media->hasConversion('medium'));
        $this->assertSame($smallPath, $media->getConversion('small')['path']);
        $this->assertSame($mediumPath, $media->getConversion('medium')['path']);
    }

    public function test_conversion_path_includes_media_directory_and_never_collides(): void
    {
        $media1 = $this->createImageMedia(600, 600, 'photo.jpg', 'public');
        $media2 = $this->createImageMedia(600, 600, 'photo.jpg', 'public');

        $manager = app(MediaConversionManager::class);
        $manager->generate($media1);
        $manager->generate($media2);

        $path1 = $media1->getConversion('thumbnail')['path'];
        $path2 = $media2->getConversion('thumbnail')['path'];

        // Must include directory
        $this->assertStringStartsWith('media/2026/09/conversions/', $path1);
        $this->assertStringStartsWith('media/2026/09/conversions/', $path2);

        // Different UUIDs guarantee no collision
        $this->assertNotSame($path1, $path2);
        $this->assertStringContainsString($media1->uuid, $path1);
        $this->assertStringContainsString($media2->uuid, $path2);

        // No path traversal, no URLs, no absolute paths
        $this->assertStringNotContainsString('..', $path1);
        $this->assertStringNotContainsString('http://', $path1);
        $this->assertStringNotContainsString('https://', $path1);
        $this->assertFalse(str_starts_with($path1, '/'));
    }

    public function test_conversion_url_returns_null_on_private_disk(): void
    {
        $media = $this->createImageMedia(400, 400, 'private-photo.jpg', 'local');
        $manager = app(MediaConversionManager::class);
        $manager->generate($media);
        $media->refresh();

        $this->assertTrue($media->hasConversion('thumbnail'));

        // Private disk conversion URL must return null
        $this->assertNull($manager->getConversionUrl($media, 'thumbnail'));
        $this->assertNull($media->conversionUrl('thumbnail'));
    }

    public function test_conversion_url_fails_gracefully_on_unsupported_or_throwing_disk(): void
    {
        $media = Media::query()->create([
            'disk' => 'unsupported_disk',
            'directory' => 'media/2026/09',
            'filename' => 'nonexistent.jpg',
            'original_filename' => 'nonexistent.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 100,
            'metadata' => [
                'conversions' => [
                    'thumbnail' => [
                        'path' => 'media/2026/09/conversions/test-uuid-thumbnail.jpg',
                        'width' => 150,
                        'height' => 150,
                        'size' => 50,
                        'mime_type' => 'image/jpeg',
                    ],
                ],
            ],
        ]);

        $manager = app(MediaConversionManager::class);

        // When disk does not exist or throws, conversionUrl returns null gracefully
        $this->assertNull($manager->getConversionUrl($media, 'thumbnail'));
        $this->assertNull($media->conversionUrl('thumbnail'));
    }

    private function createImageMedia(int $width, int $height, string $filename, string $disk = 'public'): Media
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 100, 150, 200);
        imagefill($image, 0, 0, $color);

        ob_start();
        imagejpeg($image, null, 90);
        $binary = ob_get_clean();
        imagedestroy($image);

        $path = "media/2026/09/{$filename}";
        Storage::disk($disk)->put($path, $binary);

        return Media::query()->create([
            'disk' => $disk,
            'directory' => 'media/2026/09',
            'filename' => $filename,
            'original_filename' => $filename,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => strlen($binary),
            'width' => $width,
            'height' => $height,
        ]);
    }
}
