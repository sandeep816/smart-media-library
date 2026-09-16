<x-filament-panels::page>
    <div class="fml-library">
        <nav class="fml-tabs" role="tablist" aria-label="Media collection">
            @foreach (['library' => ['Library', $libraryCount], 'trash' => ['Trash', $trashCount]] as $value => [$label, $count])
                <button
                    type="button"
                    role="tab"
                    wire:click="$set('collectionView', '{{ $value }}')"
                    aria-selected="{{ $this->collectionView === $value ? 'true' : 'false' }}"
                    aria-label="{{ $label }}, {{ $count }} {{ str('item')->plural($count) }}"
                    @class([
                        'fml-tab',
                        'fml-tab--active' => $this->collectionView === $value,
                    ])
                >
                    <span>{{ $label }}</span>
                    <span class="fml-tab__count">{{ number_format($count) }}</span>
                </button>
            @endforeach
        </nav>

        <section class="fml-toolbar-panel" aria-label="Search and filter media">
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
                    <x-filament::input.wrapper class="fml-toolbar__filter">
                        <x-filament::input.select wire:model.live="typeFilter" aria-label="Filter by media type">
                            <option value="all">All types</option>
                            <option value="images">Images</option>
                            <option value="videos">Videos</option>
                            <option value="audio">Audio</option>
                            <option value="documents">Documents</option>
                            <option value="other">Other</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>

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

                    <div class="fml-layout-toggle" role="group" aria-label="Media layout">
                        @foreach (['grid' => 'heroicon-m-squares-2x2', 'list' => 'heroicon-m-list-bullet'] as $value => $icon)
                            <x-filament::icon-button
                                :icon="$icon"
                                :label="ucfirst($value).' view'"
                                :tooltip="ucfirst($value).' view'"
                                color="gray"
                                wire:click="$set('layoutMode', '{{ $value }}')"
                                aria-pressed="{{ $this->layoutMode === $value ? 'true' : 'false' }}"
                                @class([
                                    'fml-layout-button',
                                    'fml-layout-button--active' => $this->layoutMode === $value,
                                ])
                            />
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        @if ($media->isNotEmpty())
            @if ($this->layoutMode === 'grid')
                <div class="fml-media-grid">
                    @foreach ($media as $item)
                        @php($presentation = $presentations[$item->getKey()])
                        <article wire:key="media-grid-{{ $item->getKey() }}" class="fml-media-card">
                            <button
                                type="button"
                                class="fml-media-card__preview-button"
                                wire:click="mountAction('details', { media: {{ $item->getKey() }} })"
                                aria-label="View details for {{ $presentation['display_name'] }}"
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
                                </span>
                            </button>

                            <div class="fml-media-card__body">
                                <button
                                    type="button"
                                    class="fml-media-card__summary"
                                    wire:click="mountAction('details', { media: {{ $item->getKey() }} })"
                                    title="{{ $presentation['display_name'] }}"
                                    aria-label="View details for {{ $presentation['display_name'] }}"
                                >
                                    <span class="fml-media-card__name">{{ $presentation['display_name'] }}</span>
                                    <span class="fml-media-card__meta">{{ $presentation['type_label'] }} <span aria-hidden="true">&middot;</span> {{ $presentation['size'] }}</span>
                                </button>

                                @include('filament-media-library::filament.components.media-actions', [
                                    'item' => $item,
                                    'presentation' => $presentation,
                                ])
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="fml-media-list" role="list">
                    @foreach ($media as $item)
                        @php($presentation = $presentations[$item->getKey()])
                        <article wire:key="media-list-{{ $item->getKey() }}" class="fml-media-list-row" role="listitem">
                            <button
                                type="button"
                                class="fml-media-list-row__preview-button"
                                wire:click="mountAction('details', { media: {{ $item->getKey() }} })"
                                aria-label="View details for {{ $presentation['display_name'] }}"
                            >
                                <span class="fml-media-list-row__preview">
                                    @if (! $presentation['exists'])
                                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="fml-media-list-row__icon" />
                                    @elseif ($presentation['type'] === \FilamentMediaLibrary\Support\MediaType::Image && ($presentation['thumbnail_url'] ?? $presentation['url']))
                                        <img
                                            src="{{ $presentation['thumbnail_url'] ?? $presentation['url'] }}"
                                            alt=""
                                            class="fml-media-list-row__image"
                                            loading="lazy"
                                        >
                                    @else
                                        <x-filament::icon
                                            :icon="match ($presentation['type']) {
                                                \FilamentMediaLibrary\Support\MediaType::Image => 'heroicon-o-photo',
                                                \FilamentMediaLibrary\Support\MediaType::Video => 'heroicon-o-video-camera',
                                                \FilamentMediaLibrary\Support\MediaType::Audio => 'heroicon-o-musical-note',
                                                \FilamentMediaLibrary\Support\MediaType::Document => 'heroicon-o-document-text',
                                                default => 'heroicon-o-document',
                                            }"
                                            class="fml-media-list-row__icon"
                                        />
                                    @endif
                                </span>
                            </button>

                            <button
                                type="button"
                                class="fml-media-list-row__name"
                                wire:click="mountAction('details', { media: {{ $item->getKey() }} })"
                                title="{{ $presentation['display_name'] }}"
                            >
                                <span>{{ $presentation['display_name'] }}</span>
                                @if (! $presentation['exists'])
                                    <small>File unavailable</small>
                                @endif
                            </button>

                            <div class="fml-media-list-row__metadata">
                                <span>{{ $presentation['type_label'] }}</span>
                                <span>{{ $presentation['size'] }}</span>
                                <time datetime="{{ $item->created_at?->toAtomString() }}">{{ $item->created_at?->format('M j, Y') }}</time>
                            </div>

                            @include('filament-media-library::filament.components.media-actions', [
                                'item' => $item,
                                'presentation' => $presentation,
                            ])
                        </article>
                    @endforeach
                </div>
            @endif

            @if ($media->hasPages())
                <div>{{ $media->onEachSide(1)->links() }}</div>
            @endif
        @elseif ($unfilteredMediaCount === 0 && ! $hasActiveFilters)
            <section class="fml-empty-state">
                <div class="fml-empty-state__content">
                    <div class="fml-empty-state__icon">
                        <x-filament::icon :icon="$this->collectionView === 'trash' ? 'heroicon-o-trash' : 'heroicon-o-photo'" />
                    </div>
                    <div>
                        <h2>{{ $this->collectionView === 'trash' ? 'Trash is empty' : 'No media yet' }}</h2>
                        <p>{{ $this->collectionView === 'trash' ? 'Items moved to trash will appear here.' : 'Upload your first file to start building your media library.' }}</p>
                    </div>
                    @if ($this->collectionView === 'library')
                        <x-filament::button icon="heroicon-m-arrow-up-tray" wire:click="mountAction('upload')">Upload Media</x-filament::button>
                    @endif
                </div>
            </section>
        @else
            <section class="fml-empty-state">
                <div class="fml-empty-state__content">
                    <div class="fml-empty-state__icon fml-empty-state__icon--neutral">
                        <x-filament::icon icon="heroicon-o-magnifying-glass" />
                    </div>
                    <div>
                        <h2>No media found</h2>
                        <p>Try a different search or clear the current filters.</p>
                    </div>
                    <x-filament::button color="gray" wire:click="clearFilters">Clear filters</x-filament::button>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
