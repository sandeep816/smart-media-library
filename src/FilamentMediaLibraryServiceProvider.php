<?php

namespace FilamentMediaLibrary;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use FilamentMediaLibrary\Commands\RegenerateMediaConversionsCommand;
use FilamentMediaLibrary\Contracts\ImageProcessor;
use FilamentMediaLibrary\Contracts\MediaAttacher;
use FilamentMediaLibrary\Contracts\MediaConversionManager;
use FilamentMediaLibrary\Contracts\MediaStorage;
use FilamentMediaLibrary\Contracts\MediaUploader;
use FilamentMediaLibrary\Events\MediaCreated;
use FilamentMediaLibrary\Listeners\GenerateMediaConversionsListener;
use FilamentMediaLibrary\Livewire\MediaPickerModal;
use FilamentMediaLibrary\Services\EloquentMediaAttacher;
use FilamentMediaLibrary\Services\GdImageProcessor;
use FilamentMediaLibrary\Services\LaravelMediaConversionManager;
use FilamentMediaLibrary\Services\LaravelMediaStorage;
use FilamentMediaLibrary\Services\LaravelMediaUploader;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class FilamentMediaLibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/filament-media-library.php',
            'filament-media-library',
        );

        $this->app->singleton(MediaStorage::class, LaravelMediaStorage::class);
        $this->app->singleton(MediaUploader::class, LaravelMediaUploader::class);
        $this->app->singleton(MediaAttacher::class, EloquentMediaAttacher::class);
        $this->app->singleton(ImageProcessor::class, GdImageProcessor::class);
        $this->app->singleton(MediaConversionManager::class, LaravelMediaConversionManager::class);
    }

    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('media-library', __DIR__.'/../resources/css/media-library.css'),
        ], 'filament-media-library');

        $this->loadViewsFrom(
            __DIR__.'/../resources/views',
            'filament-media-library',
        );

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Livewire::component('filament-media-library-picker-modal', MediaPickerModal::class);

        Event::listen(MediaCreated::class, GenerateMediaConversionsListener::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                RegenerateMediaConversionsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/filament-media-library.php' => config_path('filament-media-library.php'),
            ], 'filament-media-library-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'filament-media-library-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/filament-media-library'),
            ], 'filament-media-library-views');

            $this->publishes([
                __DIR__.'/../resources/css' => public_path('css/filament-media-library'),
            ], 'filament-media-library-assets');
        }
    }
}
