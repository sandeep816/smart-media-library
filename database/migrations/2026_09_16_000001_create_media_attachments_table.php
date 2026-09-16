<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->tableName(), function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')
                ->constrained($this->mediaTableName())
                ->cascadeOnDelete();
            $table->string('mediable_type');
            $table->string('mediable_id', 64);
            $table->string('collection', 64)->default('default');
            $table->unsignedInteger('position')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['mediable_type', 'mediable_id', 'collection', 'position'],
                $this->tableName().'_lookup_idx',
            );
            $table->unique(
                ['media_id', 'mediable_type', 'mediable_id', 'collection'],
                $this->tableName().'_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName());
    }

    private function tableName(): string
    {
        $tableName = config('filament-media-library.table_names.attachments', 'media_attachments');

        if (! is_string($tableName) || trim($tableName) === '') {
            throw new InvalidArgumentException('The configured attachments table name must be a non-empty string.');
        }

        return $tableName;
    }

    private function mediaTableName(): string
    {
        $tableName = config('filament-media-library.table_names.media', 'media');

        if (! is_string($tableName) || trim($tableName) === '') {
            throw new InvalidArgumentException('The configured media table name must be a non-empty string.');
        }

        return $tableName;
    }
};
