<?php

namespace FilamentMediaLibrary\Tests\Feature\Models;

use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Models\MediaAttachment;
use FilamentMediaLibrary\Tests\Fixtures\TestArticle;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

class MediaAttachmentTest extends TestCase
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

    public function test_media_attachment_persists_with_casts_and_relationships(): void
    {
        $media = Media::query()->create($this->mediaAttributes());
        $article = TestArticle::query()->create(['title' => 'Sample Article']);

        $attachment = MediaAttachment::query()->create([
            'media_id' => $media->getKey(),
            'mediable_type' => $article->getMorphClass(),
            'mediable_id' => (string) $article->getKey(),
            'collection' => 'featured',
            'position' => 0,
            'metadata' => ['crop' => '16:9'],
        ]);

        $this->assertModelExists($attachment);
        $this->assertSame(0, $attachment->position);
        $this->assertSame(['crop' => '16:9'], $attachment->metadata);
        $this->assertInstanceOf(Media::class, $attachment->media);
        $this->assertSame($media->id, $attachment->media->id);
        $this->assertInstanceOf(TestArticle::class, $attachment->mediable);
        $this->assertSame($article->id, $attachment->mediable->id);
    }

    public function test_media_attachments_relationship_on_media_model(): void
    {
        $media = Media::query()->create($this->mediaAttributes());
        $article1 = TestArticle::query()->create(['title' => 'Article 1']);
        $article2 = TestArticle::query()->create(['title' => 'Article 2']);

        MediaAttachment::query()->create([
            'media_id' => $media->getKey(),
            'mediable_type' => $article1->getMorphClass(),
            'mediable_id' => (string) $article1->getKey(),
            'collection' => 'featured',
            'position' => 0,
        ]);

        MediaAttachment::query()->create([
            'media_id' => $media->getKey(),
            'mediable_type' => $article2->getMorphClass(),
            'mediable_id' => (string) $article2->getKey(),
            'collection' => 'gallery',
            'position' => 0,
        ]);

        $this->assertCount(2, $media->attachments);
    }

    public function test_attachment_scopes(): void
    {
        $media1 = Media::query()->create($this->mediaAttributes());
        $media2 = Media::query()->create($this->mediaAttributes(['filename' => 'second.jpg', 'original_filename' => 'second.jpg']));
        $article = TestArticle::query()->create(['title' => 'Article']);

        MediaAttachment::query()->create([
            'media_id' => $media1->getKey(),
            'mediable_type' => $article->getMorphClass(),
            'mediable_id' => (string) $article->getKey(),
            'collection' => 'gallery',
            'position' => 1,
        ]);

        MediaAttachment::query()->create([
            'media_id' => $media2->getKey(),
            'mediable_type' => $article->getMorphClass(),
            'mediable_id' => (string) $article->getKey(),
            'collection' => 'gallery',
            'position' => 0,
        ]);

        $gallery = MediaAttachment::query()->forCollection('gallery')->ordered()->get();

        $this->assertCount(2, $gallery);
        $this->assertSame($media2->getKey(), $gallery[0]->media_id);
        $this->assertSame($media1->getKey(), $gallery[1]->media_id);
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
