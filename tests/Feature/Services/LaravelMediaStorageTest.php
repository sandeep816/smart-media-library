<?php

namespace FilamentMediaLibrary\Tests\Feature\Services;

use FilamentMediaLibrary\Contracts\MediaStorage;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Services\LaravelMediaStorage;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class LaravelMediaStorageTest extends TestCase
{
    public function test_storage_contract_resolves_to_the_laravel_implementation(): void
    {
        $storage = $this->app->make(MediaStorage::class);

        $this->assertInstanceOf(LaravelMediaStorage::class, $storage);
    }

    public function test_storage_checks_and_explicitly_deletes_the_resolved_path(): void
    {
        Storage::fake('media-test');
        $media = $this->media(['disk' => 'media-test']);
        $storage = $this->app->make(MediaStorage::class);
        Storage::disk('media-test')->put('media/2026/09/example.jpg', 'contents');

        $this->assertSame('media/2026/09/example.jpg', $storage->path($media));
        $this->assertTrue($storage->exists($media));

        $this->assertTrue($storage->delete($media));
        $this->assertFalse($storage->exists($media));
        Storage::disk('media-test')->assertMissing('media/2026/09/example.jpg');
    }

    public function test_public_local_disk_url_is_returned(): void
    {
        Storage::fake('media-public', [
            'url' => 'https://cdn.example.test/files',
            'visibility' => 'public',
        ]);
        $media = $this->media(['disk' => 'media-public']);

        $url = $this->app->make(MediaStorage::class)->url($media);

        $this->assertSame('https://cdn.example.test/files/media/2026/09/example.jpg', $url);
    }

    public function test_private_local_disk_without_public_url_returns_null(): void
    {
        Storage::fake('media-private');
        $media = $this->media(['disk' => 'media-private']);

        $url = $this->app->make(MediaStorage::class)->url($media);

        $this->assertNull($url);
    }

    public function test_default_public_disk_resolves_url_correctly(): void
    {
        Storage::fake('public');
        $media = $this->media(['disk' => 'public']);

        $url = $this->app->make(MediaStorage::class)->url($media);

        $this->assertNotNull($url);
        $this->assertStringContainsString('/storage/media/2026/09/example.jpg', $url);
    }

    public function test_unsupported_disk_exception_fails_gracefully_returning_null(): void
    {
        // Custom disk that throws an exception when resolving URL
        config()->set('filesystems.disks.broken-disk', [
            'driver' => 'local',
            'root' => storage_path('broken'),
            'visibility' => 'private',
        ]);
        $media = $this->media(['disk' => 'broken-disk']);

        $url = $this->app->make(MediaStorage::class)->url($media);

        $this->assertNull($url);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function media(array $overrides = []): Media
    {
        return Media::query()->make(array_replace([
            'disk' => 'public',
            'directory' => 'media/2026/09',
            'filename' => 'example.jpg',
            'original_filename' => 'example.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 100,
        ], $overrides));
    }
}
