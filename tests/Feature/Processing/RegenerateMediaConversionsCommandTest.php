<?php

namespace FilamentMediaLibrary\Tests\Feature\Processing;

use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;

class RegenerateMediaConversionsCommandTest extends TestCase
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

    public function test_regenerate_command_processes_all_images(): void
    {
        $media1 = $this->createImageMedia('img1.jpg');
        $media2 = $this->createImageMedia('img2.jpg');

        $this->artisan('media-library:regenerate', ['--force' => true])
            ->expectsOutputToContain('Starting media conversions regeneration...')
            ->expectsOutputToContain('Successfully processed: 2 media items')
            ->assertSuccessful();

        $media1->refresh();
        $media2->refresh();

        $this->assertTrue($media1->hasConversion('thumbnail'));
        $this->assertTrue($media2->hasConversion('thumbnail'));
    }

    public function test_regenerate_command_supports_single_preset(): void
    {
        $media = $this->createImageMedia('single-command.jpg');

        $this->artisan('media-library:regenerate', ['--preset' => 'thumbnail'])
            ->assertSuccessful();

        $media->refresh();
        $this->assertTrue($media->hasConversion('thumbnail'));
        $this->assertFalse($media->hasConversion('small'));
    }

    private function createImageMedia(string $filename): Media
    {
        $image = imagecreatetruecolor(600, 400);
        $color = imagecolorallocate($image, 50, 150, 200);
        imagefill($image, 0, 0, $color);

        ob_start();
        imagejpeg($image, null, 85);
        $binary = ob_get_clean();
        imagedestroy($image);

        $path = "media/2026/09/{$filename}";
        Storage::disk('public')->put($path, $binary);

        return Media::query()->create([
            'disk' => 'public',
            'directory' => 'media/2026/09',
            'filename' => $filename,
            'original_filename' => $filename,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => strlen($binary),
            'width' => 600,
            'height' => 400,
        ]);
    }
}
