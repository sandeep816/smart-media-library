<?php

namespace FilamentMediaLibrary\Tests\Feature\Services;

use FilamentMediaLibrary\Contracts\MediaUploader;
use FilamentMediaLibrary\Events\MediaCreated;
use FilamentMediaLibrary\Exceptions\MediaUploadException;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Services\LaravelMediaUploader;
use FilamentMediaLibrary\Support\MediaType;
use FilamentMediaLibrary\Support\MediaUploadOptions;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;

class LaravelMediaUploaderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_uploader_contract_resolves_to_the_laravel_implementation(): void
    {
        $uploader = $this->app->make(MediaUploader::class);

        $this->assertInstanceOf(LaravelMediaUploader::class, $uploader);
    }

    public function test_valid_image_creates_media_and_stores_the_unchanged_original(): void
    {
        $this->travelTo('2026-09-15 12:00:00');
        $this->fakeDisk('uploads');
        config()->set('filament-media-library.default_disk', 'uploads');
        Event::fake([MediaCreated::class]);
        $file = UploadedFile::fake()->image('Portrait.JPEG', 120, 80);
        $contents = $file->getContent();

        $media = $this->uploader()->upload($file);

        $this->assertModelExists($media);
        $this->assertTrue(Str::isUuid($media->uuid));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.jpg$/', $media->filename);
        $this->assertSame('Portrait.JPEG', $media->original_filename);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame('jpg', $media->extension);
        $this->assertSame(strlen($contents), $media->size);
        $this->assertSame(120, $media->width);
        $this->assertSame(80, $media->height);
        $this->assertSame('media/2026/09', $media->directory);
        $this->assertSame($contents, Storage::disk('uploads')->get($media->path));
        Event::assertDispatched(MediaCreated::class, fn (MediaCreated $event): bool => $event->media->is($media));
    }

    public function test_valid_pdf_creates_document_without_dimensions(): void
    {
        $this->fakeDisk('uploads');
        config()->set('filament-media-library.default_disk', 'uploads');
        $file = UploadedFile::fake()->createWithContent(
            'Guide.PDF',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF",
        );

        $media = $this->uploader()->upload($file);

        $this->assertSame('application/pdf', $media->mime_type);
        $this->assertSame('pdf', $media->extension);
        $this->assertStringEndsWith('.pdf', $media->filename);
        $this->assertSame(MediaType::Document, $media->type);
        $this->assertNull($media->width);
        $this->assertNull($media->height);
        Storage::disk('uploads')->assertExists($media->path);
    }

    #[DataProvider('binaryMediaProvider')]
    public function test_supported_binary_media_uploads_without_external_tools(
        string $originalName,
        string $contents,
        string $mimeType,
        string $extension,
        MediaType $type,
    ): void {
        $this->fakeDisk('uploads');
        config()->set('filament-media-library.default_disk', 'uploads');
        $source = UploadedFile::fake()->createWithContent('source.bin', $contents);
        $file = new UploadedFile($source->getPathname(), $originalName, null, UPLOAD_ERR_OK, true);

        $media = $this->uploader()->upload($file);

        $this->assertSame($mimeType, $media->mime_type);
        $this->assertSame($extension, $media->extension);
        $this->assertSame($type, $media->type);
        $this->assertNull($media->width);
        $this->assertNull($media->height);
        $this->assertSame($contents, Storage::disk('uploads')->get($media->path));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: MediaType}>
     */
    public static function binaryMediaProvider(): array
    {
        $wav = 'RIFF'.pack('V', 36).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8).'data'.pack('V', 0);
        $mp4 = pack('N', 24).'ftypisom'.pack('N', 0).'isomiso2';

        return [
            'WAV audio' => ['sound.bin', $wav, 'audio/x-wav', 'wav', MediaType::Audio],
            'MP4 video' => ['video.bin', $mp4, 'video/mp4', 'mp4', MediaType::Video],
        ];
    }

    public function test_disallowed_server_detected_mime_is_rejected_without_side_effects(): void
    {
        $this->fakeDisk('uploads');
        config()->set('filament-media-library.default_disk', 'uploads');
        Event::fake([MediaCreated::class]);
        $source = UploadedFile::fake()->createWithContent('source.php', '<?php echo "unsafe";');
        $file = new UploadedFile($source->getPathname(), 'photo.jpg', 'image/jpeg', UPLOAD_ERR_OK, true);

        try {
            $this->uploader()->upload($file);
            $this->fail('Expected a media upload exception.');
        } catch (MediaUploadException $exception) {
            $this->assertStringContainsString('not allowed', $exception->getMessage());
        }

        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('uploads')->allFiles());
        Event::assertNotDispatched(MediaCreated::class);
    }

    public function test_oversized_file_is_rejected_without_side_effects(): void
    {
        $this->fakeDisk('uploads');
        config()->set('filament-media-library.default_disk', 'uploads');
        config()->set('filament-media-library.upload.max_size_kb', 1);
        $file = UploadedFile::fake()->image('large.jpg')->size(2);

        try {
            $this->uploader()->upload($file);
            $this->fail('Expected a media upload exception.');
        } catch (MediaUploadException $exception) {
            $this->assertStringContainsString('1 KB', $exception->getMessage());
        }

        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_allowed_mime_without_a_controlled_extension_is_rejected(): void
    {
        $this->fakeDisk('uploads');
        config()->set('filament-media-library.default_disk', 'uploads');
        config()->set('filament-media-library.upload.allowed_mime_types', ['application/json']);
        $source = UploadedFile::fake()->createWithContent('source.bin', '{"valid":true}');
        $file = new UploadedFile($source->getPathname(), 'data.custom', null, UPLOAD_ERR_OK, true);

        $this->expectException(MediaUploadException::class);
        $this->expectExceptionMessage('No safe file extension is configured for MIME type [application/json].');

        try {
            $this->uploader()->upload($file);
        } finally {
            $this->assertDatabaseCount('media', 0);
            $this->assertSame([], Storage::disk('uploads')->allFiles());
        }
    }

    public function test_hostile_original_filename_is_metadata_only_and_cannot_control_the_path(): void
    {
        $this->travelTo('2026-09-15 12:00:00');
        $this->fakeDisk('uploads');
        config()->set('filament-media-library.default_disk', 'uploads');
        $source = UploadedFile::fake()->image('source.jpg');
        $file = new UploadedFile($source->getPathname(), '../../evil.php', null, UPLOAD_ERR_OK, true);

        $media = $this->uploader()->upload($file);

        $this->assertSame('evil.php', $media->original_filename);
        $this->assertMatchesRegularExpression('/^media\/2026\/09\/[0-9a-f-]{36}\.jpg$/', $media->path);
        $this->assertStringNotContainsString('evil', $media->filename);
        $this->assertStringNotContainsString('..', $media->path);
        Storage::disk('uploads')->assertExists($media->path);
    }

    #[DataProvider('unsafeDirectoryProvider')]
    public function test_unsafe_directory_override_is_rejected_without_storage(string $directory): void
    {
        $this->fakeDisk('uploads');
        config()->set('filament-media-library.default_disk', 'uploads');

        try {
            $this->uploader()->upload(
                UploadedFile::fake()->image('photo.jpg'),
                new MediaUploadOptions(directory: $directory),
            );
            $this->fail('Expected a media upload exception.');
        } catch (MediaUploadException $exception) {
            $this->assertStringContainsString('safe relative path', $exception->getMessage());
        }

        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    /** @return array<string, array{0: string}> */
    public static function unsafeDirectoryProvider(): array
    {
        return [
            'parent traversal' => ['../private'],
            'Unix absolute path' => ['/foo'],
            'Windows absolute path' => ['C:\\private'],
            'URL path' => ['https://example.com/files'],
        ];
    }

    public function test_custom_directory_and_disk_override_are_used(): void
    {
        $this->fakeDisk('uploads');
        $this->fakeDisk('private-media');
        config()->set('filament-media-library.default_disk', 'uploads');

        $media = $this->uploader()->upload(
            UploadedFile::fake()->image('photo.png'),
            new MediaUploadOptions(disk: 'private-media', directory: 'tenant/42/originals'),
        );

        $this->assertSame('private-media', $media->disk);
        $this->assertSame('tenant/42/originals', $media->directory);
        Storage::disk('private-media')->assertExists($media->path);
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_invalid_disk_is_a_controlled_failure_without_side_effects(): void
    {
        $this->fakeDisk('uploads');

        try {
            $this->uploader()->upload(
                UploadedFile::fake()->image('photo.jpg'),
                new MediaUploadOptions(disk: 'missing-disk'),
            );
            $this->fail('Expected a media upload exception.');
        } catch (MediaUploadException $exception) {
            $this->assertSame('The selected media storage disk is not configured.', $exception->getMessage());
        }

        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_user_metadata_is_normalized_and_persisted(): void
    {
        $this->fakeDisk('uploads');
        config()->set('filament-media-library.default_disk', 'uploads');

        $media = $this->uploader()->upload(
            UploadedFile::fake()->image('photo.jpg'),
            new MediaUploadOptions(
                title: '  Temple detail  ',
                altText: 'Carved stone entrance',
                caption: '  An archival photograph.  ',
                description: '  Original high-resolution source.  ',
                uploadedBy: '  user-42  ',
                metadata: ['source' => 'archive'],
            ),
        );

        $this->assertSame('Temple detail', $media->title);
        $this->assertSame('Carved stone entrance', $media->alt_text);
        $this->assertSame('An archival photograph.', $media->caption);
        $this->assertSame('Original high-resolution source.', $media->description);
        $this->assertSame('user-42', $media->uploaded_by);
        $this->assertSame('archive', $media->metadata['source'] ?? null);
        $this->assertArrayHasKey('conversions', $media->metadata);
    }

    public function test_oversized_text_metadata_is_rejected_before_storage(): void
    {
        $this->fakeDisk('uploads');
        config()->set('filament-media-library.default_disk', 'uploads');

        $this->expectException(MediaUploadException::class);
        $this->expectExceptionMessage('The media field [title] exceeds its maximum length of 255.');

        try {
            $this->uploader()->upload(
                UploadedFile::fake()->image('photo.jpg'),
                new MediaUploadOptions(title: str_repeat('a', 256)),
            );
        } finally {
            $this->assertDatabaseCount('media', 0);
            $this->assertSame([], Storage::disk('uploads')->allFiles());
        }
    }

    public function test_database_failure_removes_the_file_created_by_the_attempt(): void
    {
        $this->travelTo('2026-09-15 12:00:00');
        $this->fakeDisk('uploads');
        Media::count();
        config()->set('filament-media-library.default_disk', 'uploads');
        config()->set('filament-media-library.table_names.media', 'missing_media_table');
        Event::fake([MediaCreated::class]);
        $collision = '11111111-1111-4111-8111-111111111111';
        $available = '22222222-2222-4222-8222-222222222222';
        $modelUuid = '33333333-3333-4333-8333-333333333333';
        $uuids = [Uuid::fromString($collision), Uuid::fromString($available), Uuid::fromString($modelUuid)];
        Storage::disk('uploads')->put("media/2026/09/{$collision}.jpg", 'pre-existing');
        Str::createUuidsUsing(static function () use (&$uuids) {
            return array_shift($uuids);
        });

        try {
            $this->uploader()->upload(UploadedFile::fake()->image('photo.jpg'));
            $this->fail('Expected a database query exception.');
        } catch (QueryException) {
            $this->assertSame(
                ["media/2026/09/{$collision}.jpg"],
                Storage::disk('uploads')->allFiles(),
            );
            $this->assertSame('pre-existing', Storage::disk('uploads')->get("media/2026/09/{$collision}.jpg"));
        } finally {
            Str::createUuidsNormally();
            config()->set('filament-media-library.table_names.media', 'media');
        }

        $this->assertDatabaseCount('media', 0);
        Event::assertNotDispatched(MediaCreated::class);
    }

    public function test_media_created_event_waits_for_an_open_database_transaction_to_commit(): void
    {
        $this->fakeDisk('uploads');
        config()->set('filament-media-library.default_disk', 'uploads');
        Event::fake([MediaCreated::class]);
        DB::beginTransaction();

        try {
            $media = $this->uploader()->upload(UploadedFile::fake()->image('photo.jpg'));
            Event::assertNotDispatched(MediaCreated::class);

            DB::commit();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        Event::assertDispatched(MediaCreated::class, fn (MediaCreated $event): bool => $event->media->is($media));
    }

    public function test_enclosing_database_rollback_removes_the_stored_file_and_discards_the_event(): void
    {
        $this->fakeDisk('uploads');
        config()->set('filament-media-library.default_disk', 'uploads');
        Event::fake([MediaCreated::class]);
        DB::beginTransaction();

        try {
            $media = $this->uploader()->upload(UploadedFile::fake()->image('photo.jpg'));
            Storage::disk('uploads')->assertExists($media->path);

            DB::rollBack();
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }

        Storage::disk('uploads')->assertMissing($media->path);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Event::assertNotDispatched(MediaCreated::class);
    }

    public function test_storage_failure_does_not_create_a_media_record(): void
    {
        config()->set('filesystems.disks.broken', ['driver' => 'local', 'root' => '/unused']);
        $adapter = Mockery::mock(FilesystemAdapter::class);
        $adapter->shouldReceive('exists')->once()->andReturnFalse();
        $adapter->shouldReceive('putFileAs')->once()->andReturnFalse();
        $manager = Mockery::mock(FilesystemManager::class);
        $manager->shouldReceive('disk')->once()->with('broken')->andReturn($adapter);
        $this->app->instance(FilesystemManager::class, $manager);
        $this->app->forgetInstance(MediaUploader::class);

        $this->expectException(MediaUploadException::class);
        $this->expectExceptionMessage('The uploaded file could not be stored.');

        try {
            $this->uploader()->upload(
                UploadedFile::fake()->image('photo.jpg'),
                new MediaUploadOptions(disk: 'broken'),
            );
        } finally {
            $this->assertDatabaseCount('media', 0);
        }
    }

    public function test_collision_check_preserves_existing_file_and_allocates_a_new_uuid_filename(): void
    {
        $this->travelTo('2026-09-15 12:00:00');
        $this->fakeDisk('uploads');
        config()->set('filament-media-library.default_disk', 'uploads');
        $collision = '11111111-1111-4111-8111-111111111111';
        $available = '22222222-2222-4222-8222-222222222222';
        $modelUuid = '33333333-3333-4333-8333-333333333333';
        $uuids = [Uuid::fromString($collision), Uuid::fromString($available), Uuid::fromString($modelUuid)];
        Storage::disk('uploads')->put("media/2026/09/{$collision}.jpg", 'pre-existing');
        Str::createUuidsUsing(static function () use (&$uuids) {
            return array_shift($uuids);
        });

        try {
            $media = $this->uploader()->upload(UploadedFile::fake()->image('photo.jpg'));
        } finally {
            Str::createUuidsNormally();
        }

        $this->assertSame("{$available}.jpg", $media->filename);
        $this->assertSame('pre-existing', Storage::disk('uploads')->get("media/2026/09/{$collision}.jpg"));
        Storage::disk('uploads')->assertExists($media->path);
    }

    private function uploader(): MediaUploader
    {
        return $this->app->make(MediaUploader::class);
    }

    private function fakeDisk(string $name): void
    {
        config()->set("filesystems.disks.{$name}", [
            'driver' => 'local',
            'root' => storage_path("framework/testing/disks/{$name}"),
        ]);

        Storage::fake($name);
    }
}
