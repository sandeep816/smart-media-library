<?php

namespace FilamentMediaLibrary\Exceptions;

use RuntimeException;
use Throwable;

class MediaUploadException extends RuntimeException
{
    public static function invalidFile(): self
    {
        return new self('The uploaded file is invalid or incomplete.');
    }

    public static function invalidConfiguration(string $setting): self
    {
        return new self("The media upload configuration for [{$setting}] is invalid.");
    }

    public static function invalidDisk(): self
    {
        return new self('The selected media storage disk is not configured.');
    }

    public static function invalidMimeType(?string $mimeType = null, ?Throwable $previous = null): self
    {
        $detected = filled($mimeType) ? " Detected type: [{$mimeType}]." : '';

        return new self('The uploaded file type is not allowed.'.$detected, previous: $previous);
    }

    public static function unresolvedExtension(string $mimeType): self
    {
        return new self("No safe file extension is configured for MIME type [{$mimeType}].");
    }

    public static function fileTooLarge(int $maximumKilobytes): self
    {
        return new self("The uploaded file exceeds the maximum size of {$maximumKilobytes} KB.");
    }

    public static function invalidDirectory(Throwable $previous): self
    {
        return new self('The selected media directory is not a safe relative path.', previous: $previous);
    }

    public static function invalidMetadata(string $field, int $maximum): self
    {
        return new self("The media field [{$field}] exceeds its maximum length of {$maximum}.");
    }

    public static function invalidOriginalFilename(): self
    {
        return new self('The uploaded file does not have a valid original filename.');
    }

    public static function storageFailed(?Throwable $previous = null): self
    {
        return new self('The uploaded file could not be stored.', previous: $previous);
    }

    public static function filenameUnavailable(): self
    {
        return new self('A unique storage filename could not be allocated.');
    }

    public static function cleanupFailed(Throwable $previous): self
    {
        return new self('The media record could not be saved and its stored file could not be cleaned up.', previous: $previous);
    }
}
