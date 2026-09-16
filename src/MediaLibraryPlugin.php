<?php

namespace FilamentMediaLibrary;

use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use FilamentMediaLibrary\Filament\Pages\MediaLibrary;
use FilamentMediaLibrary\Support\MediaAuthorization;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;
use UnitEnum;

class MediaLibraryPlugin implements Plugin
{
    protected ?string $navigationLabel = null;

    protected string|BackedEnum|Htmlable|null $navigationIcon = null;

    protected string|UnitEnum|null $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected bool|Closure $shouldRegisterNavigation = true;

    protected ?string $title = null;

    protected string|Htmlable|null $subheading = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(?string $panelId = null): ?static
    {
        try {
            $panel = filled($panelId) ? Filament::getPanel($panelId) : Filament::getCurrentPanel();

            if ($panel && $panel->hasPlugin('filament-media-library')) {
                /** @var static $plugin */
                $plugin = $panel->getPlugin('filament-media-library');

                return $plugin;
            }
        } catch (Throwable) {
            //
        }

        return null;
    }

    public function getId(): string
    {
        return 'filament-media-library';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            MediaLibrary::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function navigationLabel(?string $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): ?string
    {
        return $this->navigationLabel;
    }

    public function navigationIcon(string|BackedEnum|Htmlable|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->navigationIcon;
    }

    public function navigationGroup(string|UnitEnum|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): string|UnitEnum|null
    {
        return $this->navigationGroup;
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    public function shouldRegisterNavigation(bool|Closure $condition = true): static
    {
        $this->shouldRegisterNavigation = $condition;

        return $this;
    }

    public function getShouldRegisterNavigation(): bool
    {
        if ($this->shouldRegisterNavigation instanceof Closure) {
            return (bool) call_user_func($this->shouldRegisterNavigation);
        }

        return (bool) $this->shouldRegisterNavigation;
    }

    public function title(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function subheading(string|Htmlable|null $subheading): static
    {
        $this->subheading = $subheading;

        return $this;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->subheading;
    }

    public function authorizeUsing(string $ability, Closure $callback): static
    {
        MediaAuthorization::authorizeUsing($ability, $callback);

        return $this;
    }

    public function authorize(Closure $callback): static
    {
        MediaAuthorization::authorizeWith($callback);

        return $this;
    }
}
