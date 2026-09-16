<?php

namespace FilamentMediaLibrary\Support;

class ImageConversionResult
{
    public function __construct(
        public readonly string $binaryContent,
        public readonly int $width,
        public readonly int $height,
        public readonly string $mimeType,
        public readonly int $size,
    ) {}
}
