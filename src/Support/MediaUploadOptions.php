<?php

namespace FilamentMediaLibrary\Support;

final readonly class MediaUploadOptions
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public ?string $disk = null,
        public ?string $directory = null,
        public ?string $title = null,
        public ?string $altText = null,
        public ?string $caption = null,
        public ?string $description = null,
        public ?string $uploadedBy = null,
        public ?array $metadata = null,
    ) {}
}
