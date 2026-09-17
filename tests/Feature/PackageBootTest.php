<?php

namespace FilamentMediaLibrary\Tests\Feature;

use Filament\Facades\Filament;
use FilamentMediaLibrary\Contracts\MediaConversionManager;
use FilamentMediaLibrary\Contracts\MediaStorage;
use FilamentMediaLibrary\Contracts\MediaUploader;
use FilamentMediaLibrary\FilamentMediaLibraryServiceProvider;
use FilamentMediaLibrary\MediaLibraryPlugin;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class PackageBootTest extends TestCase
{
    public function test_package_service_provider_is_registered(): void
    {
        $this->assertInstanceOf(
            FilamentMediaLibraryServiceProvider::class,
            $this->app->getProvider(FilamentMediaLibraryServiceProvider::class),
        );
    }

    public function test_package_configuration_is_loaded(): void
    {
        $this->assertNotEmpty(config('filament-media-library'));
        $this->assertSame('media', config('filament-media-library.table_names.media'));
        $this->assertSame('media_attachments', config('filament-media-library.table_names.attachments'));
        $this->assertSame('public', config('filament-media-library.default_disk'));
    }

    public function test_package_contracts_are_bound(): void
    {
        $this->assertTrue($this->app->bound(MediaUploader::class));
        $this->assertTrue($this->app->bound(MediaStorage::class));
        $this->assertTrue($this->app->bound(MediaConversionManager::class));
    }

    public function test_package_migrations_are_available_and_run(): void
    {
        $this->assertTrue(Schema::hasTable('media'));
        $this->assertTrue(Schema::hasTable('media_attachments'));
    }

    public function test_plugin_registration_on_panel_is_accessible(): void
    {
        $panel = Filament::getPanel('admin');
        $this->assertNotNull($panel);
        $this->assertTrue($panel->hasPlugin('filament-media-library'));
        $this->assertInstanceOf(MediaLibraryPlugin::class, $panel->getPlugin('filament-media-library'));
    }
}
