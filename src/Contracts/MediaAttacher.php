<?php

namespace FilamentMediaLibrary\Contracts;

use FilamentMediaLibrary\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface MediaAttacher
{
    /**
     * @param  array<int, string>  $mediaUuids
     * @param  array<string, mixed>  $options
     */
    public function sync(Model $record, string $collection, array $mediaUuids, array $options = []): void;

    /**
     * @param  string|array<int, string>  $mediaUuids
     * @param  array<string, mixed>  $options
     */
    public function attach(Model $record, string $collection, string|array $mediaUuids, array $options = []): void;

    /**
     * @param  array<int, string>|null  $mediaUuids
     */
    public function detach(Model $record, string $collection, ?array $mediaUuids = null): void;

    /**
     * @return Collection<int, Media>
     */
    public function getAttachedMedia(Model $record, string $collection): Collection;

    /**
     * @return array<int, string>
     */
    public function getAttachedMediaUuids(Model $record, string $collection): array;
}
