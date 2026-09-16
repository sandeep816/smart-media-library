<?php

namespace FilamentMediaLibrary\Services;

use FilamentMediaLibrary\Contracts\ImageProcessor;
use FilamentMediaLibrary\Contracts\MediaConversionManager;
use FilamentMediaLibrary\Contracts\MediaStorage;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Support\MediaPath;
use FilamentMediaLibrary\Support\MediaType;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Support\Carbon;
use Throwable;

class LaravelMediaConversionManager implements MediaConversionManager
{
    public function __construct(
        protected FilesystemManager $filesystem,
        protected ImageProcessor $imageProcessor,
        protected MediaStorage $storage,
    ) {}

    public function generate(Media $media, ?string $preset = null, bool $force = false): array
    {
        // Only images can have conversions generated
        if ($media->type !== MediaType::Image) {
            return [];
        }

        if (! config('filament-media-library.conversions.enabled', true)) {
            return [];
        }

        if (! $this->imageProcessor->canProcess($media->mime_type)) {
            return [];
        }

        // Verify original file exists
        if (! $this->storage->exists($media)) {
            return [];
        }

        $presetsConfig = config('filament-media-library.conversions.presets', []);
        if (! is_array($presetsConfig) || empty($presetsConfig)) {
            return [];
        }

        if ($preset !== null) {
            if (! isset($presetsConfig[$preset])) {
                return [];
            }
            $targetPresets = [$preset => $presetsConfig[$preset]];
        } else {
            $targetPresets = $presetsConfig;
        }

        $disk = $this->filesystem->disk($media->disk);
        $originalPath = $this->storage->path($media);

        try {
            $originalContent = $disk->get($originalPath);
        } catch (Throwable) {
            return [];
        }

        if (! is_string($originalContent) || $originalContent === '') {
            return [];
        }

        $currentMetadata = is_array($media->metadata) ? $media->metadata : [];
        $existingConversions = is_array($currentMetadata['conversions'] ?? null)
            ? $currentMetadata['conversions']
            : [];

        $generated = [];

        foreach ($targetPresets as $presetName => $options) {
            if (! is_array($options)) {
                continue;
            }

            $maxWidth = (int) ($options['width'] ?? 800);
            $maxHeight = (int) ($options['height'] ?? 600);
            $quality = (int) ($options['quality'] ?? 82);
            $requestedFormat = isset($options['format']) && is_string($options['format'])
                ? $options['format']
                : null;

            $existingPath = isset($existingConversions[$presetName]['path']) && is_string($existingConversions[$presetName]['path'])
                ? $existingConversions[$presetName]['path']
                : null;

            // Skip if already exists and force is false
            if (! $force && $existingPath !== null && $disk->exists($existingPath)) {
                $generated[$presetName] = $existingConversions[$presetName];

                continue;
            }

            $result = $this->imageProcessor->resize(
                $originalContent,
                $maxWidth,
                $maxHeight,
                $media->mime_type,
                $quality,
                $requestedFormat,
            );

            if ($result === null) {
                continue;
            }

            $extension = $this->mimeTypeToExtension($result->mimeType);
            $targetPath = $this->determineConversionPath($media, $presetName, $extension);

            try {
                $written = $disk->put($targetPath, $result->binaryContent);
            } catch (Throwable) {
                continue;
            }

            if ($written) {
                $conversionData = [
                    'path' => $targetPath,
                    'width' => $result->width,
                    'height' => $result->height,
                    'size' => $result->size,
                    'mime_type' => $result->mimeType,
                    'generated_at' => Carbon::now()->toIso8601String(),
                ];

                $existingConversions[$presetName] = $conversionData;
                $generated[$presetName] = $conversionData;
            }
        }

        if (! empty($generated)) {
            $currentMetadata['conversions'] = $existingConversions;
            $media->metadata = $currentMetadata;
            $media->saveQuietly();
        }

        return $generated;
    }

    public function regenerate(Media $media): array
    {
        return $this->generate($media, null, force: true);
    }

    public function deleteConversions(Media $media): bool
    {
        $disk = $this->filesystem->disk($media->disk);
        $currentMetadata = is_array($media->metadata) ? $media->metadata : [];
        $conversions = is_array($currentMetadata['conversions'] ?? null)
            ? $currentMetadata['conversions']
            : [];

        foreach ($conversions as $presetName => $data) {
            $path = $this->determineConversionPath($media, $presetName);
            try {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            } catch (Throwable) {
                // Continue cleaning up
            }
        }

        unset($currentMetadata['conversions']);
        $media->metadata = $currentMetadata;

        return $media->saveQuietly();
    }

    public function getConversionPath(Media $media, string $preset): ?string
    {
        $conversions = $media->metadata['conversions'] ?? null;
        if (is_array($conversions) && isset($conversions[$preset]['path'])) {
            return (string) $conversions[$preset]['path'];
        }

        return $this->determineConversionPath($media, $preset);
    }

    public function getConversionUrl(Media $media, string $preset): ?string
    {
        $path = $this->getConversionPath($media, $preset);
        if (! $path) {
            return null;
        }

        try {
            $disk = $this->filesystem->disk($media->disk);

            if (! $disk->exists($path)) {
                return null;
            }

            $configuration = method_exists($disk, 'getConfig') ? $disk->getConfig() : [];
            $diskConfig = config("filesystems.disks.{$media->disk}", []);

            $hasUrl = isset($configuration['url']) || isset($diskConfig['url']);
            $isPublic = ($configuration['visibility'] ?? null) === 'public'
                || ($diskConfig['visibility'] ?? null) === 'public'
                || $media->disk === 'public';

            if ($disk instanceof LocalFilesystemAdapter && ! $hasUrl && ! $isPublic) {
                return null;
            }

            $url = $disk->url($path);
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

    public function conversionExists(Media $media, string $preset): bool
    {
        $path = $this->getConversionPath($media, $preset);
        if (! $path) {
            return false;
        }

        try {
            return $this->filesystem->disk($media->disk)->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Compute deterministic storage path for a preset conversion.
     * Pattern: {directory}/conversions/{uuid}-{preset}.{extension}
     */
    protected function determineConversionPath(Media $media, string $preset, ?string $extension = null): string
    {
        $conversionDir = config('filament-media-library.conversions.directory', 'conversions');
        $baseDir = $media->directory ? trim($media->directory, '/') : '';

        $folder = $baseDir !== ''
            ? $baseDir.'/'.$conversionDir
            : $conversionDir;

        $ext = $extension ?: ($media->extension ?: 'jpg');
        $filename = "{$media->uuid}-{$preset}.{$ext}";

        return MediaPath::for($folder, $filename);
    }

    /**
     * Map MIME type to standard file extension.
     */
    protected function mimeTypeToExtension(string $mimeType): string
    {
        return match (strtolower(trim($mimeType))) {
            'image/webp' => 'webp',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/jpeg' => 'jpg',
            default => 'jpg',
        };
    }
}
