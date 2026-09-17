<?php

namespace FilamentMediaLibrary\Tests\Fixtures;

use FilamentMediaLibrary\Concerns\InteractsWithMediaAttachments;
use Illuminate\Database\Eloquent\Model;

class TestArticle extends Model
{
    use InteractsWithMediaAttachments;

    protected $table = 'test_articles';

    protected $fillable = [
        'title',
        'featured_image_uuid',
        'gallery_uuids',
        'gallery_media_uuids',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gallery_uuids' => 'array',
            'gallery_media_uuids' => 'array',
        ];
    }
}
