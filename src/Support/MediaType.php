<?php

namespace FilamentMediaLibrary\Support;

use Illuminate\Support\Str;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
    case Other = 'other';

    public static function fromMimeType(?string $mimeType): self
    {
        $mimeType = Str::of($mimeType ?? '')->trim()->lower()->toString();

        return match (true) {
            str_starts_with($mimeType, 'image/') => self::Image,
            str_starts_with($mimeType, 'video/') => self::Video,
            str_starts_with($mimeType, 'audio/') => self::Audio,
            str_starts_with($mimeType, 'text/'), self::isDocumentApplication($mimeType) => self::Document,
            default => self::Other,
        };
    }

    private static function isDocumentApplication(string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/msword',
            'application/pdf',
            'application/rtf',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
            'application/vnd.oasis.opendocument.presentation',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], true);
    }
}
