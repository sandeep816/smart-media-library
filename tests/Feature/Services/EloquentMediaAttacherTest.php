<?php

namespace FilamentMediaLibrary\Tests\Feature\Services;

use FilamentMediaLibrary\Contracts\MediaAttacher;
use FilamentMediaLibrary\Contracts\MediaStorage;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Models\MediaAttachment;
use FilamentMediaLibrary\Tests\Fixtures\TestArticle;
use FilamentMediaLibrary\Tests\Fixtures\TestUlidEntity;
use FilamentMediaLibrary\Tests\Fixtures\TestUuidEntity;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class EloquentMediaAttacherTest extends TestCase
{
    use LazilyRefreshDatabase;

    private MediaAttacher $attacher;

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

        Schema::create('test_uuid_entities', function ($table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('test_ulid_entities', function ($table): void {
            $table->ulid('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        $this->attacher = app(MediaAttacher::class);
    }

    public function test_attaches_single_media_to_model(): void
    {
        $media = Media::query()->create($this->mediaAttributes());
        $article = TestArticle::query()->create(['title' => 'Article 1']);

        $this->attacher->attach($article, 'featured', $media->uuid);

        $this->assertSame([$media->uuid], $this->attacher->getAttachedMediaUuids($article, 'featured'));
        $this->assertCount(1, $this->attacher->getAttachedMedia($article, 'featured'));
    }

    public function test_attaches_multiple_media_with_deterministic_ordering(): void
    {
        $m1 = Media::query()->create($this->mediaAttributes(['filename' => '1.jpg', 'original_filename' => '1.jpg']));
        $m2 = Media::query()->create($this->mediaAttributes(['filename' => '2.jpg', 'original_filename' => '2.jpg']));
        $m3 = Media::query()->create($this->mediaAttributes(['filename' => '3.jpg', 'original_filename' => '3.jpg']));

        $article = TestArticle::query()->create(['title' => 'Article Gallery']);

        $this->attacher->sync($article, 'gallery', [$m3->uuid, $m1->uuid, $m2->uuid]);

        $uuids = $this->attacher->getAttachedMediaUuids($article, 'gallery');

        $this->assertSame([$m3->uuid, $m1->uuid, $m2->uuid], $uuids);

        // Verify database positions
        $attachments = MediaAttachment::query()->where('collection', 'gallery')->orderBy('position')->get();
        $this->assertSame(0, $attachments[0]->position);
        $this->assertSame($m3->id, $attachments[0]->media_id);
        $this->assertSame(1, $attachments[1]->position);
        $this->assertSame($m1->id, $attachments[1]->media_id);
        $this->assertSame(2, $attachments[2]->position);
        $this->assertSame($m2->id, $attachments[2]->media_id);
    }

    public function test_same_media_reused_across_different_models_and_collections(): void
    {
        $media = Media::query()->create($this->mediaAttributes());
        $article1 = TestArticle::query()->create(['title' => 'Article 1']);
        $article2 = TestArticle::query()->create(['title' => 'Article 2']);

        // Attach same media to article 1 (featured)
        $this->attacher->attach($article1, 'featured', $media->uuid);
        // Attach same media to article 1 (gallery)
        $this->attacher->attach($article1, 'gallery', $media->uuid);
        // Attach same media to article 2 (featured)
        $this->attacher->attach($article2, 'featured', $media->uuid);

        $this->assertSame([$media->uuid], $this->attacher->getAttachedMediaUuids($article1, 'featured'));
        $this->assertSame([$media->uuid], $this->attacher->getAttachedMediaUuids($article1, 'gallery'));
        $this->assertSame([$media->uuid], $this->attacher->getAttachedMediaUuids($article2, 'featured'));

        $this->assertSame(3, MediaAttachment::query()->where('media_id', $media->id)->count());
        $this->assertSame(1, Media::query()->count());
    }

    public function test_sync_removes_obsolete_attachments_without_deleting_media_or_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/keep.jpg', 'content-keep');
        Storage::disk('public')->put('media/remove.jpg', 'content-remove');

        $keepMedia = Media::query()->create($this->mediaAttributes(['filename' => 'keep.jpg', 'original_filename' => 'keep.jpg']));
        $removeMedia = Media::query()->create($this->mediaAttributes(['filename' => 'remove.jpg', 'original_filename' => 'remove.jpg']));

        $article = TestArticle::query()->create(['title' => 'Article']);

        $this->attacher->sync($article, 'gallery', [$keepMedia->uuid, $removeMedia->uuid]);
        $this->assertCount(2, MediaAttachment::query()->get());

        // Sync to keep only $keepMedia
        $this->attacher->sync($article, 'gallery', [$keepMedia->uuid]);

        $this->assertSame([$keepMedia->uuid], $this->attacher->getAttachedMediaUuids($article, 'gallery'));
        $this->assertCount(1, MediaAttachment::query()->get());

        // Central Media records must still exist
        $this->assertModelExists($keepMedia);
        $this->assertModelExists($removeMedia);

        // Physical files must NOT be deleted
        $this->assertTrue(app(MediaStorage::class)->exists($keepMedia));
        $this->assertTrue(app(MediaStorage::class)->exists($removeMedia));
    }

    public function test_detach_specific_media_and_detach_all(): void
    {
        $m1 = Media::query()->create($this->mediaAttributes(['filename' => '1.jpg', 'original_filename' => '1.jpg']));
        $m2 = Media::query()->create($this->mediaAttributes(['filename' => '2.jpg', 'original_filename' => '2.jpg']));
        $m3 = Media::query()->create($this->mediaAttributes(['filename' => '3.jpg', 'original_filename' => '3.jpg']));

        $article = TestArticle::query()->create(['title' => 'Article']);
        $this->attacher->sync($article, 'gallery', [$m1->uuid, $m2->uuid, $m3->uuid]);

        // Detach one item
        $this->attacher->detach($article, 'gallery', [$m2->uuid]);
        $this->assertSame([$m1->uuid, $m3->uuid], $this->attacher->getAttachedMediaUuids($article, 'gallery'));

        // Detach all
        $this->attacher->detach($article, 'gallery');
        $this->assertSame([], $this->attacher->getAttachedMediaUuids($article, 'gallery'));
    }

    public function test_polymorphic_id_compatibility_with_uuid_primary_keys(): void
    {
        $media = Media::query()->create($this->mediaAttributes());
        $uuidEntity = TestUuidEntity::query()->create(['name' => 'UUID Item']);

        $this->attacher->attach($uuidEntity, 'avatar', $media->uuid);

        $uuids = $this->attacher->getAttachedMediaUuids($uuidEntity, 'avatar');
        $this->assertSame([$media->uuid], $uuids);

        $attachment = MediaAttachment::query()->where('mediable_id', (string) $uuidEntity->id)->first();
        $this->assertNotNull($attachment);
        $this->assertSame((string) $uuidEntity->id, $attachment->mediable_id);
    }

    public function test_polymorphic_id_compatibility_with_ulid_primary_keys(): void
    {
        $media1 = Media::query()->create($this->mediaAttributes());
        $media2 = Media::query()->create($this->mediaAttributes(['original_filename' => 'ulid-2.jpg']));
        $ulidEntity = TestUlidEntity::query()->create(['name' => 'ULID Item']);

        $this->attacher->attach($ulidEntity, 'documents', [$media1->uuid, $media2->uuid]);

        $uuids = $this->attacher->getAttachedMediaUuids($ulidEntity, 'documents');
        $this->assertSame([$media1->uuid, $media2->uuid], $uuids);

        $attachment = MediaAttachment::query()->where('mediable_id', (string) $ulidEntity->id)->first();
        $this->assertNotNull($attachment);
        $this->assertSame((string) $ulidEntity->id, $attachment->mediable_id);

        // Test sync & detach on ULID entity
        $this->attacher->sync($ulidEntity, 'documents', [$media2->uuid]);
        $this->assertSame([$media2->uuid], $this->attacher->getAttachedMediaUuids($ulidEntity, 'documents'));

        $this->attacher->detach($ulidEntity, 'documents');
        $this->assertSame([], $this->attacher->getAttachedMediaUuids($ulidEntity, 'documents'));
    }

    public function test_rejects_invalid_collection_names(): void
    {
        $article = TestArticle::query()->create(['title' => 'Article']);

        $this->expectException(InvalidArgumentException::class);
        $this->attacher->sync($article, '../traversal', []);
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
