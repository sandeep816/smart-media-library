<div class="fml-details-preview-column">
    <section class="fml-details-preview" aria-label="Media preview">
        @if (! $presentation['exists'])
            <div class="fml-details-preview__placeholder">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" />
                <p>File unavailable</p>
            </div>
        @elseif ($presentation['type'] === \FilamentMediaLibrary\Support\MediaType::Image && ($presentation['medium_url'] ?? $presentation['url']))
            <img
                src="{{ $presentation['medium_url'] ?? $presentation['url'] }}"
                alt="Preview of {{ $presentation['display_name'] }}"
                class="fml-details-preview__media"
            >

            <a
                href="{{ $presentation['url'] }}"
                target="_blank"
                rel="noopener noreferrer"
                class="fml-details-preview__full-size"
            >
                <x-filament::icon icon="heroicon-m-magnifying-glass" />
                <span>View full size</span>
            </a>
        @elseif ($presentation['type'] === \FilamentMediaLibrary\Support\MediaType::Video && $presentation['url'])
            <video controls preload="metadata" class="fml-details-preview__media" aria-label="Preview of {{ $presentation['display_name'] }}">
                <source src="{{ $presentation['url'] }}" type="{{ $media->mime_type }}">
            </video>
        @elseif ($presentation['type'] === \FilamentMediaLibrary\Support\MediaType::Audio && $presentation['url'])
            <div class="fml-details-preview__audio">
                <x-filament::icon icon="heroicon-o-musical-note" />
                <audio controls preload="metadata" aria-label="Preview of {{ $presentation['display_name'] }}">
                    <source src="{{ $presentation['url'] }}" type="{{ $media->mime_type }}">
                </audio>
            </div>
        @else
            <div class="fml-details-preview__placeholder">
                <x-filament::icon
                    :icon="match ($presentation['type']) {
                        \FilamentMediaLibrary\Support\MediaType::Image => 'heroicon-o-photo',
                        \FilamentMediaLibrary\Support\MediaType::Video => 'heroicon-o-video-camera',
                        \FilamentMediaLibrary\Support\MediaType::Audio => 'heroicon-o-musical-note',
                        \FilamentMediaLibrary\Support\MediaType::Document => 'heroicon-o-document-text',
                        default => 'heroicon-o-document',
                    }"
                />
                <p>{{ $presentation['url'] ? 'Preview not available' : 'Private file' }}</p>
            </div>
        @endif
    </section>

    @include('filament-media-library::filament.components.media-quick-actions', [
        'presentation' => $presentation,
    ])

    <section class="fml-details-information" aria-labelledby="media-file-information-heading">
        <header class="fml-details-section-heading">
            <span class="fml-details-section-heading__icon">
                <x-filament::icon icon="heroicon-o-information-circle" />
            </span>
            <span>
                <h3 id="media-file-information-heading">File information</h3>
                <p>Read-only technical details for the stored original.</p>
            </span>
        </header>

        <dl class="fml-details-information__grid">
            <div>
                <dt>Original filename</dt>
                <dd>{{ $media->original_filename }}</dd>
            </div>
            <div>
                <dt>UUID</dt>
                <dd>{{ $media->uuid }}</dd>
            </div>
            <div>
                <dt>Type / MIME</dt>
                <dd>{{ $presentation['type_label'] }} &middot; {{ $media->mime_type }}</dd>
            </div>
            <div>
                <dt>Disk</dt>
                <dd>{{ $media->disk }}</dd>
            </div>
            <div>
                <dt>Size</dt>
                <dd>{{ $presentation['size'] }}</dd>
            </div>
            <div>
                <dt>Relative path</dt>
                <dd>{{ $media->path }}</dd>
            </div>
            <div>
                <dt>Dimensions</dt>
                <dd>{{ $media->width && $media->height ? $media->width.' × '.$media->height.' px' : 'Not available' }}</dd>
            </div>
            <div>
                <dt>Uploaded</dt>
                <dd>{{ $media->created_at?->format('M j, Y, g:i A') }}</dd>
            </div>
        </dl>

        <div class="fml-details-lifecycle">
            @if ($media->trashed())
                <x-filament::button
                    color="success"
                    icon="heroicon-m-arrow-path"
                    wire:click="mountAction('restore', { media: {{ $media->getKey() }} })"
                >
                    Restore
                </x-filament::button>
                <p>Return this item to the active media library.</p>
            @else
                <x-filament::button
                    color="danger"
                    icon="heroicon-m-trash"
                    wire:click="mountAction('delete', { media: {{ $media->getKey() }} })"
                >
                    Move to trash
                </x-filament::button>
                <p>The original file remains stored and can be restored later.</p>
            @endif
        </div>
    </section>
</div>
