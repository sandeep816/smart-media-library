<x-filament::dropdown placement="bottom-end" shift teleport width="xs">
    <x-slot name="trigger">
        <x-filament::icon-button
            icon="heroicon-m-ellipsis-vertical"
            color="gray"
            label="Media actions"
            tooltip="Media actions"
            class="fml-media-actions__trigger"
        />
    </x-slot>

    <x-filament::dropdown.list>
        <x-filament::dropdown.list.item
            icon="heroicon-m-eye"
            wire:click="mountAction('details', { media: {{ $item->getKey() }} })"
        >
            View details
        </x-filament::dropdown.list.item>

        @if ($presentation['url'])
            <x-filament::dropdown.list.item
                icon="heroicon-m-link"
                x-data="{ copied: false }"
                x-on:click="navigator.clipboard.writeText(@js($presentation['url'])); copied = true; setTimeout(() => copied = false, 2000)"
            >
                <span x-text="copied ? 'Copied' : 'Copy URL'">Copy URL</span>
            </x-filament::dropdown.list.item>
        @endif

        @if ($this->collectionView === 'trash')
            @if (\FilamentMediaLibrary\Support\MediaAuthorization::can('restore', $item))
                <x-filament::dropdown.list.item
                    icon="heroicon-m-arrow-path"
                    color="success"
                    wire:click="mountAction('restore', { media: {{ $item->getKey() }} })"
                >
                    Restore
                </x-filament::dropdown.list.item>
            @endif
        @else
            @if (\FilamentMediaLibrary\Support\MediaAuthorization::can('trash', $item))
                <x-filament::dropdown.list.item
                    icon="heroicon-m-trash"
                    color="danger"
                    wire:click="mountAction('delete', { media: {{ $item->getKey() }} })"
                >
                    Move to trash
                </x-filament::dropdown.list.item>
            @endif
        @endif
    </x-filament::dropdown.list>
</x-filament::dropdown>
