<?php

namespace FilamentMediaLibrary\Filament\Forms\Components;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Concerns\CanLimitItemsLength;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use FilamentMediaLibrary\Contracts\MediaAttacher;
use FilamentMediaLibrary\Contracts\MediaConversionManager;
use FilamentMediaLibrary\Contracts\MediaStorage;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Support\MediaType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use Throwable;

class MediaPicker extends Field
{
    use CanLimitItemsLength;

    protected string $view = 'filament-media-library::filament.forms.components.media-picker';

    protected bool|Closure $isMultiple = false;

    protected bool|Closure $isReorderable = false;

    protected string|Closure $collection = 'default';

    protected bool $isAttachmentMode = false;

    /**
     * @var array<int, MediaType|string> | Closure
     */
    protected array|Closure $acceptedMediaTypes = [];

    protected bool|Closure $allowUpload = true;

    protected Model|Closure|null $record = null;

    public function record(Model|Closure|null $record): static
    {
        $this->record = $record;

        return $this;
    }

    public function getRecord(bool $withContainerRecord = true): Model|array|null
    {
        if ($this->record !== null) {
            return $this->evaluate($this->record);
        }

        try {
            return parent::getRecord($withContainerRecord);
        } catch (Throwable) {
            return null;
        }
    }

    public function getModel(): ?string
    {
        $record = $this->getRecord();

        if ($record instanceof Model) {
            return $record::class;
        }

        try {
            return parent::getModel();
        } catch (Throwable) {
            return null;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            fn (MediaPicker $component): Action => $component->getPickerAction(),
            fn (MediaPicker $component): Action => $component->getRemoveAction(),
            fn (MediaPicker $component): Action => $component->getMoveUpAction(),
            fn (MediaPicker $component): Action => $component->getMoveDownAction(),
        ]);

        $this->afterStateHydrated(static function (MediaPicker $component, mixed $state): void {
            if ($component->isMultiple()) {
                if (is_array($state)) {
                    $component->state(array_values(array_filter($state, fn ($item): bool => is_string($item) && filled($item))));
                } elseif (is_string($state) && filled($state)) {
                    $component->state([$state]);
                } else {
                    $component->state([]);
                }
            } else {
                if (is_array($state)) {
                    $component->state(array_values(array_filter($state, fn ($item): bool => is_string($item) && filled($item)))[0] ?? null);
                } elseif (is_string($state) && filled($state)) {
                    $component->state($state);
                } else {
                    $component->state(null);
                }
            }
        });

        $this->dehydrateStateUsing(static function (MediaPicker $component, mixed $state): mixed {
            if ($component->isMultiple()) {
                if (! is_array($state)) {
                    return filled($state) ? [(string) $state] : [];
                }

                return array_values(array_filter($state, fn ($item): bool => is_string($item) && filled($item)));
            }

            if (is_array($state)) {
                return array_values(array_filter($state, fn ($item): bool => is_string($item) && filled($item)))[0] ?? null;
            }

            return filled($state) ? (string) $state : null;
        });

        // Validation rule for type restrictions and active media
        $this->rule(static function (MediaPicker $component): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($component): void {
                $acceptedTypes = $component->getAcceptedMediaTypes();

                $uuids = match (true) {
                    is_array($value) => array_values(array_filter($value, fn ($item): bool => is_string($item) && filled($item))),
                    is_string($value) && filled($value) => [$value],
                    default => [],
                };

                if (empty($uuids)) {
                    return;
                }

                $mediaItems = Media::query()->whereIn('uuid', $uuids)->get()->keyBy('uuid');

                foreach ($uuids as $uuid) {
                    $media = $mediaItems->get($uuid);

                    if (! $media) {
                        $fail("The selected media item [{$uuid}] does not exist or has been removed.");

                        return;
                    }

                    if (! empty($acceptedTypes) && ! in_array($media->type->value, $acceptedTypes, true)) {
                        $typesList = implode(', ', $acceptedTypes);
                        $fail("The selected media item [{$media->original_filename}] is not an accepted media type. Accepted: {$typesList}.");

                        return;
                    }
                }
            };
        });
    }

    public function single(): static
    {
        $this->multiple(false);

        return $this;
    }

    public function multiple(bool|Closure $multiple = true): static
    {
        $this->isMultiple = $multiple;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    public function reorderable(bool|Closure $reorderable = true): static
    {
        $this->isReorderable = $reorderable;

        return $this;
    }

    public function isReorderable(): bool
    {
        $reorderable = (bool) $this->evaluate($this->isReorderable);

        if ($reorderable && ! $this->isMultiple()) {
            throw new LogicException('Reordering can only be enabled when multiple selection is enabled on the MediaPicker.');
        }

        return $reorderable;
    }

    public function collection(string|Closure $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function getCollection(): string
    {
        $name = (string) $this->evaluate($this->collection);

        if (trim($name) === '' || ! preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
            throw new InvalidArgumentException(
                "Invalid media collection name [{$name}]. Collection names must contain only alphanumeric characters, underscores, and hyphens.",
            );
        }

        return $name;
    }

    public function relationship(?string $collection = null): static
    {
        if (filled($collection)) {
            $this->collection($collection);
        }

        $this->isAttachmentMode = true;
        $this->dehydrated(false);

        $this->loadStateFromRelationshipsUsing(static function (MediaPicker $component): void {
            $record = $component->getRecord();

            if (! $record instanceof Model) {
                return;
            }

            $collection = $component->getCollection();
            $uuids = app(MediaAttacher::class)->getAttachedMediaUuids($record, $collection);

            if ($component->isMultiple()) {
                $component->state($uuids);
            } else {
                $component->state($uuids[0] ?? null);
            }
        });

        $this->saveRelationshipsUsing(static function (MediaPicker $component): void {
            $record = $component->getRecord();

            if (! $record instanceof Model) {
                return;
            }

            $collection = $component->getCollection();
            $state = $component->getState();

            $uuids = match (true) {
                is_array($state) => array_values(array_filter($state, fn ($id): bool => is_string($id) && filled($id))),
                is_string($state) && filled($state) => [$state],
                default => [],
            };

            app(MediaAttacher::class)->sync($record, $collection, $uuids);
        });

        return $this;
    }

    public function isAttachmentMode(): bool
    {
        return $this->isAttachmentMode;
    }

    public function image(): static
    {
        return $this->acceptedMediaTypes([MediaType::Image]);
    }

    public function video(): static
    {
        return $this->acceptedMediaTypes([MediaType::Video]);
    }

    public function audio(): static
    {
        return $this->acceptedMediaTypes([MediaType::Audio]);
    }

    public function document(): static
    {
        return $this->acceptedMediaTypes([MediaType::Document]);
    }

    /**
     * @param  array<int, MediaType|string> | Closure  $types
     */
    public function acceptedMediaTypes(array|Closure $types): static
    {
        $this->acceptedMediaTypes = $types;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getAcceptedMediaTypes(): array
    {
        $types = $this->evaluate($this->acceptedMediaTypes);

        if (! is_array($types)) {
            return [];
        }

        return collect($types)
            ->map(fn ($type): string => $type instanceof MediaType ? $type->value : (string) $type)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function allowUpload(bool|Closure $allow = true): static
    {
        $this->allowUpload = $allow;

        return $this;
    }

    public function allowsUpload(): bool
    {
        return (bool) $this->evaluate($this->allowUpload);
    }

    /**
     * @return Collection<int, Media>
     */
    public function getSelectedMedia(): Collection
    {
        $state = $this->getState();

        $uuids = match (true) {
            is_array($state) => array_values(array_filter($state, fn ($id): bool => is_string($id) && filled($id))),
            is_string($state) && filled($state) => [$state],
            default => [],
        };

        if (empty($uuids)) {
            return new Collection;
        }

        // Include trashed so we can detect and report unavailable media safely without crashing
        $mediaRecords = Media::withTrashed()
            ->whereIn('uuid', $uuids)
            ->get()
            ->keyBy('uuid');

        $orderedCollection = new Collection;

        foreach ($uuids as $uuid) {
            if ($mediaRecords->has($uuid)) {
                $orderedCollection->push($mediaRecords->get($uuid));
            }
        }

        return $orderedCollection;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSelectedMediaPresentations(): array
    {
        $selected = $this->getSelectedMedia();
        $storage = app(MediaStorage::class);
        $presentations = [];

        foreach ($selected as $media) {
            $isTrashed = $media->trashed();

            try {
                $exists = ! $isTrashed && $storage->exists($media);
            } catch (Throwable) {
                $exists = false;
            }

            $url = $exists ? $storage->url($media) : null;

            $thumbnailUrl = null;
            if ($exists && $media->type === MediaType::Image && $url !== null) {
                $conversionManager = app(MediaConversionManager::class);
                $thumbnailUrl = $conversionManager->getConversionUrl($media, 'thumbnail') ?? $url;
            }

            $presentations[] = [
                'uuid' => $media->uuid,
                'exists' => $exists,
                'is_trashed' => $isTrashed,
                'url' => $url,
                'thumbnail_url' => $thumbnailUrl,
                'type' => $media->type,
                'type_label' => $media->type === MediaType::Document && $media->mime_type === 'application/pdf'
                    ? 'PDF'
                    : ucfirst($media->type->value),
                'display_name' => filled($media->title) ? $media->title : $media->original_filename,
                'size' => $this->formatBytes($media->size),
            ];
        }

        return $presentations;
    }

    public function removeMediaItem(?string $uuid): void
    {
        if ($this->isDisabled() || ! filled($uuid)) {
            return;
        }

        if ($this->isMultiple()) {
            $state = is_array($this->getState()) ? $this->getState() : [];
            $state = array_values(array_diff($state, [$uuid]));
            $this->state($state);
        } else {
            $this->state(null);
        }

        $this->callAfterStateUpdated();
    }

    public function moveItem(?string $uuid, int $direction): void
    {
        if ($this->isDisabled() || ! filled($uuid) || ! $this->isMultiple()) {
            return;
        }

        $state = is_array($this->getState()) ? array_values($this->getState()) : [];
        $index = array_search($uuid, $state, true);

        if ($index === false) {
            return;
        }

        $targetIndex = $index + $direction;

        if ($targetIndex < 0 || $targetIndex >= count($state)) {
            return;
        }

        // Swap
        $temp = $state[$index];
        $state[$index] = $state[$targetIndex];
        $state[$targetIndex] = $temp;

        $this->state($state);
        $this->callAfterStateUpdated();
    }

    #[ExposedLivewireMethod]
    public function applySelectedMedia(mixed $selection = null): void
    {
        if ($this->isDisabled()) {
            return;
        }

        if ($this->isMultiple()) {
            $uuids = match (true) {
                is_array($selection) => array_values(array_filter($selection, fn ($id): bool => is_string($id) && filled($id))),
                is_string($selection) && filled($selection) => [$selection],
                default => [],
            };

            $max = $this->getMaxItems();
            if ($max !== null && count($uuids) > $max) {
                $uuids = array_slice($uuids, 0, $max);
            }

            $this->state($uuids);
        } else {
            $uuid = match (true) {
                is_array($selection) => array_values(array_filter($selection, fn ($id): bool => is_string($id) && filled($id)))[0] ?? null,
                is_string($selection) && filled($selection) => $selection,
                default => null,
            };

            $this->state($uuid);
        }

        $this->callAfterStateUpdated();
    }

    public function getPickerAction(): Action
    {
        return Action::make('openPicker')
            ->label('Choose from Media Library')
            ->icon(Heroicon::OutlinedPhoto)
            ->disabled(fn (): bool => $this->isDisabled())
            ->modalHeading(fn (): string => $this->getLabel() ?: 'Choose Media')
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'fml-picker-modal-window'])
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalContent(fn (): View => view('filament-media-library::filament.components.media-picker-modal-container', [
                'field' => $this,
                'statePath' => $this->getStatePath(),
                'componentKey' => $this->getKey(),
                'isMultiple' => $this->isMultiple(),
                'maxItems' => $this->getMaxItems(),
                'acceptedTypes' => $this->getAcceptedMediaTypes(),
                'allowUpload' => $this->allowsUpload(),
                'currentState' => $this->getState(),
            ]));
    }

    public function getRemoveAction(): Action
    {
        return Action::make('removeMedia')
            ->label('Remove')
            ->icon(Heroicon::XMark)
            ->color('danger')
            ->disabled(fn (): bool => $this->isDisabled())
            ->action(function (array $arguments): void {
                $this->removeMediaItem($arguments['uuid'] ?? null);
            });
    }

    public function getMoveUpAction(): Action
    {
        return Action::make('moveMediaUp')
            ->label('Move up')
            ->icon(Heroicon::ChevronUp)
            ->color('gray')
            ->disabled(fn (): bool => $this->isDisabled())
            ->action(function (array $arguments): void {
                $this->moveItem($arguments['uuid'] ?? null, -1);
            });
    }

    public function getMoveDownAction(): Action
    {
        return Action::make('moveMediaDown')
            ->label('Move down')
            ->icon(Heroicon::ChevronDown)
            ->color('gray')
            ->disabled(fn (): bool => $this->isDisabled())
            ->action(function (array $arguments): void {
                $this->moveItem($arguments['uuid'] ?? null, 1);
            });
    }

    private function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes < 1024 => "{$bytes} B",
            $bytes < 1024 * 1024 => number_format($bytes / 1024, 1).' KB',
            $bytes < 1024 * 1024 * 1024 => number_format($bytes / (1024 * 1024), 1).' MB',
            default => number_format($bytes / (1024 * 1024 * 1024), 1).' GB',
        };
    }
}
