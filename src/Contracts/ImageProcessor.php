<?php

namespace FilamentMediaLibrary\Contracts;

use FilamentMediaLibrary\Support\ImageConversionResult;

interface ImageProcessor
{
    /**
     * Check if the given MIME type can be processed by this engine.
     */
    public function canProcess(string $mimeType): bool;

    /**
     * Check if the given format (e.g. 'webp', 'jpeg', 'png') can be encoded by this engine.
     */
    public function canEncode(string $format): bool;

    /**
     * Resize an image to fit within maxWidth and maxHeight bounds, preserving aspect ratio.
     * Never upscales smaller images. Preserves transparency where supported.
     *
     * @param  string  $binaryContent  Raw binary image data.
     * @param  int  $maxWidth  Maximum bounding width.
     * @param  int  $maxHeight  Maximum bounding height.
     * @param  string  $mimeType  MIME type of the input image.
     * @param  int  $quality  Compression quality (1-100).
     * @param  string|null  $format  Target output format (e.g. 'webp', 'jpeg', 'png') or null for source format.
     */
    public function resize(
        string $binaryContent,
        int $maxWidth,
        int $maxHeight,
        string $mimeType,
        int $quality = 82,
        ?string $format = null,
    ): ?ImageConversionResult;
}
