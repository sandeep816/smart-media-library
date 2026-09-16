<?php

namespace FilamentMediaLibrary\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use FilamentMediaLibrary\Contracts\MediaConversionManager;
use FilamentMediaLibrary\Contracts\MediaStorage;
use FilamentMediaLibrary\Contracts\MediaUploader;
use FilamentMediaLibrary\Exceptions\MediaUploadException;
use FilamentMediaLibrary\MediaLibraryPlugin;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Support\MediaAuthorization;
use FilamentMediaLibrary\Support\MediaType;
use FilamentMediaLibrary\Support\MediaUploadOptions;
use FilamentMediaLibrary\Support\MimeTypeExtension;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Throwable;
use UnitEnum;

class MediaLibrary extends Page
{
    use WithPagination;

    protected static ?string $title = 'Media Library';

    protected string $view = 'filament-media-library::filament.pages.media-library';

    protected Width|string|null $maxContentWidth = Width::Full;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'type', except: 'all')]
    public string $typeFilter = 'all';

    #[Url(as: 'date', except: 'all')]
    public string $dateFilter = 'all';

    #[Url(as: 'sort', except: 'newest')]
    public string $sort = 'newest';

    #[Url(as: 'view', except: 'library')]
    public string $collectionView = 'library';

    #[Url(as: 'layout', except: 'grid')]
    public string $layoutMode = 'grid';

    public static function canAccess(): bool
    {
        return MediaAuthorization::can('view-any');
    }

    public static function shouldRegisterNavigation(): bool
    {
        if ($plugin = MediaLibraryPlugin::get()) {
            return $plugin->getShouldRegisterNavigation();
        }

        return (bool) config('filament-media-library.should_register_navigation', true);
    }

    public function getTitle(): string|Htmlable
    {
        if ($plugin = MediaLibraryPlugin::get()) {
            if ($pluginTitle = $plugin->getTitle()) {
                return $pluginTitle;
            }
        }

        $title = config('filament-media-library.page_title');

        return is_string($title) && filled($title) ? $title : (static::$title ?? 'Media Library');
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($plugin = MediaLibraryPlugin::get()) {
            if ($pluginSubheading = $plugin->getSubheading()) {
                return $pluginSubheading;
            }
        }

        $subheading = config('filament-media-library.page_subheading');

        return is_string($subheading) ? $subheading : 'Manage and reuse your files from one central library.';
    }

    public static function getNavigationLabel(): string
    {
        if ($plugin = MediaLibraryPlugin::get()) {
            if ($label = $plugin->getNavigationLabel()) {
                return $label;
            }
        }

        $label = config('filament-media-library.navigation_label');

        return is_string($label) ? $label : 'Media Library';
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        if ($plugin = MediaLibraryPlugin::get()) {
            if ($icon = $plugin->getNavigationIcon()) {
                return $icon;
            }
        }

        $icon = config('filament-media-library.navigation_icon');

        return is_string($icon) || $icon instanceof BackedEnum || $icon instanceof Htmlable
            ? $icon
            : Heroicon::OutlinedPhoto;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        if ($plugin = MediaLibraryPlugin::get()) {
            if ($group = $plugin->getNavigationGroup()) {
                return $group;
            }
        }

        $group = config('filament-media-library.navigation_group');

        return is_string($group) || $group instanceof UnitEnum ? $group : null;
    }

    public static function getNavigationSort(): ?int
    {
        if ($plugin = MediaLibraryPlugin::get()) {
            if ($sort = $plugin->getNavigationSort()) {
                return $sort;
            }
        }

        $sort = config('filament-media-library.navigation_sort');

        return is_int($sort) ? $sort : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $media = $this->mediaQuery()->paginate($this->perPage());
        $libraryCount = Media::query()->count();
        $trashCount = Media::query()->onlyTrashed()->count();
        $presentations = $media->getCollection()
            ->mapWithKeys(fn (Media $item): array => [$item->getKey() => $this->presentMedia($item)])
            ->all();

        return [
            'media' => $media,
            'presentations' => $presentations,
            'libraryCount' => $libraryCount,
            'trashCount' => $trashCount,
            'unfilteredMediaCount' => $this->collectionView === 'trash' ? $trashCount : $libraryCount,
            'hasActiveFilters' => $this->hasActiveFilters(),
        ];
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
        $mediumUrl = null;

        if ($exists && $media->type === MediaType::Image && $url !== null) {
            $conversionManager = app(MediaConversionManager::class);
            $thumbnailUrl = $conversionManager->getConversionUrl($media, 'thumbnail') ?? $url;
            $mediumUrl = $conversionManager->getConversionUrl($media, 'medium') ?? $url;
        }

        return [
            'exists' => $exists,
            'url' => $url,
            'thumbnail_url' => $thumbnailUrl,
            'medium_url' => $mediumUrl,
            'type' => $media->type,
            'type_label' => $media->type === MediaType::Document && $media->mime_type === 'application/pdf'
                ? 'PDF'
                : ucfirst($media->type->value),
            'display_name' => filled($media->title) ? $media->title : $media->original_filename,
            'size' => $this->formatBytes($media->size),
        ];
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

    public function updatedCollectionView(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->typeFilter = 'all';
        $this->dateFilter = 'all';
        $this->sort = 'newest';
        $this->resetPage();
    }

    public function uploadAction(): Action
    {
        $allowedMimeTypes = config('filament-media-library.upload.allowed_mime_types', []);
        $maximumKilobytes = config('filament-media-library.upload.max_size_kb', 10240);
        $acceptedMimeTypes = is_array($allowedMimeTypes) ? $allowedMimeTypes : [];
        $maximumKilobytes = is_int($maximumKilobytes) ? $maximumKilobytes : 10240;

        return Action::make('upload')
            ->label('Upload Media')
            ->icon(Heroicon::ArrowUpTray)
            ->visible(fn (): bool => MediaAuthorization::can('upload'))
            ->modalHeading('Upload Media')
            ->modalDescription('Add one file to the central media library.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Upload Media')
            ->schema([
                Section::make('File')
                    ->description('Choose one file to add to the central library.')
                    ->schema([
                        FileUpload::make('file')
                            ->label('File')
                            ->placeholder('Drop your file here or Browse')
                            ->helperText($this->uploadConstraintsText($acceptedMimeTypes, $maximumKilobytes))
                            ->acceptedFileTypes($acceptedMimeTypes)
                            ->maxSize($maximumKilobytes)
                            ->imagePreviewHeight('180')
                            ->panelLayout('integrated')
                            ->storeFiles(false)
                            ->required(),
                    ]),
            ])
            ->action(function (array $data, MediaUploader $uploader): void {
                $file = $data['file'] ?? null;

                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages([
                        'file' => 'The uploaded file is invalid or incomplete.',
                    ]);
                }

                try {
                    $uploader->upload($file, new MediaUploadOptions(
                        title: $this->nullableString($data['title'] ?? null),
                        altText: $this->nullableString($data['alt_text'] ?? null),
                        caption: $this->nullableString($data['caption'] ?? null),
                        description: $this->nullableString($data['description'] ?? null),
                        uploadedBy: Filament::auth()->id() === null ? null : (string) Filament::auth()->id(),
                    ));
                } catch (MediaUploadException $exception) {
                    throw ValidationException::withMessages([
                        'file' => $exception->getMessage(),
                    ]);
                }

                $this->resetPage();

                Notification::make()
                    ->title('Media uploaded successfully.')
                    ->success()
                    ->send();
            });
    }

    public function detailsAction(): Action
    {
        return Action::make('details')
            ->label('View details')
            ->icon(Heroicon::PencilSquare)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalHeading(fn (array $arguments): string => $this->resolveMedia($arguments)->original_filename)
            ->modalDescription('View details and edit metadata.')
            ->disabledForm(fn (array $arguments): bool => ! MediaAuthorization::can('update', $this->resolveMedia($arguments)))
            ->modalSubmitAction(fn (Action $action, array $arguments): ?Action => MediaAuthorization::can('update', $this->resolveMedia($arguments)) ? $action : null)
            ->modalSubmitActionLabel('Save changes')
            ->modalCancelActionLabel('Cancel')
            ->modalFooterActionsAlignment(Alignment::End)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->extraModalWindowAttributes(['class' => 'fml-details-modal'])
            ->schema(function (array $arguments): array {
                $media = $this->resolveMedia($arguments);
                $presentation = $this->presentMedia($media);

                return [
                    Grid::make([
                        'default' => 1,
                        'lg' => 2,
                    ])
                        ->extraAttributes(['class' => 'fml-details-layout'])
                        ->schema([
                            View::make('filament-media-library::filament.components.media-details')
                                ->viewData([
                                    'media' => $media,
                                    'presentation' => $presentation,
                                ]),
                            Section::make('Edit metadata')
                                ->icon(Heroicon::PencilSquare)
                                ->description('Update descriptive information without changing the original file.')
                                ->schema([
                                    TextInput::make('title')
                                        ->maxLength(255),
                                    TextInput::make('alt_text')
                                        ->label('Alt text')
                                        ->helperText('Describe the image for accessibility.')
                                        ->maxLength(255)
                                        ->visible($media->type === MediaType::Image),
                                    Textarea::make('caption')
                                        ->rows(3)
                                        ->maxLength(65535),
                                    Textarea::make('description')
                                        ->rows(4),
                                ]),
                        ]),
                ];
            })
            ->mountUsing(function (Schema $schema, array $arguments): void {
                $media = $this->resolveMedia($arguments);

                $schema->fill([
                    'title' => $media->title,
                    'alt_text' => $media->alt_text,
                    'caption' => $media->caption,
                    'description' => $media->description,
                ]);
            })
            ->action(function (array $arguments, array $data): void {
                $media = $this->resolveMedia($arguments);
                $updates = [
                    'title' => $this->nullableString($data['title'] ?? null),
                    'caption' => $this->nullableString($data['caption'] ?? null),
                    'description' => $this->nullableString($data['description'] ?? null),
                ];

                if ($media->type === MediaType::Image) {
                    $updates['alt_text'] = $this->nullableString($data['alt_text'] ?? null);
                }

                $media->update($updates);

                Notification::make()
                    ->title('Media details updated.')
                    ->success()
                    ->send();
            });
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Move to trash')
            ->icon(Heroicon::Trash)
            ->color('danger')
            ->visible(fn (array $arguments): bool => MediaAuthorization::can('trash', $this->resolveActiveMedia($arguments)))
            ->requiresConfirmation()
            ->modalHeading('Move this media item to trash?')
            ->modalDescription('The file will remain in storage and can be restored later.')
            ->action(function (array $arguments): void {
                $this->resolveActiveMedia($arguments)->delete();

                Notification::make()
                    ->title('Media moved to trash.')
                    ->success()
                    ->send();
            });
    }

    public function restoreAction(): Action
    {
        return Action::make('restore')
            ->label('Restore')
            ->icon(Heroicon::ArrowPath)
            ->color('success')
            ->visible(fn (array $arguments): bool => MediaAuthorization::can('restore', $this->resolveTrashedMedia($arguments)))
            ->action(function (array $arguments): void {
                $this->resolveTrashedMedia($arguments)->restore();

                Notification::make()
                    ->title('Media restored.')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->uploadAction()];
    }

    private function mediaQuery(): Builder
    {
        $query = $this->collectionQuery()->select([
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

    private function collectionQuery(): Builder
    {
        return $this->collectionView === 'trash' ? Media::query()->onlyTrashed() : Media::query();
    }

    private function applyTypeFilter(Builder $query): void
    {
        match ($this->typeFilter) {
            'images' => $query->where('mime_type', 'like', 'image/%'),
            'videos' => $query->where('mime_type', 'like', 'video/%'),
            'audio' => $query->where('mime_type', 'like', 'audio/%'),
            'documents' => $query->where(function (Builder $query): void {
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

    /** @return array<int, string> */
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

    /** @param array<string, mixed> $arguments */
    private function resolveMedia(array $arguments): Media
    {
        return $this->collectionView === 'trash'
            ? $this->resolveTrashedMedia($arguments)
            : $this->resolveActiveMedia($arguments);
    }

    /** @param array<string, mixed> $arguments */
    private function resolveActiveMedia(array $arguments): Media
    {
        return Media::query()->findOrFail($arguments['media'] ?? null);
    }

    /** @param array<string, mixed> $arguments */
    private function resolveTrashedMedia(array $arguments): Media
    {
        return Media::query()->onlyTrashed()->findOrFail($arguments['media'] ?? null);
    }

    private function hasActiveFilters(): bool
    {
        return trim($this->search) !== '' || $this->typeFilter !== 'all'
            || $this->dateFilter !== 'all' || $this->sort !== 'newest';
    }

    private function perPage(): int
    {
        $perPage = config('filament-media-library.pagination.per_page', 24);

        return is_int($perPage) && $perPage > 0 ? $perPage : 24;
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

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<int, mixed>  $mimeTypes
     */
    private function uploadConstraintsText(array $mimeTypes, int $maximumKilobytes): string
    {
        $extensions = collect($mimeTypes)
            ->filter(fn (mixed $mimeType): bool => is_string($mimeType))
            ->map(fn (string $mimeType): ?string => MimeTypeExtension::resolve($mimeType))
            ->filter()
            ->unique()
            ->map(fn (string $extension): string => strtoupper($extension))
            ->implode(', ');

        $allowedTypes = $extensions !== '' ? $extensions : 'Configured file types';

        return "Allowed types: {$allowedTypes}. Maximum size: {$this->formatBytes($maximumKilobytes * 1024)}.";
    }
}
