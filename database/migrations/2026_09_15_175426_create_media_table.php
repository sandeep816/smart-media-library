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
            $table->uuid('uuid')->unique();
            $table->string('disk', 100)->index();
            $table->string('directory')->nullable();
            $table->string('filename');
            $table->string('original_filename');
            $table->string('mime_type')->index();
            $table->string('extension', 32)->nullable()->index();
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->longText('description')->nullable();
            $table->json('metadata')->nullable();
            $table->string('uploaded_by')->nullable()->index();
            $table->timestamps();
            $table->index('created_at');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tableName());
    }

    private function tableName(): string
    {
        $tableName = config('filament-media-library.table_names.media', 'media');

        if (! is_string($tableName) || trim($tableName) === '') {
            throw new InvalidArgumentException('The configured media table name must be a non-empty string.');
        }

        return $tableName;
    }
};
