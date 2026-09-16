<div
    class="fml-picker-modal"
    x-data="{
        modalId: null,
        init() {
            this.modalId = this.$el.closest('.fi-modal')?.id;
        },
    }"
    x-on:media-picker-confirmed.window="
        if ($event.detail.statePath === @js($statePath) || ($event.detail.componentKey && $event.detail.componentKey === @js($componentKey))) {
            const id = this.modalId || this.$el.closest('.fi-modal')?.id;
            if (id) {
                $dispatch('close-modal', { id: id });
            }
        }
    "
>
    {{-- Toolbar --}}
    <div class="fml-toolbar-panel">
        <div class="fml-toolbar">
            <x-filament::input.wrapper class="fml-toolbar__search" prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Search media"
                    aria-label="Search media"
                />
            </x-filament::input.wrapper>

            <div class="fml-toolbar__filters">
                @if (count($availableTypeOptions) > 1)
                    <x-filament::input.wrapper class="fml-toolbar__filter">
                        <x-filament::input.select wire:model.live="typeFilter" aria-label="Filter by media type">
                            @foreach ($availableTypeOptions as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                @endif

                <x-filament::input.wrapper class="fml-toolbar__filter">
                    <x-filament::input.select wire:model.live="dateFilter" aria-label="Filter by upload date">
                        <option value="all">All dates</option>
                        <option value="this_month">This month</option>
                        <option value="last_month">Last month</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::input.wrapper class="fml-toolbar__filter">
                    <x-filament::input.select wire:model.live="sort" aria-label="Sort media">
                        <option value="newest">Newest</option>
                        <option value="oldest">Oldest</option>
                        <option value="name_asc">Name A-Z</option>
                        <option value="name_desc">Name Z-A</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                @if ($allowUpload && \FilamentMediaLibrary\Support\MediaAuthorization::can('upload'))
                    <x-filament::button
                        icon="heroicon-m-arrow-up-tray"
                        wire:click="toggleUploadForm"
                        color="{{ $showUploadForm ? 'gray' : 'primary' }}"
                        size="sm"
                    >
                        {{ $showUploadForm ? 'Close Upload' : 'Upload Media' }}
                    </x-filament::button>
                @endif
            </div>
        </div>

        @if ($allowUpload && \FilamentMediaLibrary\Support\MediaAuthorization::can('upload') && $showUploadForm)
            <div class="fml-picker-upload-box">
                <div class="fml-picker-upload-dropzone">
                    <input
                        type="file"
                        wire:model="uploadedFile"
                        id="fml-picker-file-input"
                        class="fml-picker-upload-input"
                        aria-label="Upload file"
                    >
                    <label for="fml-picker-file-input" class="fml-picker-upload-label">
                        <x-filament::icon icon="heroicon-o-arrow-up-tray" class="fml-picker-upload-icon" />
                        <span class="fml-picker-upload-text">Choose a file or drag it here to upload</span>
                        <span class="fml-picker-upload-subtext">
                            @if (! empty($acceptedTypes))
                                Accepted: {{ implode(', ', $acceptedTypes) }} &middot; Max size: {{ config('filament-media-library.upload.max_size_kb', 10240) }} KB
                            @else
                                Max size: {{ config('filament-media-library.upload.max_size_kb', 10240) }} KB
                            @endif
                        </span>
                    </label>

                    <div wire:loading wire:target="uploadedFile" class="fml-picker-upload-loading">
                        <span>Uploading file...</span>
                    </div>
                </div>

                @if ($uploadErrorMessage)
                    <div class="fml-picker-upload-error">
                        <x-filament::icon icon="heroicon-o-exclamation-circle" class="fml-picker-upload-error-icon" />
                        <span>{{ $uploadErrorMessage }}</span>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Grid Content --}}
    <div class="fml-picker-modal__content">
        @if ($media->isNotEmpty())
            <div class="fml-media-grid fml-picker-grid">
                @foreach ($media as $item)
                    @php
                        $presentation = $presentations[$item->getKey()];
                        $isSelected = in_array($item->uuid, $temporarySelection, true);
                        $isAvailable = $presentation['exists'];
                    @endphp
                    <article
                        wire:key="picker-card-{{ $item->getKey() }}"
                        @class([
                            'fml-media-card',
                            'fml-picker-item',
                            'fml-picker-item--selected' => $isSelected,
                            'fml-picker-item--disabled' => ! $isAvailable,
                        ])
                    >
                        <button
                            type="button"
                            wire:click="toggleSelect('{{ $item->uuid }}')"
                            @disabled(! $isAvailable)
                            class="fml-media-card__preview-button fml-picker-item__button"
                            aria-label="{{ $isSelected ? 'Deselect' : 'Select' }} {{ $presentation['display_name'] }}"
                            aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                        >
                            <span class="fml-media-card__preview">
                                @if (! $presentation['exists'])
                                    <span class="fml-media-card__placeholder">
                                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="fml-media-card__placeholder-icon" />
                                        <span>File unavailable</span>
                                    </span>
                                @elseif ($presentation['type'] === \FilamentMediaLibrary\Support\MediaType::Image && ($presentation['thumbnail_url'] ?? $presentation['url']))
                                    <img
                                        src="{{ $presentation['thumbnail_url'] ?? $presentation['url'] }}"
                                        alt="Preview of {{ $presentation['display_name'] }}"
                                        class="fml-media-card__image"
                                        loading="lazy"
                                    >
                                @else
                                    <span class="fml-media-card__placeholder">
                                        <x-filament::icon
                                            :icon="match ($presentation['type']) {
                                                \FilamentMediaLibrary\Support\MediaType::Image => 'heroicon-o-photo',
                                                \FilamentMediaLibrary\Support\MediaType::Video => 'heroicon-o-video-camera',
                                                \FilamentMediaLibrary\Support\MediaType::Audio => 'heroicon-o-musical-note',
                                                \FilamentMediaLibrary\Support\MediaType::Document => 'heroicon-o-document-text',
                                                default => 'heroicon-o-document',
                                            }"
                                            class="fml-media-card__placeholder-icon"
                                        />
                                        <span>{{ $presentation['type_label'] }}</span>
                                        @if (! $presentation['url'])
                                            <span class="fml-media-card__placeholder-note">Private file</span>
                                        @endif
                                    </span>
                                @endif

                                @if ($isSelected)
                                    <span class="fml-picker-badge">
                                        <x-filament::icon icon="heroicon-m-check" class="fml-picker-badge__icon" />
                                    </span>
                                @endif
                            </span>
                        </button>

                        <div class="fml-media-card__body">
                            <div class="fml-media-card__summary">
                                <span class="fml-media-card__name" title="{{ $presentation['display_name'] }}">
                                    {{ $presentation['display_name'] }}
                                </span>
                                <span class="fml-media-card__meta">
                                    {{ $presentation['type_label'] }} &middot; {{ $presentation['size'] }}
                                </span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($media->hasPages())
                <div class="fml-picker-pagination">
                    {{ $media->links() }}
                </div>
            @endif
        @else
            <section class="fml-empty-state">
                <div class="fml-empty-state__content">
                    <div class="fml-empty-state__icon fml-empty-state__icon--neutral">
                        <x-filament::icon icon="heroicon-o-magnifying-glass" />
                    </div>
                    <div>
                        <h2>No media found</h2>
                        <p>Try a different search or clear filters to see available files.</p>
                    </div>
                    <x-filament::button color="gray" wire:click="clearFilters">Clear filters</x-filament::button>
                </div>
            </section>
        @endif
    </div>

    {{-- Sticky Footer --}}
    <div class="fml-picker-modal__footer">
        <div class="fml-picker-modal__footer-status">
            @if ($isMultiple)
                <span class="fml-picker-modal__count">
                    {{ count($temporarySelection) }} selected
                    @if ($maxItems !== null)
                        (max {{ $maxItems }})
                    @endif
                </span>
            @elseif (! empty($temporarySelection))
                <span class="fml-picker-modal__count">1 item selected</span>
            @endif
        </div>

        <div class="fml-picker-modal__footer-actions">
            <x-filament::button
                color="gray"
                wire:click="cancelSelection"
                x-on:click="$dispatch('close-modal', { id: $el.closest('.fi-modal')?.id })"
            >
                Cancel
            </x-filament::button>

            <x-filament::button
                wire:click="confirmSelection"
                color="primary"
                wire:loading.attr="disabled"
            >
                Use selected media
            </x-filament::button>
        </div>
    </div>
</div>
