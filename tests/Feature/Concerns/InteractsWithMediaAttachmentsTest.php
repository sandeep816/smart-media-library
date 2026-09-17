<?php

namespace FilamentMediaLibrary\Tests\Feature\Concerns;

use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Tests\Fixtures\TestArticle;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

class InteractsWithMediaAttachmentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_articles', function ($table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->string('featured_image_uuid')->nullable();
            $table->json('gallery_uuids')->nullable();
            $table->timestamps();
        });
    }

    public function test_trait_provides_convenient_media_helpers(): void
    {
        $m1 = Media::query()->create($this->mediaAttributes(['filename' => '1.jpg', 'original_filename' => '1.jpg']));
        $m2 = Media::query()->create($this->mediaAttributes(['filename' => '2.jpg', 'original_filename' => '2.jpg']));

        $article = TestArticle::query()->create(['title' => 'Article with Trait']);

        $article->attachMedia('featured', $m1->uuid);
        $article->attachMedia('gallery', [$m1->uuid, $m2->uuid]);

        // singleMediaFor
        $this->assertSame($m1->id, $article->singleMediaFor('featured')?->id);

        // mediaFor
        $gallery = $article->mediaFor('gallery');
        $this->assertCount(2, $gallery);
        $this->assertSame([$m1->uuid, $m2->uuid], $article->mediaUuidsFor('gallery'));

        // detachMedia
        $article->detachMedia('gallery', [$m1->uuid]);
        $this->assertSame([$m2->uuid], $article->mediaUuidsFor('gallery'));

        // syncMedia
        $article->syncMedia('gallery', [$m1->uuid]);
        $this->assertSame([$m1->uuid], $article->mediaUuidsFor('gallery'));

        // relationship
        $this->assertCount(2, $article->mediaAttachments);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function mediaAttributes(array $overrides = []): array
    {
        return array_merge([
            'filename' => 'test-file.jpg',
            'original_filename' => 'test-file.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 1024,
            'disk' => 'public',
            'directory' => 'media',
        ], $overrides);
    }
}
