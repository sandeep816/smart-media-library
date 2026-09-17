<?php

namespace FilamentMediaLibrary\Tests\Feature\Models;

use FilamentMediaLibrary\Contracts\MediaStorage;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Support\MediaType;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

class MediaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_media_persists_with_generated_uuid_casts_and_normalized_attributes(): void
    {
        $media = Media::query()->create($this->mediaAttributes([
            'disk' => null,
            'directory' => 'media//2026/09/',
            'extension' => '.JPG',
            'size' => '2048',
            'width' => '1200',
            'height' => '800',
            'metadata' => ['camera' => 'Example'],
        ]));

        $this->assertModelExists($media);
        $this->assertTrue(Str::isUuid($media->uuid));
        $this->assertSame('public', $media->disk);
        $this->assertSame('media/2026/09', $media->directory);
        $this->assertSame('jpg', $media->extension);
        $this->assertSame(2048, $media->size);
        $this->assertSame(1200, $media->width);
        $this->assertSame(800, $media->height);
        $this->assertSame(['camera' => 'Example'], $media->metadata);
        $this->assertSame(MediaType::Image, $media->type);
    }

    public function test_generated_uuids_are_unique(): void
    {
        $firstMedia = Media::query()->create($this->mediaAttributes());
        $secondMedia = Media::query()->create($this->mediaAttributes([
            'filename' => 'second.jpg',
            'original_filename' => 'second.jpg',
        ]));

        $this->assertNotSame($firstMedia->uuid, $secondMedia->uuid);
    }

    public function test_uuid_cannot_change_after_creation(): void
    {
        $media = Media::query()->create($this->mediaAttributes());
        $media->uuid = (string) Str::uuid();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A media UUID cannot be changed after creation.');

        $media->save();
    }

    public function test_soft_delete_preserves_the_file_and_media_can_be_restored(): void
    {
        Storage::fake('media-test');
        $media = Media::query()->create($this->mediaAttributes(['disk' => 'media-test']));
        Storage::disk('media-test')->put($media->path, 'original file');

        $media->delete();

        $this->assertSoftDeleted($media);
        Storage::disk('media-test')->assertExists('media/2026/09/example.jpg');

        $media->restore();

        $this->assertModelExists($media);
        $this->assertFalse($media->trashed());
        $this->assertTrue($this->app->make(MediaStorage::class)->exists($media));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mediaAttributes(array $overrides = []): array
    {
        return array_replace([
            'disk' => 'public',
            'directory' => 'media/2026/09',
            'filename' => 'example.jpg',
            'original_filename' => 'Example Photo.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 2048,
        ], $overrides);
    }
}
