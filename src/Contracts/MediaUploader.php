<?php

namespace FilamentMediaLibrary\Contracts;

use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Support\MediaUploadOptions;
use Illuminate\Http\UploadedFile;

interface MediaUploader
{
    public function upload(UploadedFile $file, ?MediaUploadOptions $options = null): Media;
}
