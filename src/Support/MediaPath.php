<?php

namespace FilamentMediaLibrary\Support;

use InvalidArgumentException;

final class MediaPath
{
    public static function for(?string $directory, string $filename): string
    {
        $directory = self::normalizeDirectory($directory);
        $filename = self::normalizeFilename($filename);

        return $directory === null ? $filename : $directory.'/'.$filename;
    }

    public static function normalizeDirectory(?string $directory): ?string
    {
        if ($directory === null || trim($directory) === '') {
            return null;
        }

        self::ensureNoNullBytes($directory);

        $directory = str_replace('\\', '/', trim($directory));

        if (
            str_starts_with($directory, '/')
            || preg_match('/^[a-zA-Z]:\//', $directory) === 1
            || preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//', $directory) === 1
        ) {
            throw new InvalidArgumentException('Media directories must be relative paths.');
        }

        $segments = [];

        foreach (explode('/', $directory) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new InvalidArgumentException('Media directories cannot traverse outside their configured root.');
            }

            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    public static function normalizeFilename(string $filename): string
    {
        self::ensureNoNullBytes($filename);

        $filename = trim($filename);

        if ($filename === '' || $filename === '.' || $filename === '..' || str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw new InvalidArgumentException('Media filenames must be non-empty names without path segments.');
        }

        return $filename;
    }

    private static function ensureNoNullBytes(string $path): void
    {
        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException('Media paths cannot contain null bytes.');
        }
    }
}
