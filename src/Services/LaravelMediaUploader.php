<?php

namespace FilamentMediaLibrary\Services;

use FilamentMediaLibrary\Contracts\MediaUploader;
use FilamentMediaLibrary\Events\MediaCreated;
use FilamentMediaLibrary\Exceptions\MediaUploadException;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Support\MediaPath;
use FilamentMediaLibrary\Support\MediaType;
use FilamentMediaLibrary\Support\MediaUploadOptions;
use FilamentMediaLibrary\Support\MimeTypeExtension;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LaravelMediaUploader implements MediaUploader
{
    private const FILENAME_ATTEMPTS = 5;

    public function __construct(private FilesystemManager $filesystem) {}

    public function upload(UploadedFile $file, ?MediaUploadOptions $options = null): Media
    {
        $options ??= new MediaUploadOptions;

        if (! $file->isValid()) {
            throw MediaUploadException::invalidFile();
        }

        $mimeType = $this->mimeType($file);
        $extension = MimeTypeExtension::resolve($mimeType)
            ?? throw MediaUploadException::unresolvedExtension($mimeType);
        $size = $this->size($file);
        $diskName = $this->diskName($options);
        $directory = $this->directory($options);
        $originalFilename = $this->originalFilename($file);
        $metadata = $this->metadata($options);
        $disk = $this->disk($diskName);
        [$filename, $path] = $this->availablePath($disk, $directory, $extension);

        try {
            $storedPath = $disk->putFileAs($directory ?? '', $file, $filename);
        } catch (Throwable $exception) {
            throw MediaUploadException::storageFailed($exception);
        }

        if ($storedPath === false || $storedPath !== $path) {
            if (is_string($storedPath) && $storedPath !== '') {
                try {
                    $disk->delete($storedPath);
                } catch (Throwable $exception) {
                    throw MediaUploadException::storageFailed($exception);
                }
            }

            throw MediaUploadException::storageFailed();
        }

        try {
            $media = DB::transaction(fn (): Media => Media::query()->create([
                'disk' => $diskName,
                'directory' => $directory,
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size' => $size,
                ...$this->dimensions($file, $mimeType),
                ...$metadata,
            ]));
        } catch (Throwable $exception) {
            $this->cleanupStoredFile($disk, $path, $exception);

            throw $exception;
        }

        if (DB::transactionLevel() > 0) {
            DB::afterRollBack(function () use ($disk, $path): void {
                $this->cleanupStoredFile(
                    $disk,
                    $path,
                    new RuntimeException('The enclosing database transaction was rolled back.'),
                );
            });
        }

        MediaCreated::dispatch($media);

        return $media;
    }

    private function mimeType(UploadedFile $file): string
    {
        try {
            $mimeType = $file->getMimeType();
        } catch (Throwable $exception) {
            throw MediaUploadException::invalidMimeType(previous: $exception);
        }

        $mimeType = is_string($mimeType) ? Str::lower(trim($mimeType)) : null;
        $allowedMimeTypes = config('filament-media-library.upload.allowed_mime_types');

        if (! is_array($allowedMimeTypes)) {
            throw MediaUploadException::invalidConfiguration('upload.allowed_mime_types');
        }

        $allowedMimeTypes = array_map(
            static fn (mixed $allowedMimeType): ?string => is_string($allowedMimeType)
                ? Str::lower(trim($allowedMimeType))
                : null,
            $allowedMimeTypes,
        );

        if ($mimeType === null || ! in_array($mimeType, $allowedMimeTypes, true)) {
            throw MediaUploadException::invalidMimeType($mimeType);
        }

        return $mimeType;
    }

    private function size(UploadedFile $file): int
    {
        $maximumKilobytes = config('filament-media-library.upload.max_size_kb');

        if (! is_int($maximumKilobytes) || $maximumKilobytes < 1) {
            throw MediaUploadException::invalidConfiguration('upload.max_size_kb');
        }

        $size = $file->getSize();

        if (! is_int($size) || $size < 0) {
            throw MediaUploadException::invalidFile();
        }

        if ($size > $maximumKilobytes * 1024) {
            throw MediaUploadException::fileTooLarge($maximumKilobytes);
        }

        return $size;
    }

    private function diskName(MediaUploadOptions $options): string
    {
        $diskName = $options->disk ?? config('filament-media-library.default_disk');
        $disks = config('filesystems.disks');

        if (! is_string($diskName) || trim($diskName) === '' || ! is_array($disks) || ! array_key_exists($diskName, $disks)) {
            throw MediaUploadException::invalidDisk();
        }

        return $diskName;
    }

    private function disk(string $diskName): FilesystemAdapter
    {
        try {
            return $this->filesystem->disk($diskName);
        } catch (Throwable $exception) {
            throw MediaUploadException::storageFailed($exception);
        }
    }

    private function directory(MediaUploadOptions $options): ?string
    {
        $directory = $options->directory;

        if ($directory === null) {
            $baseDirectory = config('filament-media-library.directory');

            if (! is_string($baseDirectory)) {
                throw MediaUploadException::invalidConfiguration('directory');
            }

            $directory = rtrim($baseDirectory, '/\\').'/'.now()->format('Y/m');
        }

        try {
            return MediaPath::normalizeDirectory($directory);
        } catch (Throwable $exception) {
            throw MediaUploadException::invalidDirectory($exception);
        }
    }

    private function originalFilename(UploadedFile $file): string
    {
        $filename = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename);
        $filename = is_string($filename) ? trim($filename) : '';

        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw MediaUploadException::invalidOriginalFilename();
        }

        return Str::limit($filename, 255, '');
    }

    /**
     * @return array{title: ?string, alt_text: ?string, caption: ?string, description: ?string, uploaded_by: ?string, metadata: array<string, mixed>|null}
     */
    private function metadata(MediaUploadOptions $options): array
    {
        return [
            'title' => $this->text($options->title, 'title', 255),
            'alt_text' => $this->text($options->altText, 'alt_text', 255),
            'caption' => $this->text($options->caption, 'caption', 65_535, true),
            'description' => $this->text($options->description, 'description', 4_294_967_295, true),
            'uploaded_by' => $this->text($options->uploadedBy, 'uploaded_by', 255),
            'metadata' => $options->metadata,
        ];
    }

    private function text(?string $value, string $field, int $maximum, bool $bytes = false): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        $length = $bytes ? strlen($value) : Str::length($value);

        if ($length > $maximum) {
            throw MediaUploadException::invalidMetadata($field, $maximum);
        }

        return $value === '' ? null : $value;
    }

    /**
     * @return array{width: int|null, height: int|null}
     */
    private function dimensions(UploadedFile $file, string $mimeType): array
    {
        if (MediaType::fromMimeType($mimeType) !== MediaType::Image || ! function_exists('getimagesize')) {
            return ['width' => null, 'height' => null];
        }

        $dimensions = @getimagesize($file->getPathname());

        if (! is_array($dimensions) || ! isset($dimensions[0], $dimensions[1])) {
            return ['width' => null, 'height' => null];
        }

        return ['width' => (int) $dimensions[0], 'height' => (int) $dimensions[1]];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function availablePath(FilesystemAdapter $disk, ?string $directory, string $extension): array
    {
        for ($attempt = 0; $attempt < self::FILENAME_ATTEMPTS; $attempt++) {
            $filename = Str::uuid().'.'.$extension;
            $path = MediaPath::for($directory, $filename);

            try {
                if (! $disk->exists($path)) {
                    return [$filename, $path];
                }
            } catch (Throwable $exception) {
                throw MediaUploadException::storageFailed($exception);
            }
        }

        throw MediaUploadException::filenameUnavailable();
    }

    private function cleanupStoredFile(FilesystemAdapter $disk, string $path, Throwable $previous): void
    {
        try {
            $deleted = $disk->delete($path);
        } catch (Throwable) {
            throw MediaUploadException::cleanupFailed($previous);
        }

        if (! $deleted) {
            throw MediaUploadException::cleanupFailed($previous);
        }
    }
}
