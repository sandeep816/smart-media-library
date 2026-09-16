<div class="fml-details-actions" x-data="{ copied: false }">
    @if ($presentation['url'])
        <x-filament::button
            color="gray"
            icon="heroicon-m-link"
            x-on:click="navigator.clipboard.writeText(@js($presentation['url'])); copied = true; setTimeout(() => copied = false, 2000)"
        >
            <span x-text="copied ? 'Copied' : 'Copy URL'">Copy URL</span>
        </x-filament::button>

        <x-filament::button
            tag="a"
            :href="$presentation['url']"
            color="gray"
            icon="heroicon-m-arrow-down-tray"
            download
        >
            Download
        </x-filament::button>

        <x-filament::button
            tag="a"
            :href="$presentation['url']"
            target="_blank"
            rel="noopener noreferrer"
            color="gray"
            icon="heroicon-m-arrow-top-right-on-square"
        >
            Open in new tab
        </x-filament::button>
    @else
        <p class="fml-details-actions__unavailable">A public URL is not available for this file.</p>
    @endif
</div>
