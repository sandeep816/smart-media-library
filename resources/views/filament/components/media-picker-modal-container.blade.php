<div>
    @livewire('filament-media-library-picker-modal', [
        'statePath' => $statePath,
        'componentKey' => $componentKey ?? $statePath,
        'isMultiple' => $isMultiple,
        'maxItems' => $maxItems,
        'acceptedTypes' => $acceptedTypes,
        'allowUpload' => $allowUpload,
        'currentState' => $currentState,
    ], key($statePath . '-picker-modal-' . md5(json_encode($currentState))))
</div>
