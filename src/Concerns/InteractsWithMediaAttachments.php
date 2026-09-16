<?php

namespace FilamentMediaLibrary\Concerns;

use FilamentMediaLibrary\Contracts\MediaAttacher;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin Model
 */
trait InteractsWithMediaAttachments
{
    /**
     * @return MorphMany<MediaAttachment, $this>
     */
    public function mediaAttachments(): MorphMany
    {
        return $this->morphMany(MediaAttachment::class, 'mediable')
            ->orderBy('position', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * @return Collection<int, Media>
     */
    public function mediaFor(string $collection = 'default'): Collection
    {
        return app(MediaAttacher::class)->getAttachedMedia($this, $collection);
    }

    /**
     * @return array<int, string>
     */
    public function mediaUuidsFor(string $collection = 'default'): array
    {
        return app(MediaAttacher::class)->getAttachedMediaUuids($this, $collection);
    }

    public function singleMediaFor(string $collection = 'default'): ?Media
    {
        return $this->mediaFor($collection)->first();
    }

    /**
     * @param  string|array<int, string>  $mediaUuids
     * @param  array<string, mixed>  $options
     */
    public function attachMedia(string $collection, string|array $mediaUuids, array $options = []): void
    {
        app(MediaAttacher::class)->attach($this, $collection, $mediaUuids, $options);
    }

    /**
     * @param  array<int, string>  $mediaUuids
     * @param  array<string, mixed>  $options
     */
    public function syncMedia(string $collection, array $mediaUuids, array $options = []): void
    {
        app(MediaAttacher::class)->sync($this, $collection, $mediaUuids, $options);
    }

    /**
     * @param  array<int, string>|null  $mediaUuids
     */
    public function detachMedia(string $collection, ?array $mediaUuids = null): void
    {
        app(MediaAttacher::class)->detach($this, $collection, $mediaUuids);
    }
}
