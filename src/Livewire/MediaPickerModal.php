<?php

namespace FilamentMediaLibrary\Livewire;

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use FilamentMediaLibrary\Contracts\MediaConversionManager;
use FilamentMediaLibrary\Contracts\MediaStorage;
use FilamentMediaLibrary\Contracts\MediaUploader;
use FilamentMediaLibrary\Exceptions\MediaUploadException;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Support\MediaAuthorization;
use FilamentMediaLibrary\Support\MediaType;
use FilamentMediaLibrary\Support\MediaUploadOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Throwable;

class MediaPickerModal extends Component
{
    use WithFileUploads;
    use WithoutUrlPagination;
    use WithPagination;

    public string $statePath = '';

    public string $componentKey = '';

    public bool $isMultiple = false;

    public ?int $maxItems = null;

    /**
     * @var array<int, string>
     */
    public array $acceptedTypes = [];

    public bool $allowUpload = true;

    /**
     * @var array<int, string>
     */
    public array $temporarySelection = [];

    public string $search = '';

    public string $typeFilter = 'all';

    public string $dateFilter = 'all';

    public string $sort = 'newest';

    public bool $showUploadForm = false;

    /**
     * @var UploadedFile|null
     */
    public $uploadedFile = null;

    public ?string $uploadErrorMessage = null;

    /**
     * @param  array<int, string>  $acceptedTypes
     */
    public function mount(
        string $statePath = '',
        string $componentKey = '',
        bool $isMultiple = false,
        ?int $maxItems = null,
        array $acceptedTypes = [],
        bool $allowUpload = true,
        mixed $currentState = null,
    ): void {
        $this->statePath = $statePath;
        $this->componentKey = filled($componentKey) ? $componentKey : $statePath;
        $this->isMultiple = $isMultiple;
        $this->maxItems = $maxItems;
        $this->acceptedTypes = array_values(array_filter($acceptedTypes, 'is_string'));
        $this->allowUpload = $allowUpload;

        if (is_array($currentState)) {
            $this->temporarySelection = array_values(array_filter($currentState, fn ($item): bool => is_string($item) && filled($item)));
        } elseif (is_string($currentState) && filled($currentState)) {
            $this->temporarySelection = [$currentState];
        } else {
            $this->temporarySelection = [];
        }

        if (count($this->acceptedTypes) === 1) {
            $this->typeFilter = $this->acceptedTypes[0];
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->typeFilter = count($this->acceptedTypes) === 1 ? $this->acceptedTypes[0] : 'all';
        $this->dateFilter = 'all';
        $this->sort = 'newest';
        $this->resetPage();
    }

    public function toggleUploadForm(): void
    {
        $this->showUploadForm = ! $this->showUploadForm;
        $this->uploadedFile = null;
        $this->uploadErrorMessage = null;
    }

    public function toggleSelect(string $uuid): void
    {
        $media = Media::query()->where('uuid', $uuid)->first();

        if (! $media) {
            return;
        }

        if (! MediaAuthorization::can('select', $media)) {
            Notification::make()
                ->title('Unauthorized')
                ->body('You are not authorized to select this media item.')
                ->danger()
                ->send();

            return;
        }

        $presentation = $this->presentMedia($media);

        // Disallow selecting unavailable files
        if (! $presentation['exists']) {
            Notification::make()
                ->title('File unavailable')
                ->body('This media item cannot be selected because its physical file is missing.')
                ->warning()
                ->send();

            return;
        }

        // Enforce type restrictions
        if (! empty($this->acceptedTypes) && ! in_array($media->type->value, $this->acceptedTypes, true)) {
            Notification::make()
                ->title('Invalid media type')
                ->body('This file is not an accepted media type for this field.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->isMultiple) {
            if ($this->temporarySelection === [$uuid]) {
                $this->temporarySelection = [];
            } else {
                $this->temporarySelection = [$uuid];
            }

            return;
        }

        // Multiple mode
        if (in_array($uuid, $this->temporarySelection, true)) {
            $this->temporarySelection = array_values(array_diff($this->temporarySelection, [$uuid]));
        } else {
            if ($this->maxItems !== null && count($this->temporarySelection) >= $this->maxItems) {
                Notification::make()
                    ->title('Maximum items reached')
                    ->body("You cannot select more than {$this->maxItems} items.")
                    ->warning()
                    ->send();

                return;
            }

            $this->temporarySelection[] = $uuid;
        }
    }

    public function updatedUploadedFile(): void
    {
        $this->uploadErrorMessage = null;

        if (! $this->uploadedFile instanceof UploadedFile) {
            return;
        }

        if (! $this->allowUpload || ! MediaAuthorization::can('upload')) {
            $this->uploadErrorMessage = 'You are not authorized to upload media.';
            $this->uploadedFile = null;

            return;
        }

        // Validate type restriction against uploaded file
        if (! empty($this->acceptedTypes)) {
            $mime = $this->uploadedFile->getMimeType() ?: '';
            $mediaType = MediaType::fromMimeType($mime);

            if (! in_array($mediaType->value, $this->acceptedTypes, true)) {
                $this->uploadErrorMessage = 'The uploaded file does not match the accepted media types for this field.';
                $this->uploadedFile = null;

                return;
            }
        }

        $uploader = app(MediaUploader::class);

        try {
            $authId = Filament::auth()->id();
            $media = $uploader->upload($this->uploadedFile, new MediaUploadOptions(
                uploadedBy: $authId !== null ? (string) $authId : null,
            ));

            $this->uploadedFile = null;
            $this->showUploadForm = false;
            $this->resetPage();

            // Auto-select the newly uploaded file
            if (! $this->isMultiple) {
                $this->temporarySelection = [$media->uuid];
            } else {
                if ($this->maxItems === null || count($this->temporarySelection) < $this->maxItems) {
                    $this->temporarySelection[] = $media->uuid;
                }
            }

            Notification::make()
                ->title('Media uploaded successfully.')
                ->success()
                ->send();
        } catch (MediaUploadException $e) {
            $this->uploadErrorMessage = $e->getMessage();
            $this->uploadedFile = null;
        } catch (Throwable $e) {
            $this->uploadErrorMessage = 'An error occurred during file upload.';
            $this->uploadedFile = null;
        }
    }

    public function confirmSelection(): void
    {
        $selection = $this->isMultiple
            ? array_values($this->temporarySelection)
            : ($this->temporarySelection[0] ?? null);

        $this->dispatch(
            'media-picker-confirmed',
            statePath: $this->statePath,
            componentKey: $this->componentKey,
            selection: $selection,
        );
    }

    public function cancelSelection(): void
    {
        $this->dispatch(
            'media-picker-cancelled',
            statePath: $this->statePath,
            componentKey: $this->componentKey,
        );
    }

    public function render(): View
    {
        $media = $this->mediaQuery()->paginate(24);

        $presentations = $media->getCollection()
            ->mapWithKeys(fn (Media $item): array => [$item->getKey() => $this->presentMedia($item)])
            ->all();

        return view('filament-media-library::filament.components.media-picker-modal', [
            'media' => $media,
            'presentations' => $presentations,
            'availableTypeOptions' => $this->getAvailableTypeOptions(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableTypeOptions(): array
    {
        $all = [
            'images' => 'Images',
            'videos' => 'Videos',
            'audio' => 'Audio',
            'documents' => 'Documents',
            'other' => 'Other',
        ];

        if (empty($this->acceptedTypes)) {
            return array_merge(['all' => 'All types'], $all);
        }

        $filtered = [];

        if (count($this->acceptedTypes) > 1) {
            $filtered['all'] = 'All accepted types';
        }

        foreach ($this->acceptedTypes as $type) {
            $pluralKey = match ($type) {
                'image' => 'images',
                'video' => 'videos',
                'audio' => 'audio',
                'document' => 'documents',
                default => 'other',
            };

            if (isset($all[$pluralKey])) {
                $filtered[$pluralKey] = $all[$pluralKey];
            }
        }

        return $filtered;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentMedia(Media $media): array
    {
        $storage = app(MediaStorage::class);

        try {
            $exists = $storage->exists($media);
        } catch (Throwable) {
            $exists = false;
        }

        $url = $exists ? $storage->url($media) : null;

        $thumbnailUrl = null;
        if ($exists && $media->type === MediaType::Image && $url !== null) {
            $conversionManager = app(MediaConversionManager::class);
            $thumbnailUrl = $conversionManager->getConversionUrl($media, 'thumbnail') ?? $url;
        }

        return [
            'exists' => $exists,
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

    private function mediaQuery(): Builder
    {
        $query = Media::query()->select([
            'id', 'uuid', 'disk', 'directory', 'filename', 'original_filename', 'mime_type',
            'extension', 'size', 'width', 'height', 'title', 'alt_text', 'caption',
            'description', 'created_at', 'deleted_at',
        ]);

        $search = trim($this->search);

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('original_filename', 'like', "%{$search}%")
                    ->orWhere('filename', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('alt_text', 'like', "%{$search}%")
                    ->orWhere('caption', 'like', "%{$search}%");
            });
        }

        $this->applyTypeFilter($query);
        $this->applyDateFilter($query);
        $this->applySort($query);

        return $query;
    }

    private function applyTypeFilter(Builder $query): void
    {
        // If field-level accepted types exist
        if (! empty($this->acceptedTypes)) {
            if ($this->typeFilter === 'all') {
                $query->where(function (Builder $q): void {
                    foreach ($this->acceptedTypes as $type) {
                        $this->addTypeCondition($q, $type);
                    }
                });

                return;
            }
        }

        match ($this->typeFilter) {
            'images', 'image' => $query->where('mime_type', 'like', 'image/%'),
            'videos', 'video' => $query->where('mime_type', 'like', 'video/%'),
            'audio' => $query->where('mime_type', 'like', 'audio/%'),
            'documents', 'document' => $query->where(function (Builder $query): void {
                $query->where('mime_type', 'like', 'text/%')->orWhereIn('mime_type', $this->documentMimeTypes());
            }),
            'other' => $query->where('mime_type', 'not like', 'image/%')
                ->where('mime_type', 'not like', 'video/%')
                ->where('mime_type', 'not like', 'audio/%')
                ->where('mime_type', 'not like', 'text/%')
                ->whereNotIn('mime_type', $this->documentMimeTypes()),
            default => null,
        };
    }

    private function addTypeCondition(Builder $query, string $type): void
    {
        match ($type) {
            'image' => $query->orWhere('mime_type', 'like', 'image/%'),
            'video' => $query->orWhere('mime_type', 'like', 'video/%'),
            'audio' => $query->orWhere('mime_type', 'like', 'audio/%'),
            'document' => $query->orWhere(function (Builder $subQuery): void {
                $subQuery->where('mime_type', 'like', 'text/%')->orWhereIn('mime_type', $this->documentMimeTypes());
            }),
            default => $query->orWhere(function (Builder $subQuery): void {
                $subQuery->where('mime_type', 'not like', 'image/%')
                    ->where('mime_type', 'not like', 'video/%')
                    ->where('mime_type', 'not like', 'audio/%')
                    ->where('mime_type', 'not like', 'text/%')
                    ->whereNotIn('mime_type', $this->documentMimeTypes());
            }),
        };
    }

    private function applyDateFilter(Builder $query): void
    {
        $now = CarbonImmutable::now();

        match ($this->dateFilter) {
            'this_month' => $query->whereBetween('created_at', [$now->startOfMonth(), $now->endOfMonth()]),
            'last_month' => $query->whereBetween('created_at', [
                $now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth(),
            ]),
            default => null,
        };
    }

    private function applySort(Builder $query): void
    {
        match ($this->sort) {
            'oldest' => $query->oldest('created_at')->oldest('id'),
            'name_asc' => $query->orderByRaw('LOWER(COALESCE(title, original_filename)) ASC')->orderBy('id'),
            'name_desc' => $query->orderByRaw('LOWER(COALESCE(title, original_filename)) DESC')->orderByDesc('id'),
            default => $query->latest('created_at')->latest('id'),
        };
    }

    /**
     * @return array<int, string>
     */
    private function documentMimeTypes(): array
    {
        return [
            'application/msword', 'application/pdf', 'application/rtf', 'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint', 'application/vnd.oasis.opendocument.presentation',
            'application/vnd.oasis.opendocument.spreadsheet', 'application/vnd.oasis.opendocument.text',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
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
