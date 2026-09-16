@php
    $statePath = $getStatePath();
    $key = $getKey();
    $isMultiple = $isMultiple();
    $isReorderable = $isMultiple && $isReorderable();
    $presentations = $getSelectedMediaPresentations();
    $hasSelection = count($presentations) > 0;
    $maxItems = $getMaxItems();
    $isMaxReached = $maxItems !== null && count($presentations) >= $maxItems;
@endphp

<x-filament-forms::field-wrapper
    :field="$field"
    class="fml-picker-wrapper"
>
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
        }"
        x-on:media-picker-confirmed.window="
            if ($event.detail.statePath === @js($statePath) || ($event.detail.componentKey && $event.detail.componentKey === @js($key))) {
                if (typeof state !== 'undefined') {
                    state = $event.detail.selection;
                }
                if (typeof $wire !== 'undefined' && typeof $wire.callSchemaComponentMethod === 'function') {
                    $wire.callSchemaComponentMethod(@js($key), 'applySelectedMedia', { selection: $event.detail.selection });
                } else if (typeof $wire !== 'undefined' && typeof $wire.$set === 'function') {
                    $wire.$set(@js($statePath), $event.detail.selection);
                }
                const modalId = $event.detail.modalId || document.querySelector('.fi-modal.fi-modal-open')?.id || document.querySelector('.fi-modal-open')?.id || document.querySelector('.fml-picker-modal-window')?.closest('.fi-modal')?.id;
                if (modalId) {
                    $dispatch('close-modal', { id: modalId });
                } else {
                    $dispatch('close-modal');
                }
            }
        "
        x-on:media-picker-cancelled.window="
            if ($event.detail.statePath === @js($statePath) || ($event.detail.componentKey && $event.detail.componentKey === @js($key))) {
                const modalId = $event.detail.modalId || document.querySelector('.fi-modal.fi-modal-open')?.id || document.querySelector('.fi-modal-open')?.id || document.querySelector('.fml-picker-modal-window')?.closest('.fi-modal')?.id;
                if (modalId) {
                    $dispatch('close-modal', { id: modalId });
                } else {
                    $dispatch('close-modal');
                }
            }
        "
        class="fml-picker"
    >
        @if (! $hasSelection)
            {{-- Empty State --}}
            <div class="fml-picker-empty">
                <div class="fml-picker-empty__icon">
                    <x-filament::icon
                        :icon="match (true) {
                            in_array('image', $getAcceptedMediaTypes(), true) && count($getAcceptedMediaTypes()) === 1 => 'heroicon-o-photo',
                            in_array('video', $getAcceptedMediaTypes(), true) && count($getAcceptedMediaTypes()) === 1 => 'heroicon-o-video-camera',
                            in_array('document', $getAcceptedMediaTypes(), true) && count($getAcceptedMediaTypes()) === 1 => 'heroicon-o-document-text',
                            default => 'heroicon-o-folder',
                        }"
                        class="fml-picker-empty__svg"
                    />
                </div>
                <div class="fml-picker-empty__text">
                    <span class="fml-picker-empty__title">No media selected</span>
                    <span class="fml-picker-empty__desc">
                        @if (! empty($getAcceptedMediaTypes()))
                            Accepted: {{ implode(', ', $getAcceptedMediaTypes()) }}
                        @else
                            Select files from the central library
                        @endif
                    </span>
                </div>
                <div class="fml-picker-empty__action">
                    {{ $getAction('openPicker') }}
                </div>
            </div>
        @elseif (! $isMultiple)
            {{-- Single Selected State --}}
            @php($item = $presentations[0])
            <div class="fml-picker-single">
                <div class="fml-picker-card fml-picker-card--single">
                    <div class="fml-picker-card__preview">
                        @if ($item['is_trashed'] || ! $item['exists'])
                            <div class="fml-picker-card__placeholder fml-picker-card__placeholder--warning">
                                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="fml-picker-card__icon" />
                                <span>{{ $item['is_trashed'] ? 'Media in trash' : 'File unavailable' }}</span>
                            </div>
                        @elseif ($item['type'] === \FilamentMediaLibrary\Support\MediaType::Image && ($item['thumbnail_url'] ?? $item['url']))
                            <img
                                src="{{ $item['thumbnail_url'] ?? $item['url'] }}"
                                alt="{{ $item['display_name'] }}"
                                class="fml-picker-card__img"
                                loading="lazy"
                            >
                        @else
                            <div class="fml-picker-card__placeholder">
                                <x-filament::icon
                                    :icon="match ($item['type']) {
                                        \FilamentMediaLibrary\Support\MediaType::Image => 'heroicon-o-photo',
                                        \FilamentMediaLibrary\Support\MediaType::Video => 'heroicon-o-video-camera',
                                        \FilamentMediaLibrary\Support\MediaType::Audio => 'heroicon-o-musical-note',
                                        \FilamentMediaLibrary\Support\MediaType::Document => 'heroicon-o-document-text',
                                        default => 'heroicon-o-document',
                                    }"
                                    class="fml-picker-card__icon"
                                />
                                <span>{{ $item['type_label'] }}</span>
                                @if (! $item['url'])
                                    <span class="fml-picker-card__placeholder-note">Private file</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="fml-picker-card__details">
                        <span class="fml-picker-card__name" title="{{ $item['display_name'] }}">
                            {{ $item['display_name'] }}
                        </span>
                        <span class="fml-picker-card__meta">
                            {{ $item['type_label'] }} &middot; {{ $item['size'] }}
                        </span>
                    </div>

                    <div class="fml-picker-card__actions">
                        {{ $getAction('openPicker')->label('Change')->size('sm') }}
                        {{ ($getAction('removeMedia'))(['uuid' => $item['uuid']])->label('Remove')->size('sm') }}
                    </div>
                </div>
            </div>
        @else
            {{-- Multiple Selected State --}}
            <div class="fml-picker-multiple">
                <div class="fml-picker-grid">
                    @foreach ($presentations as $index => $item)
                        <div wire:key="picker-item-{{ $item['uuid'] }}" class="fml-picker-card">
                            <div class="fml-picker-card__preview">
                                @if ($item['is_trashed'] || ! $item['exists'])
                                    <div class="fml-picker-card__placeholder fml-picker-card__placeholder--warning">
                                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="fml-picker-card__icon" />
                                        <span>{{ $item['is_trashed'] ? 'In trash' : 'Unavailable' }}</span>
                                    </div>
                                @elseif ($item['type'] === \FilamentMediaLibrary\Support\MediaType::Image && ($item['thumbnail_url'] ?? $item['url']))
                                    <img
                                        src="{{ $item['thumbnail_url'] ?? $item['url'] }}"
                                        alt="{{ $item['display_name'] }}"
                                        class="fml-picker-card__img"
                                        loading="lazy"
                                    >
                                @else
                                    <div class="fml-picker-card__placeholder">
                                        <x-filament::icon
                                            :icon="match ($item['type']) {
                                                \FilamentMediaLibrary\Support\MediaType::Image => 'heroicon-o-photo',
                                                \FilamentMediaLibrary\Support\MediaType::Video => 'heroicon-o-video-camera',
                                                \FilamentMediaLibrary\Support\MediaType::Audio => 'heroicon-o-musical-note',
                                                \FilamentMediaLibrary\Support\MediaType::Document => 'heroicon-o-document-text',
                                                default => 'heroicon-o-document',
                                            }"
                                            class="fml-picker-card__icon"
                                        />
                                        <span>{{ $item['type_label'] }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="fml-picker-card__details">
                                <span class="fml-picker-card__name" title="{{ $item['display_name'] }}">
                                    {{ $item['display_name'] }}
                                </span>
                                <span class="fml-picker-card__meta">
                                    {{ $item['type_label'] }} &middot; {{ $item['size'] }}
                                </span>
                            </div>

                            <div class="fml-picker-card__actions">
                                @if ($isReorderable)
                                    <div class="fml-picker-card__reorder-group">
                                        {{ ($getAction('moveMediaUp'))(['uuid' => $item['uuid']])
                                            ->iconButton()
                                            ->size('sm')
                                            ->tooltip('Move up')
                                            ->extraAttributes(['aria-label' => 'Move up'])
                                            ->disabled($index === 0) }}

                                        {{ ($getAction('moveMediaDown'))(['uuid' => $item['uuid']])
                                            ->iconButton()
                                            ->size('sm')
                                            ->tooltip('Move down')
                                            ->extraAttributes(['aria-label' => 'Move down'])
                                            ->disabled($index === count($presentations) - 1) }}
                                    </div>
                                @endif

                                <div class="fml-picker-card__remove-group">
                                    {{ ($getAction('removeMedia'))(['uuid' => $item['uuid']])
                                        ->iconButton()
                                        ->size('sm')
                                        ->tooltip('Remove')
                                        ->extraAttributes(['aria-label' => 'Remove media']) }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if (! $isMaxReached)
                    <div class="fml-picker-add-more">
                        {{ $getAction('openPicker')->label('Add media') }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-forms::field-wrapper>
