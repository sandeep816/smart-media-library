<?php

namespace FilamentMediaLibrary\Tests\Feature\Plugin;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelRegistry;
use FilamentMediaLibrary\Filament\Pages\MediaLibrary;
use FilamentMediaLibrary\MediaLibraryPlugin;
use FilamentMediaLibrary\Support\MediaAuthorization;
use FilamentMediaLibrary\Tests\Fixtures\User;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class MediaLibraryPluginConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        MediaAuthorization::reset();
        parent::tearDown();
    }

    public function test_plugin_fluent_setters_and_getters(): void
    {
        $plugin = MediaLibraryPlugin::make()
            ->navigationLabel('Digital Assets')
            ->navigationIcon('heroicon-o-archive-box')
            ->navigationGroup('CMS')
            ->navigationSort(42)
            ->shouldRegisterNavigation(false)
            ->title('Asset Manager')
            ->subheading('Browse corporate media.');

        $this->assertSame('Digital Assets', $plugin->getNavigationLabel());
        $this->assertSame('heroicon-o-archive-box', $plugin->getNavigationIcon());
        $this->assertSame('CMS', $plugin->getNavigationGroup());
        $this->assertSame(42, $plugin->getNavigationSort());
        $this->assertFalse($plugin->getShouldRegisterNavigation());
        $this->assertSame('Asset Manager', $plugin->getTitle());
        $this->assertSame('Browse corporate media.', $plugin->getSubheading());
    }

    public function test_page_reads_fluent_plugin_configuration_when_registered_on_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $customPlugin = MediaLibraryPlugin::make()
            ->navigationLabel('Custom Media Label')
            ->title('Custom Media Title')
            ->subheading('Custom Subheading')
            ->navigationGroup('Custom Group')
            ->navigationSort(99);

        $panel->plugin($customPlugin);
        Filament::setCurrentPanel($panel);

        $this->assertSame('Custom Media Label', MediaLibrary::getNavigationLabel());
        $this->assertSame('Custom Group', MediaLibrary::getNavigationGroup());
        $this->assertSame(99, MediaLibrary::getNavigationSort());

        $page = new MediaLibrary;
        $this->assertSame('Custom Media Title', $page->getTitle());
        $this->assertSame('Custom Subheading', $page->getSubheading());
    }

    public function test_plugin_and_page_function_on_alternate_panel_without_admin_prefix(): void
    {
        // Register an isolated 'cms' panel at path 'cms'
        $cmsPanel = Panel::make()
            ->id('cms')
            ->path('cms')
            ->default(false)
            ->plugin(MediaLibraryPlugin::make()->title('CMS Media'));

        app(PanelRegistry::class)->register($cmsPanel);
        Filament::setCurrentPanel($cmsPanel);

        // Verify route name generated for this panel does not use 'admin'
        $routeName = MediaLibrary::getRouteName($cmsPanel);
        $this->assertSame('filament.cms.pages.media-library', $routeName);
        $this->assertStringNotContainsString('admin', $routeName);

        // Verify plugin title resolution on this panel
        $page = new MediaLibrary;
        $this->assertSame('CMS Media', $page->getTitle());

        // Verify livewire page test functions inside this panel
        Livewire::actingAs($this->user)
            ->test(MediaLibrary::class)
            ->assertSuccessful()
            ->assertSee('CMS Media');
    }
}
