<?php

namespace FilamentMediaLibrary\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

class MediaAttachment extends Model
{
    protected $fillable = [
        'media_id',
        'mediable_type',
        'mediable_id',
        'collection',
        'position',
        'metadata',
    ];

    public function getTable(): string
    {
        $tableName = config('filament-media-library.table_names.attachments', 'media_attachments');

        if (! is_string($tableName) || trim($tableName) === '') {
            throw new InvalidArgumentException('The configured attachments table name must be a non-empty string.');
        }

        return $tableName;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_id' => 'integer',
            'position' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForCollection(Builder $query, string $collection): Builder
    {
        return $query->where('collection', $collection);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOrdered(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('position', $direction)->orderBy('id', $direction);
    }
}
