<?php

namespace FilamentMediaLibrary\Models;

use FilamentMediaLibrary\Contracts\MediaConversionManager;
use FilamentMediaLibrary\Contracts\MediaStorage;
use FilamentMediaLibrary\Support\MediaPath;
use FilamentMediaLibrary\Support\MediaType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * @property-read string $path
 * @property-read MediaType $type
 */
class Media extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'disk',
        'directory',
        'filename',
        'original_filename',
        'mime_type',
        'extension',
        'size',
        'width',
        'height',
        'title',
        'alt_text',
        'caption',
        'description',
        'metadata',
        'uploaded_by',
    ];

    public function getTable(): string
    {
        $tableName = config('filament-media-library.table_names.media', 'media');

        if (! is_string($tableName) || trim($tableName) === '') {
            throw new InvalidArgumentException('The configured media table name must be a non-empty string.');
        }

        return $tableName;
    }

    protected static function booted(): void
    {
        static::creating(function (Media $media): void {
            if (blank($media->uuid)) {
                $media->uuid = (string) Str::uuid();
            }

            if (blank($media->disk)) {
                $defaultDisk = config('filament-media-library.default_disk', 'public');
                $media->disk = is_string($defaultDisk) && $defaultDisk !== '' ? $defaultDisk : 'public';
            }

            if (! array_key_exists('directory', $media->getAttributes())) {
                $directory = config('filament-media-library.directory', 'media');
                $media->directory = is_string($directory) ? $directory : 'media';
            }
        });

        static::updating(function (Media $media): void {
            if ($media->isDirty('uuid')) {
                throw new LogicException('A media UUID cannot be changed after creation.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'uploaded_by' => 'string',
        ];
    }

    protected function directory(): Attribute
    {
        return Attribute::set(
            fn (mixed $value): ?string => MediaPath::normalizeDirectory(
                is_string($value) ? $value : null,
            ),
        );
    }

    protected function filename(): Attribute
    {
        return Attribute::set(
            fn (mixed $value): string => MediaPath::normalizeFilename((string) $value),
        );
    }

    protected function extension(): Attribute
    {
        return Attribute::set(function (mixed $value): ?string {
            if (! is_string($value)) {
                return null;
            }

            $extension = Str::lower(ltrim(trim($value), '.'));

            return $extension !== '' ? $extension : null;
        });
    }

    protected function path(): Attribute
    {
        return Attribute::get(
            fn (): string => MediaPath::for($this->directory, $this->filename),
        );
    }

    protected function type(): Attribute
    {
        return Attribute::get(
            fn (): MediaType => MediaType::fromMimeType($this->mime_type),
        );
    }

    /**
     * @return HasMany<MediaAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MediaAttachment::class, 'media_id');
    }

    /**
     * Check if a specific conversion preset exists in metadata.
     */
    public function hasConversion(string $preset): bool
    {
        $conversions = $this->metadata['conversions'] ?? null;

        return is_array($conversions) && isset($conversions[$preset]);
    }

    /**
     * Get metadata for a specific conversion preset.
     *
     * @return array<string, mixed>|null
     */
    public function getConversion(string $preset): ?array
    {
        $conversions = $this->metadata['conversions'] ?? null;

        return is_array($conversions) && isset($conversions[$preset]) && is_array($conversions[$preset])
            ? $conversions[$preset]
            : null;
    }

    /**
     * Get the URL for a conversion preset, falling back to original if missing or not an image.
     */
    public function conversionUrl(string $preset): ?string
    {
        $manager = app(MediaConversionManager::class);

        $url = $manager->getConversionUrl($this, $preset);

        if ($url !== null) {
            return $url;
        }

        return app(MediaStorage::class)->url($this);
    }
}
