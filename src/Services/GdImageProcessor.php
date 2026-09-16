<?php

namespace FilamentMediaLibrary\Services;

use FilamentMediaLibrary\Contracts\ImageProcessor;
use FilamentMediaLibrary\Support\ImageConversionResult;
use GdImage;
use Throwable;

class GdImageProcessor implements ImageProcessor
{
    /**
     * @var array<string>
     */
    protected array $supportedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    public function canProcess(string $mimeType): bool
    {
        $mimeType = strtolower(trim($mimeType));

        if (! in_array($mimeType, $this->supportedMimeTypes, true)) {
            return false;
        }

        if ($mimeType === 'image/webp' && ! function_exists('imagewebp')) {
            return false;
        }

        return extension_loaded('gd');
    }

    public function canEncode(string $format): bool
    {
        $format = strtolower(trim($format));

        return match ($format) {
            'jpg', 'jpeg', 'image/jpeg' => function_exists('imagejpeg') && (imagetypes() & IMG_JPEG) !== 0,
            'png', 'image/png' => function_exists('imagepng') && (imagetypes() & IMG_PNG) !== 0,
            'webp', 'image/webp' => function_exists('imagewebp') && (imagetypes() & IMG_WEBP) !== 0,
            'gif', 'image/gif' => function_exists('imagegif') && (imagetypes() & IMG_GIF) !== 0,
            default => false,
        };
    }

    public function resize(
        string $binaryContent,
        int $maxWidth,
        int $maxHeight,
        string $mimeType,
        int $quality = 82,
        ?string $format = null,
    ): ?ImageConversionResult {
        if (! $this->canProcess($mimeType) || $binaryContent === '') {
            return null;
        }

        if ($maxWidth <= 0 || $maxHeight <= 0) {
            return null;
        }

        $mimeType = strtolower(trim($mimeType));

        // For GIFs, check if animated. Animated GIFs must not be flattened to a single static frame.
        if ($mimeType === 'image/gif' && $this->isAnimatedGif($binaryContent)) {
            return null;
        }

        // Determine target output format with capability check and graceful fallback
        $targetFormat = $this->determineOutputFormat($mimeType, $format);
        $outputMimeType = $this->formatToMimeType($targetFormat);

        try {
            $sourceImage = @imagecreatefromstring($binaryContent);
        } catch (Throwable) {
            return null;
        }

        if (! $sourceImage instanceof GdImage) {
            return null;
        }

        try {
            // Correct EXIF orientation if available and JPEG
            if ($mimeType === 'image/jpeg' && function_exists('exif_read_data')) {
                $sourceImage = $this->autoOrient($sourceImage, $binaryContent);
            }

            $origWidth = imagesx($sourceImage);
            $origHeight = imagesy($sourceImage);

            if ($origWidth <= 0 || $origHeight <= 0) {
                imagedestroy($sourceImage);

                return null;
            }

            // Calculate bounded dimensions without upscaling
            [$targetWidth, $targetHeight] = $this->calculateTargetDimensions(
                $origWidth,
                $origHeight,
                $maxWidth,
                $maxHeight,
            );

            // Create canvas for the target size
            $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

            if (! $targetImage instanceof GdImage) {
                imagedestroy($sourceImage);

                return null;
            }

            // Preserve alpha channel for PNG, WebP, and GIF
            $this->preserveTransparency($targetImage, $outputMimeType);

            imagecopyresampled(
                $targetImage,
                $sourceImage,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $origWidth,
                $origHeight,
            );

            // Encode to output buffer
            ob_start();
            $encoded = match ($outputMimeType) {
                'image/jpeg' => imagejpeg($targetImage, null, max(1, min(100, $quality))),
                'image/png' => imagepng($targetImage, null, $this->qualityToPngCompression($quality)),
                'image/webp' => imagewebp($targetImage, null, max(1, min(100, $quality))),
                'image/gif' => imagegif($targetImage, null),
                default => false,
            };
            $outputData = ob_get_clean();

            imagedestroy($sourceImage);
            imagedestroy($targetImage);

            if (! $encoded || ! is_string($outputData) || $outputData === '') {
                return null;
            }

            return new ImageConversionResult(
                binaryContent: $outputData,
                width: $targetWidth,
                height: $targetHeight,
                mimeType: $outputMimeType,
                size: strlen($outputData),
            );
        } catch (Throwable) {
            if (isset($sourceImage) && $sourceImage instanceof GdImage) {
                imagedestroy($sourceImage);
            }
            if (isset($targetImage) && $targetImage instanceof GdImage) {
                imagedestroy($targetImage);
            }

            return null;
        }
    }

    /**
     * Calculate target dimensions keeping aspect ratio without upscaling.
     *
     * @return array{0: int, 1: int}
     */
    protected function calculateTargetDimensions(
        int $origWidth,
        int $origHeight,
        int $maxWidth,
        int $maxHeight,
    ): array {
        // If image already fits inside bounding box, do not upscale
        if ($origWidth <= $maxWidth && $origHeight <= $maxHeight) {
            return [$origWidth, $origHeight];
        }

        $widthRatio = $maxWidth / $origWidth;
        $heightRatio = $maxHeight / $origHeight;

        $ratio = min($widthRatio, $heightRatio);

        $targetWidth = (int) max(1, round($origWidth * $ratio));
        $targetHeight = (int) max(1, round($origHeight * $ratio));

        return [$targetWidth, $targetHeight];
    }

    protected function preserveTransparency(GdImage $image, string $mimeType): void
    {
        if (in_array($mimeType, ['image/png', 'image/webp'], true)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            if ($transparent !== false) {
                imagefill($image, 0, 0, $transparent);
            }
        } elseif ($mimeType === 'image/gif') {
            $transparent = imagecolorallocate($image, 255, 255, 255);
            if ($transparent !== false) {
                imagefill($image, 0, 0, $transparent);
                imagecolortransparent($image, $transparent);
            }
        }
    }

    protected function autoOrient(GdImage $image, string $binaryContent): GdImage
    {
        try {
            $stream = fopen('php://memory', 'r+');
            if ($stream === false) {
                return $image;
            }

            fwrite($stream, $binaryContent);
            rewind($stream);

            $exif = @exif_read_data($stream);
            fclose($stream);

            if (! is_array($exif) || ! isset($exif['Orientation'])) {
                return $image;
            }

            $orientation = (int) $exif['Orientation'];

            $rotated = match ($orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => $image,
            };

            if ($rotated instanceof GdImage && $rotated !== $image) {
                imagedestroy($image);

                return $rotated;
            }
        } catch (Throwable) {
            // Fail safely without orienting
        }

        return $image;
    }

    /**
     * Map standard 1-100 quality scale to PNG 0-9 compression scale.
     */
    protected function qualityToPngCompression(int $quality): int
    {
        // Quality 100 -> compression 0, Quality 0 -> compression 9
        $clamped = max(0, min(100, $quality));

        return (int) round((100 - $clamped) / 100 * 9);
    }

    /**
     * Detect if a GIF binary contains more than one frame.
     */
    protected function isAnimatedGif(string $binaryContent): bool
    {
        // An animated GIF contains multiple graphic control extension blocks (0x21 0xF9)
        return preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $binaryContent) > 1;
    }

    /**
     * Determine target output format with capability check and graceful fallback to source format.
     */
    protected function determineOutputFormat(string $sourceMimeType, ?string $requestedFormat): string
    {
        $sourceFormat = match ($sourceMimeType) {
            'image/jpeg' => 'jpeg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpeg',
        };

        if ($requestedFormat === null || trim($requestedFormat) === '') {
            return $sourceFormat;
        }

        $normalized = strtolower(trim($requestedFormat));
        if ($normalized === 'jpg') {
            $normalized = 'jpeg';
        }

        // Check if processor can encode this format
        if ($this->canEncode($normalized)) {
            return $normalized;
        }

        // Graceful fallback to source format if requested format (e.g. webp) cannot be encoded
        return $sourceFormat;
    }

    /**
     * Map format identifier to standard image MIME type.
     */
    protected function formatToMimeType(string $format): string
    {
        return match ($format) {
            'jpeg', 'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }
}
