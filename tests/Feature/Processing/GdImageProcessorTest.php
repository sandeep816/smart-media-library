<?php

namespace FilamentMediaLibrary\Tests\Feature\Processing;

use FilamentMediaLibrary\Services\GdImageProcessor;
use FilamentMediaLibrary\Tests\TestCase;

class GdImageProcessorTest extends TestCase
{
    private GdImageProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new GdImageProcessor;
    }

    public function test_can_process_supported_image_types(): void
    {
        $this->assertTrue($this->processor->canProcess('image/jpeg'));
        $this->assertTrue($this->processor->canProcess('image/png'));
        $this->assertTrue($this->processor->canProcess('image/webp'));
        $this->assertTrue($this->processor->canProcess('image/gif'));

        $this->assertFalse($this->processor->canProcess('application/pdf'));
        $this->assertFalse($this->processor->canProcess('video/mp4'));
        $this->assertFalse($this->processor->canProcess('image/svg+xml'));
    }

    public function test_resizes_landscape_image_preserving_aspect_ratio(): void
    {
        $imageBinary = $this->createTestImage(1000, 500, 'jpeg');

        // Bounded to 400x300 => target should be 400x200
        $result = $this->processor->resize($imageBinary, 400, 300, 'image/jpeg', 80);

        $this->assertNotNull($result);
        $this->assertSame(400, $result->width);
        $this->assertSame(200, $result->height);
        $this->assertSame('image/jpeg', $result->mimeType);
        $this->assertGreaterThan(0, $result->size);
    }

    public function test_resizes_portrait_image_preserving_aspect_ratio(): void
    {
        $imageBinary = $this->createTestImage(500, 1000, 'jpeg');

        // Bounded to 400x300 => target should be 150x300
        $result = $this->processor->resize($imageBinary, 400, 300, 'image/jpeg', 80);

        $this->assertNotNull($result);
        $this->assertSame(150, $result->width);
        $this->assertSame(300, $result->height);
        $this->assertSame('image/jpeg', $result->mimeType);
    }

    public function test_does_not_upscale_smaller_images(): void
    {
        $imageBinary = $this->createTestImage(200, 150, 'png');

        // Bounded to 400x300 => should remain 200x150
        $result = $this->processor->resize($imageBinary, 400, 300, 'image/png', 90);

        $this->assertNotNull($result);
        $this->assertSame(200, $result->width);
        $this->assertSame(150, $result->height);
        $this->assertSame('image/png', $result->mimeType);
    }

    public function test_preserves_png_transparency(): void
    {
        $image = imagecreatetruecolor(100, 100);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        $result = $this->processor->resize($binary, 50, 50, 'image/png');

        $this->assertNotNull($result);
        $this->assertSame(50, $result->width);
        $this->assertSame(50, $result->height);

        $resampled = imagecreatefromstring($result->binaryContent);
        $rgba = imagecolorat($resampled, 25, 25);
        $alpha = ($rgba & 0x7F000000) >> 24;
        imagedestroy($resampled);

        $this->assertSame(127, $alpha);
    }

    public function test_processes_webp_images(): void
    {
        $imageBinary = $this->createTestImage(600, 600, 'webp');

        $result = $this->processor->resize($imageBinary, 300, 300, 'image/webp', 85);

        $this->assertNotNull($result);
        $this->assertSame(300, $result->width);
        $this->assertSame(300, $result->height);
        $this->assertSame('image/webp', $result->mimeType);
    }

    public function test_animated_gif_is_skipped_safely(): void
    {
        // Simple synthetic animated GIF header containing multiple Graphic Control Extension blocks
        $gifHeader = 'GIF89a'."\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff";
        $frame1 = "\x00\x21\xf9\x04\x00\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00";
        $frame2 = "\x00\x21\xf9\x04\x00\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00";
        $gifTrailer = "\x3b";

        $animatedGifBinary = $gifHeader.$frame1.$frame2.$gifTrailer;

        $result = $this->processor->resize($animatedGifBinary, 100, 100, 'image/gif');

        $this->assertNull($result, 'Animated GIFs should be skipped to prevent losing animation.');
    }

    public function test_can_encode_reports_supported_formats(): void
    {
        $this->assertTrue($this->processor->canEncode('jpeg'));
        $this->assertTrue($this->processor->canEncode('jpg'));
        $this->assertTrue($this->processor->canEncode('png'));
        $this->assertTrue($this->processor->canEncode('image/png'));
        $this->assertFalse($this->processor->canEncode('unsupported-format-xyz'));
    }

    public function test_resize_converts_to_webp_when_requested_and_supported(): void
    {
        if (! $this->processor->canEncode('webp')) {
            $this->markTestSkipped('WebP encoding not supported on this environment.');
        }

        $jpegBinary = $this->createTestImage(800, 600, 'jpeg');
        $result = $this->processor->resize($jpegBinary, 400, 300, 'image/jpeg', 85, 'webp');

        $this->assertNotNull($result);
        $this->assertSame('image/webp', $result->mimeType);
        $this->assertSame(400, $result->width);
        $this->assertSame(300, $result->height);
    }

    public function test_resize_gracefully_falls_back_to_source_format_when_requested_format_is_unsupported(): void
    {
        $jpegBinary = $this->createTestImage(800, 600, 'jpeg');

        // Request an unsupported format
        $result = $this->processor->resize($jpegBinary, 400, 300, 'image/jpeg', 85, 'unsupported_xyz');

        $this->assertNotNull($result, 'Image conversion must never fail when requested format is unsupported.');
        $this->assertSame('image/jpeg', $result->mimeType, 'Must fall back to source MIME type.');
        $this->assertSame(400, $result->width);
        $this->assertSame(300, $result->height);
    }

    public function test_webp_conversion_preserves_png_alpha_transparency(): void
    {
        if (! $this->processor->canEncode('webp')) {
            $this->markTestSkipped('WebP encoding not supported on this environment.');
        }

        // Create transparent PNG
        $image = imagecreatetruecolor(100, 100);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        ob_start();
        imagepng($image);
        $pngBinary = ob_get_clean();
        imagedestroy($image);

        // Convert PNG with transparency to WebP
        $result = $this->processor->resize($pngBinary, 50, 50, 'image/png', 85, 'webp');

        $this->assertNotNull($result);
        $this->assertSame('image/webp', $result->mimeType);
        $this->assertSame(50, $result->width);
        $this->assertSame(50, $result->height);

        // Verify transparency in resulting WebP
        $resampled = imagecreatefromstring($result->binaryContent);
        $rgba = imagecolorat($resampled, 25, 25);
        $alpha = ($rgba & 0x7F000000) >> 24;
        imagedestroy($resampled);

        $this->assertSame(127, $alpha, 'Alpha transparency must be preserved in WebP conversion.');
    }

    public function test_corrupt_image_data_fails_gracefully_returning_null(): void
    {
        $corruptData = 'not-a-valid-image-binary-stream';

        $result = $this->processor->resize($corruptData, 200, 200, 'image/jpeg');

        $this->assertNull($result);
    }

    private function createTestImage(int $width, int $height, string $format): string
    {
        $image = imagecreatetruecolor($width, $height);
        $blue = imagecolorallocate($image, 50, 100, 200);
        imagefill($image, 0, 0, $blue);

        ob_start();
        match ($format) {
            'jpeg', 'jpg' => imagejpeg($image, null, 90),
            'png' => imagepng($image),
            'webp' => imagewebp($image, null, 90),
            'gif' => imagegif($image),
        };
        $binary = ob_get_clean();
        imagedestroy($image);

        return $binary;
    }
}
