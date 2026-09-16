<?php

namespace FilamentMediaLibrary\Services;

use FilamentMediaLibrary\Contracts\MediaAttacher;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EloquentMediaAttacher implements MediaAttacher
{
    /**
     * @param  array<int, string>  $mediaUuids
     * @param  array<string, mixed>  $options
     */
    public function sync(Model $record, string $collection, array $mediaUuids, array $options = []): void
    {
        $this->validateCollection($collection);

        $cleanUuids = array_values(array_unique(array_filter($mediaUuids, fn ($id): bool => is_string($id) && filled($id))));

        DB::transaction(function () use ($record, $collection, $cleanUuids, $options): void {
            $mediableType = $record->getMorphClass();
            $mediableId = (string) $record->getKey();

            if (empty($cleanUuids)) {
                MediaAttachment::query()
                    ->where('mediable_type', $mediableType)
                    ->where('mediable_id', $mediableId)
                    ->where('collection', $collection)
                    ->delete();

                return;
            }

            // Find valid central media by uuid
            $mediaItems = Media::query()
                ->whereIn('uuid', $cleanUuids)
                ->get()
                ->keyBy('uuid');

            // Map each cleanUuid to its media record, preserving requested order
            $orderedMedia = [];
            foreach ($cleanUuids as $uuid) {
                if (isset($mediaItems[$uuid])) {
                    $orderedMedia[] = $mediaItems[$uuid];
                }
            }

            $desiredMediaIds = array_map(fn (Media $m): int => $m->getKey(), $orderedMedia);

            // Delete existing attachments not in desired list
            MediaAttachment::query()
                ->where('mediable_type', $mediableType)
                ->where('mediable_id', $mediableId)
                ->where('collection', $collection)
                ->whereNotIn('media_id', $desiredMediaIds)
                ->delete();

            // Load remaining existing attachments for this collection
            $existingAttachments = MediaAttachment::query()
                ->where('mediable_type', $mediableType)
                ->where('mediable_id', $mediableId)
                ->where('collection', $collection)
                ->get()
                ->keyBy('media_id');

            // Update or create each attachment with its deterministic position
            foreach ($orderedMedia as $position => $media) {
                $mediaId = $media->getKey();
                $attachmentMetadata = $options['metadata'][$media->uuid] ?? $options['metadata'] ?? null;

                if (isset($existingAttachments[$mediaId])) {
                    $attachment = $existingAttachments[$mediaId];
                    $updates = ['position' => $position];

                    if ($attachmentMetadata !== null) {
                        $updates['metadata'] = is_array($attachmentMetadata) ? $attachmentMetadata : null;
                    }

                    $attachment->update($updates);
                } else {
                    MediaAttachment::query()->create([
                        'media_id' => $mediaId,
                        'mediable_type' => $mediableType,
                        'mediable_id' => $mediableId,
                        'collection' => $collection,
                        'position' => $position,
                        'metadata' => is_array($attachmentMetadata) ? $attachmentMetadata : null,
                    ]);
                }
            }
        });
    }

    /**
     * @param  string|array<int, string>  $mediaUuids
     * @param  array<string, mixed>  $options
     */
    public function attach(Model $record, string $collection, string|array $mediaUuids, array $options = []): void
    {
        $this->validateCollection($collection);

        $uuidsToAttach = is_array($mediaUuids) ? $mediaUuids : [$mediaUuids];
        $currentUuids = $this->getAttachedMediaUuids($record, $collection);

        $mergedUuids = array_values(array_unique(array_merge($currentUuids, $uuidsToAttach)));

        $this->sync($record, $collection, $mergedUuids, $options);
    }

    /**
     * @param  array<int, string>|null  $mediaUuids
     */
    public function detach(Model $record, string $collection, ?array $mediaUuids = null): void
    {
        $this->validateCollection($collection);

        if ($mediaUuids === null) {
            MediaAttachment::query()
                ->where('mediable_type', $record->getMorphClass())
                ->where('mediable_id', (string) $record->getKey())
                ->where('collection', $collection)
                ->delete();

            return;
        }

        $currentUuids = $this->getAttachedMediaUuids($record, $collection);
        $remainingUuids = array_values(array_diff($currentUuids, $mediaUuids));

        $this->sync($record, $collection, $remainingUuids);
    }

    /**
     * @return Collection<int, Media>
     */
    public function getAttachedMedia(Model $record, string $collection): Collection
    {
        $this->validateCollection($collection);

        $attachments = MediaAttachment::query()
            ->where('mediable_type', $record->getMorphClass())
            ->where('mediable_id', (string) $record->getKey())
            ->where('collection', $collection)
            ->orderBy('position', 'asc')
            ->orderBy('id', 'asc')
            ->with('media')
            ->get();

        $mediaList = new Collection;

        foreach ($attachments as $attachment) {
            if ($attachment->media instanceof Media) {
                $mediaList->push($attachment->media);
            }
        }

        return $mediaList;
    }

    /**
     * @return array<int, string>
     */
    public function getAttachedMediaUuids(Model $record, string $collection): array
    {
        return $this->getAttachedMedia($record, $collection)
            ->pluck('uuid')
            ->all();
    }

    private function validateCollection(string $collection): void
    {
        if (trim($collection) === '' || ! preg_match('/^[a-zA-Z0-9_\-]+$/', $collection)) {
            throw new InvalidArgumentException(
                "Invalid media collection name [{$collection}]. Collection names must contain only alphanumeric characters, underscores, and hyphens.",
            );
        }
    }
}
