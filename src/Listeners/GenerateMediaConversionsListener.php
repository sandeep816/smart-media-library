<?php

namespace FilamentMediaLibrary\Listeners;

use FilamentMediaLibrary\Contracts\MediaConversionManager;
use FilamentMediaLibrary\Events\MediaCreated;
use FilamentMediaLibrary\Support\MediaType;
use Throwable;

class GenerateMediaConversionsListener
{
    public function __construct(
        protected MediaConversionManager $conversionManager,
    ) {}

    public function handle(MediaCreated $event): void
    {
        $media = $event->media;

        if ($media->type !== MediaType::Image) {
            return;
        }

        try {
            $this->conversionManager->generate($media);
        } catch (Throwable) {
            // Conversion generation failure must never corrupt or abort successfully stored original Media.
        }
    }
}
