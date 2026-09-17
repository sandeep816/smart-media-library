<?php

namespace FilamentMediaLibrary\Tests\Feature\Database;

use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Models\MediaAttachment;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

class MediaAttachmentMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_media_attachments_table_contains_the_required_columns_and_indexes(): void
    {
        $tableName = (new MediaAttachment)->getTable();

        $this->assertTrue(Schema::hasColumns($tableName, [
            'id',
            'media_id',
            'mediable_type',
            'mediable_id',
            'collection',
            'position',
            'metadata',
            'created_at',
            'updated_at',
        ]));

        $indexes = collect(Schema::getIndexes($tableName));

        $lookupIndex = $indexes->first(fn (array $index): bool => $index['columns'] === ['mediable_type', 'mediable_id', 'collection', 'position']);
        $this->assertNotNull($lookupIndex, 'Expected a compound lookup index.');

        $uniqueIndex = $indexes->first(fn (array $index): bool => $index['columns'] === ['media_id', 'mediable_type', 'mediable_id', 'collection']);
        $this->assertNotNull($uniqueIndex, 'Expected a compound unique index.');
        $this->assertTrue($uniqueIndex['unique']);
    }

    public function test_migration_and_model_respect_the_configured_table_name(): void
    {
        MediaAttachment::count();

        config()->set('filament-media-library.table_names.attachments', 'custom_media_attachments');

        /** @var Migration $migration */
        $migration = require __DIR__.'/../../../database/migrations/2026_09_16_000001_create_media_attachments_table.php';

        try {
            $migration->up();

            $media = Media::query()->create([
                'filename' => 'attachment-test.txt',
                'original_filename' => 'attachment-test.txt',
                'mime_type' => 'text/plain',
                'extension' => 'txt',
                'size' => 10,
            ]);

            $attachment = MediaAttachment::query()->create([
                'media_id' => $media->getKey(),
                'mediable_type' => 'App\Models\Post',
                'mediable_id' => '42',
                'collection' => 'featured',
                'position' => 0,
            ]);

            $this->assertSame('custom_media_attachments', $attachment->getTable());
            $this->assertModelExists($attachment);
        } finally {
            $migration->down();
            config()->set('filament-media-library.table_names.attachments', 'media_attachments');
        }
    }
}
