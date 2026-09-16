<?php

namespace FilamentMediaLibrary\Contracts;

use FilamentMediaLibrary\Models\Media;

interface MediaStorage
{
    public function path(Media $media): string;

    public function exists(Media $media): bool;

    public function url(Media $media): ?string;

    public function delete(Media $media): bool;
}
