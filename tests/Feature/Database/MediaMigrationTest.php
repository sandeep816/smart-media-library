<?php

namespace FilamentMediaLibrary\Tests\Feature\Database;

use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

class MediaMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_media_table_contains_the_required_columns_and_indexes(): void
    {
        $tableName = (new Media)->getTable();

        $this->assertTrue(Schema::hasColumns($tableName, [
            'id',
            'uuid',
            'disk',
            'directory',
            'filename',
            'original_filename',
            'mime_type',
            'extension',
            'size',
            'width',
            'height',
            'title',
            'alt_text',
            'caption',
            'description',
            'metadata',
            'uploaded_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ]));

        $indexes = collect(Schema::getIndexes($tableName));

        foreach (['uuid', 'disk', 'mime_type', 'extension', 'created_at', 'uploaded_by'] as $column) {
            $this->assertTrue(
                $indexes->contains(fn (array $index): bool => $index['columns'] === [$column]),
                "Expected an index for [{$column}].",
            );
        }

        $uuidIndex = $indexes->first(fn (array $index): bool => $index['columns'] === ['uuid']);
        $this->assertTrue($uuidIndex['unique']);
        $this->assertSame([], Schema::getForeignKeys($tableName));
    }

    public function test_migration_and_model_respect_the_configured_table_name(): void
    {
        Media::count();

        config()->set('filament-media-library.table_names.media', 'custom_media_records');

        /** @var Migration $migration */
        $migration = require __DIR__.'/../../../database/migrations/2026_09_15_175426_create_media_table.php';

        try {
            $migration->up();

            $media = Media::query()->create([
                'filename' => 'configured-table.txt',
                'original_filename' => 'configured-table.txt',
                'mime_type' => 'text/plain',
                'extension' => 'txt',
                'size' => 10,
            ]);

            $this->assertSame('custom_media_records', $media->getTable());
            $this->assertSame('public', $media->disk);
            $this->assertSame('media', $media->directory);
            $this->assertNull($media->metadata);
            $this->assertModelExists($media);
        } finally {
            $migration->down();
            config()->set('filament-media-library.table_names.media', 'media');
        }
    }
}
