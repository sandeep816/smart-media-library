<?php

namespace FilamentMediaLibrary\Tests\Feature\Processing;

use FilamentMediaLibrary\Contracts\ImageProcessor;
use FilamentMediaLibrary\Contracts\MediaUploader;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Support\ImageConversionResult;
use FilamentMediaLibrary\Support\MediaUploadOptions;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaUploadConversionIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public', [
            'url' => 'https://example.test/storage',
            'visibility' => 'public',
        ]);
        config()->set('filament-media-library.default_disk', 'public');
    }

    public function test_uploading_image_automatically_generates_conversions_via_event_listener(): void
    {
        $uploader = app(MediaUploader::class);
        $file = UploadedFile::fake()->image('lifecycle-photo.jpg', 1200, 800);

        $media = $uploader->upload($file, new MediaUploadOptions(
            title: 'Lifecycle Image',
        ));

        $this->assertInstanceOf(Media::class, $media);
        $media->refresh();

        // Conversions generated
        $this->assertTrue($media->hasConversion('thumbnail'));
        $this->assertTrue($media->hasConversion('small'));
        $this->assertTrue($media->hasConversion('medium'));

        $thumbnail = $media->getConversion('thumbnail');
        $this->assertNotEmpty($thumbnail['path']);
        $this->assertTrue(Storage::disk('public')->exists($thumbnail['path']));

        // Original file exists and is untouched
        $this->assertTrue(Storage::disk('public')->exists($media->path));
    }

    public function test_conversion_failure_does_not_abort_successful_media_upload(): void
    {
        // Bind an ImageProcessor mock that throws an exception during resize
        $this->app->bind(ImageProcessor::class, function () {
            return new class implements ImageProcessor
            {
                public function canProcess(string $mimeType): bool
                {
                    return true;
                }

                public function canEncode(string $format): bool
                {
                    return true;
                }

                public function resize(
                    string $binaryContent,
                    int $maxWidth,
                    int $maxHeight,
                    string $mimeType,
                    int $quality = 82,
                    ?string $format = null,
                ): ?ImageConversionResult {
                    throw new \RuntimeException('Simulated image processing crash');
                }
            };
        });

        $uploader = app(MediaUploader::class);
        $file = UploadedFile::fake()->image('resilient-upload.jpg', 800, 600);

        // Upload should succeed despite conversion failure
        $media = $uploader->upload($file);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertTrue(Storage::disk('public')->exists($media->path));

        $media->refresh();
        $this->assertFalse($media->hasConversion('thumbnail'));
    }
}
