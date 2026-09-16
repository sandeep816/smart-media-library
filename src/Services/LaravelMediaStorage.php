<?php

namespace FilamentMediaLibrary\Services;

use FilamentMediaLibrary\Contracts\MediaStorage;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Support\MediaPath;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Throwable;

class LaravelMediaStorage implements MediaStorage
{
    public function __construct(private FilesystemManager $filesystem) {}

    public function path(Media $media): string
    {
        return MediaPath::for($media->directory, $media->filename);
    }

    public function exists(Media $media): bool
    {
        return $this->filesystem->disk($media->disk)->exists($this->path($media));
    }

    public function url(Media $media): ?string
    {
        try {
            $disk = $this->filesystem->disk($media->disk);
            $configuration = method_exists($disk, 'getConfig') ? $disk->getConfig() : [];
            $diskConfig = config("filesystems.disks.{$media->disk}", []);

            $hasUrl = isset($configuration['url']) || isset($diskConfig['url']);
            $isPublic = ($configuration['visibility'] ?? null) === 'public'
                || ($diskConfig['visibility'] ?? null) === 'public'
                || $media->disk === 'public';

            if ($disk instanceof LocalFilesystemAdapter && ! $hasUrl && ! $isPublic) {
                return null;
            }

            $url = $disk->url($this->path($media));
        } catch (Throwable) {
            return null;
        }

        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true)
            ? $url
            : null;
    }

    public function delete(Media $media): bool
    {
        return $this->filesystem->disk($media->disk)->delete($this->path($media));
    }
}
