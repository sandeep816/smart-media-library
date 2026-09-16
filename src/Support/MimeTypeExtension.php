<?php

namespace FilamentMediaLibrary\Support;

use Illuminate\Support\Str;

final class MimeTypeExtension
{
    public static function resolve(string $mimeType): ?string
    {
        return match (Str::lower(trim($mimeType))) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/ogg', 'application/ogg' => 'ogg',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            default => null,
        };
    }
}
