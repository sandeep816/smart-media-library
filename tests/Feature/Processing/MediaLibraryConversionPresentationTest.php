<?php

namespace FilamentMediaLibrary\Tests\Feature\Processing;

use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use FilamentMediaLibrary\Contracts\MediaConversionManager;
use FilamentMediaLibrary\Filament\Forms\Components\MediaPicker;
use FilamentMediaLibrary\Filament\Pages\MediaLibrary;
use FilamentMediaLibrary\Livewire\MediaPickerModal;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Tests\Fixtures\TestMediaPickerHarness;
use FilamentMediaLibrary\Tests\Fixtures\User;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

class MediaLibraryConversionPresentationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());

        Storage::fake('public', [
            'url' => 'https://example.test/storage',
            'visibility' => 'public',
        ]);
        config()->set('filament-media-library.default_disk', 'public');
    }

    public function test_media_library_page_serves_thumbnail_and_medium_conversions(): void
    {
        $media = $this->createImageMedia('gallery-view.jpg');
        app(MediaConversionManager::class)->generate($media);

        $page = new MediaLibrary;
        $presentation = $page->presentMedia($media);

        $this->assertNotNull($presentation['thumbnail_url']);
        $this->assertNotNull($presentation['medium_url']);
        $this->assertStringContainsString("conversions/{$media->uuid}-thumbnail.jpg", $presentation['thumbnail_url']);
        $this->assertStringContainsString("conversions/{$media->uuid}-medium.jpg", $presentation['medium_url']);
    }

    public function test_media_picker_modal_and_field_consume_thumbnail_conversions(): void
    {
        $media = $this->createImageMedia('picker-preview.jpg');
        app(MediaConversionManager::class)->generate($media);

        // 1. MediaPickerModal presentation
        Livewire::test(MediaPickerModal::class, [
            'statePath' => 'data.image',
            'isMultiple' => false,
            'acceptedTypes' => ['image'],
            'currentState' => null,
        ])
            ->assertSee("conversions/{$media->uuid}-thumbnail.jpg", escape: false);

        // 2. MediaPicker form component presentation
        $harness = new TestMediaPickerHarness;
        $schema = Schema::make($harness);
        $picker = MediaPicker::make('featured_image_uuid');
        $picker->container($schema);
        $picker->state($media->uuid);

        $presentations = $picker->getSelectedMediaPresentations();
        $this->assertCount(1, $presentations);
        $this->assertNotNull($presentations[0]['thumbnail_url']);
        $this->assertStringContainsString("conversions/{$media->uuid}-thumbnail.jpg", $presentations[0]['thumbnail_url']);
    }

    public function test_existing_media_without_conversions_safely_falls_back_to_original_url(): void
    {
        // Media with file on disk but no conversions generated yet
        $media = $this->createImageMedia('legacy-no-conversions.jpg');

        $page = new MediaLibrary;
        $presentation = $page->presentMedia($media);

        // thumbnail_url and medium_url should gracefully fall back to original url
        $this->assertSame($presentation['url'], $presentation['thumbnail_url']);
        $this->assertSame($presentation['url'], $presentation['medium_url']);
        $this->assertStringNotContainsString('conversions/', $presentation['thumbnail_url']);
    }

    private function createImageMedia(string $filename): Media
    {
        $image = imagecreatetruecolor(800, 600);
        $color = imagecolorallocate($image, 200, 100, 50);
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
            'width' => 800,
            'height' => 600,
        ]);
    }
}
