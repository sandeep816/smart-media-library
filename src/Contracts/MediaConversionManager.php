<?php

namespace FilamentMediaLibrary\Contracts;

use FilamentMediaLibrary\Models\Media;

interface MediaConversionManager
{
    /**
     * Generate conversions for the given Media item.
     *
     * @param  Media  $media  The media item.
     * @param  string|null  $preset  Specific preset to generate, or null for all configured presets.
     * @param  bool  $force  Whether to overwrite existing conversions.
     * @return array<string, array<string, mixed>> Map of generated preset names to conversion metadata.
     */
    public function generate(Media $media, ?string $preset = null, bool $force = false): array;

    /**
     * Regenerate all configured conversions for the given Media item.
     *
     * @return array<string, array<string, mixed>>
     */
    public function regenerate(Media $media): array;

    /**
     * Delete all physical conversion files for the given Media item and clear metadata.
     */
    public function deleteConversions(Media $media): bool;

    /**
     * Get the relative storage path of a specific conversion preset.
     */
    public function getConversionPath(Media $media, string $preset): ?string;

    /**
     * Get the resolved URL for a specific conversion preset, or null if private/unavailable.
     */
    public function getConversionUrl(Media $media, string $preset): ?string;

    /**
     * Check if a specific conversion preset physically exists in storage.
     */
    public function conversionExists(Media $media, string $preset): bool;
}
