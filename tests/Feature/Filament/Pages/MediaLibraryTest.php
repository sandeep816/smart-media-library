<?php

namespace FilamentMediaLibrary\Tests\Feature\Filament\Pages;

use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Support\Enums\Width;
use FilamentMediaLibrary\Filament\Pages\MediaLibrary;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Tests\Fixtures\User;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

class MediaLibraryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->fakeDisk('media-test', public: true);
        config()->set('filament-media-library.default_disk', 'media-test');
    }

    public function test_authenticated_user_can_access_the_media_library_page(): void
    {
        config()->set('app.env', 'local');

        $this->actingAs(User::factory()->create())
            ->get(route('filament.admin.pages.media-library'))
            ->assertOk()
            ->assertSeeText('Media Library')
            ->assertSeeText('Manage and reuse your files from one central library.')
            ->assertSeeText('Upload Media')
            ->assertSee('css/filament-media-library/media-library.css', escape: false);
    }

    public function test_collection_tabs_render_integrated_counts_and_layout_controls(): void
    {
        $this->actingAs(User::factory()->create());
        $this->createMedia(['title' => 'Active media']);
        $trashed = $this->createMedia(['title' => 'Trashed media']);
        $trashed->delete();

        Livewire::test(MediaLibrary::class)
            ->assertSee('aria-label="Library, 1 item"', escape: false)
            ->assertSee('aria-label="Trash, 1 item"', escape: false)
            ->assertSee('Grid view')
            ->assertSee('List view')
            ->assertSet('layoutMode', 'grid')
            ->set('layoutMode', 'list')
            ->assertSet('layoutMode', 'list')
            ->assertSee('Active media');
    }

    public function test_grid_renders_public_image_and_handles_private_and_missing_files(): void
    {
        $this->actingAs(User::factory()->create());
        $public = $this->createMedia(['title' => 'Public portrait', 'mime_type' => 'image/jpeg']);
        Storage::disk('media-test')->put($public->path, 'image');
        $this->fakeDisk('private-media');
        $private = $this->createMedia(['disk' => 'private-media', 'title' => 'Private recording', 'mime_type' => 'audio/mpeg']);
        Storage::disk('private-media')->put($private->path, 'audio');
        $this->createMedia(['title' => 'Missing original', 'mime_type' => 'application/pdf']);

        Livewire::test(MediaLibrary::class)
            ->assertSee('Public portrait')
            ->assertSee("https://media.test/media-test/{$public->path}", escape: false)
            ->assertSee('Private recording')
            ->assertSee('Private file')
            ->assertSee('Missing original')
            ->assertSee('File unavailable');
    }

    public function test_media_cards_expose_compact_actions_and_open_details(): void
    {
        $this->actingAs(User::factory()->create());
        $media = $this->createMedia(['title' => 'A very long descriptive media title for card truncation']);
        Storage::disk('media-test')->put($media->path, 'image');

        Livewire::test(MediaLibrary::class)
            ->assertActionExists(
                TestAction::make('details')->arguments(['media' => $media->getKey()]),
                checkActionUsing: fn (Action $action): bool => $action->getModalWidth() === Width::SevenExtraLarge
                    && ! $action->isModalSlideOver()
                    && $action->isModalHeaderSticky()
                    && $action->isModalFooterSticky()
                    && $action->getModalSubmitActionLabel() === 'Save changes',
            )
            ->assertSee('A very long descriptive media title for card truncation')
            ->assertSee('Media actions')
            ->assertSee('View details')
            ->assertSee('Copy URL')
            ->assertSee('Move to trash')
            ->mountAction('details', ['media' => $media->getKey()])
            ->assertActionMounted('details');
    }

    public function test_list_view_renders_aligned_media_information_and_actions(): void
    {
        $this->actingAs(User::factory()->create());
        $media = $this->createMedia([
            'title' => 'List row media',
            'mime_type' => 'application/pdf',
            'size' => 4096,
            'created_at' => '2026-09-14 10:00:00',
        ]);

        Livewire::test(MediaLibrary::class)
            ->set('layoutMode', 'list')
            ->assertSee('List row media')
            ->assertSee('PDF')
            ->assertSee('4.0 KB')
            ->assertSee('Sep 14, 2026')
            ->assertSee('Media actions')
            ->assertSee('Move to trash')
            ->mountAction('details', ['media' => $media->getKey()])
            ->assertActionMounted('details');
    }

    public function test_search_matches_filename_and_title_and_excludes_irrelevant_media(): void
    {
        $this->actingAs(User::factory()->create());
        $this->createMedia(['original_filename' => 'mountain-sunrise.jpg', 'title' => 'Alpine']);
        $this->createMedia(['original_filename' => 'portrait.jpg', 'title' => 'Temple Detail']);
        $this->createMedia(['original_filename' => 'unrelated.jpg', 'title' => 'Ocean']);

        Livewire::test(MediaLibrary::class)
            ->set('search', 'mountain')
            ->assertSee('Alpine')
            ->assertDontSee('Temple Detail')
            ->set('search', 'Temple')
            ->assertSee('Temple Detail')
            ->assertDontSee('Ocean');
    }

    #[DataProvider('typeFilterProvider')]
    public function test_type_filters_are_applied_in_the_media_query(string $filter, string $mimeType): void
    {
        $this->actingAs(User::factory()->create());
        $expected = $this->createMedia(['title' => "Expected {$filter}", 'mime_type' => $mimeType]);
        $excludedMimeType = $filter === 'images' ? 'audio/mpeg' : 'image/jpeg';
        $this->createMedia(['title' => "Excluded {$filter}", 'mime_type' => $excludedMimeType]);

        Livewire::test(MediaLibrary::class)
            ->set('typeFilter', $filter)
            ->assertSee($expected->title)
            ->assertDontSee("Excluded {$filter}");
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function typeFilterProvider(): array
    {
        return [
            'images' => ['images', 'image/jpeg'],
            'videos' => ['videos', 'video/mp4'],
            'audio' => ['audio', 'audio/mpeg'],
            'documents' => ['documents', 'application/pdf'],
            'other' => ['other', 'application/octet-stream'],
        ];
    }

    public function test_date_filters_include_only_the_expected_month(): void
    {
        $this->actingAs(User::factory()->create());
        $this->travelTo('2026-09-16 12:00:00');
        $this->createMedia(['title' => 'Current record', 'created_at' => '2026-09-03 10:00:00']);
        $this->createMedia(['title' => 'Previous record', 'created_at' => '2026-08-20 10:00:00']);

        Livewire::test(MediaLibrary::class)
            ->set('dateFilter', 'this_month')
            ->assertSee('Current record')
            ->assertDontSee('Previous record')
            ->set('dateFilter', 'last_month')
            ->assertSee('Previous record')
            ->assertDontSee('Current record');
    }

    public function test_sorting_supports_date_and_name_directions(): void
    {
        $this->actingAs(User::factory()->create());
        $this->createMedia(['title' => 'Bravo', 'created_at' => '2026-09-01 10:00:00']);
        $this->createMedia(['title' => 'Alpha', 'created_at' => '2026-09-02 10:00:00']);

        Livewire::test(MediaLibrary::class)
            ->assertSeeInOrder(['Alpha', 'Bravo'])
            ->set('sort', 'oldest')->assertSeeInOrder(['Bravo', 'Alpha'])
            ->set('sort', 'name_asc')->assertSeeInOrder(['Alpha', 'Bravo'])
            ->set('sort', 'name_desc')->assertSeeInOrder(['Bravo', 'Alpha']);
    }

    public function test_media_are_paginated_on_the_server(): void
    {
        $this->actingAs(User::factory()->create());
        config()->set('filament-media-library.pagination.per_page', 2);
        $this->createMedia(['title' => 'First', 'created_at' => '2026-09-03 10:00:00']);
        $this->createMedia(['title' => 'Second', 'created_at' => '2026-09-02 10:00:00']);
        $this->createMedia(['title' => 'Third', 'created_at' => '2026-09-01 10:00:00']);

        Livewire::test(MediaLibrary::class)
            ->assertSee('First')->assertDontSee('Third')
            ->call('setPage', 2)
            ->assertSee('Third')->assertDontSee('First');
    }

    public function test_upload_action_uses_real_uploader_and_persists_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(MediaLibrary::class)
            ->callAction('upload', [
                'file' => UploadedFile::fake()->image('new-photo.jpg', 40, 30),
            ])
            ->assertNotified('Media uploaded successfully.')
            ->assertSee('new-photo.jpg');

        $media = Media::query()->sole();
        $this->assertSame((string) $user->getAuthIdentifier(), $media->uploaded_by);
        $this->assertSame('new-photo.jpg', $media->original_filename);
        $this->assertNull($media->title);
        $this->assertNull($media->alt_text);
        $this->assertNull($media->caption);
        $this->assertNull($media->description);
        Storage::disk('media-test')->assertExists($media->path);
    }

    public function test_upload_action_preserves_the_temporary_file_for_the_uploader(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(MediaLibrary::class)
            ->mountAction('upload')
            ->assertSchemaComponentExists(
                'file',
                checkComponentUsing: fn (FileUpload $component): bool => ! $component->shouldStoreFiles(),
            );
    }

    public function test_upload_modal_uses_media_focused_copy_and_primary_action_label(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(MediaLibrary::class)
            ->assertActionExists(
                'upload',
                checkActionUsing: fn (Action $action): bool => $action->getModalSubmitActionLabel() === 'Upload Media'
                    && $action->getModalWidth() === Width::TwoExtraLarge,
            )
            ->mountAction('upload')
            ->assertActionMounted('upload')
            ->assertSchemaComponentExists(
                'file',
                checkComponentUsing: fn (FileUpload $component): bool => $component->getPlaceholder() === 'Drop your file here or Browse',
            )
            ->assertSchemaComponentDoesNotExist('title')
            ->assertSchemaComponentDoesNotExist('alt_text')
            ->assertSchemaComponentDoesNotExist('caption')
            ->assertSchemaComponentDoesNotExist('description');
    }

    public function test_invalid_upload_is_reported_without_a_record_or_orphan(): void
    {
        $this->actingAs(User::factory()->create());
        config()->set('filament-media-library.upload.max_size_kb', 1);

        Livewire::test(MediaLibrary::class)
            ->callAction('upload', ['file' => UploadedFile::fake()->image('too-large.jpg')->size(2)])
            ->assertHasFormErrors(['file']);

        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('media-test')->allFiles());
    }

    public function test_metadata_edit_changes_only_human_metadata(): void
    {
        $this->actingAs(User::factory()->create());
        $media = $this->createMedia(['title' => 'Before']);
        Storage::disk('media-test')->put($media->path, 'original contents');
        $technical = $media->only(['uuid', 'disk', 'directory', 'filename', 'original_filename', 'mime_type', 'extension', 'size', 'width', 'height']);

        Livewire::test(MediaLibrary::class)
            ->callAction(TestAction::make('details')->arguments(['media' => $media->getKey()]), [
                'title' => 'After', 'alt_text' => 'Accessible description',
                'caption' => 'New caption', 'description' => 'New description',
            ])
            ->assertNotified('Media details updated.')
            ->assertSee('After');

        $media->refresh();
        $this->assertSame('After', $media->title);
        $this->assertSame('Accessible description', $media->alt_text);
        $this->assertSame('New caption', $media->caption);
        $this->assertSame('New description', $media->description);
        $this->assertSame($technical, $media->only(array_keys($technical)));
        $this->assertSame('original contents', Storage::disk('media-test')->get($media->path));
    }

    public function test_soft_delete_trash_and_restore_leave_the_file_untouched(): void
    {
        $this->actingAs(User::factory()->create());
        $media = $this->createMedia(['title' => 'Restorable']);
        Storage::disk('media-test')->put($media->path, 'keep me');
        $component = Livewire::test(MediaLibrary::class)
            ->callAction(TestAction::make('delete')->arguments(['media' => $media->getKey()]))
            ->assertNotified('Media moved to trash.')
            ->assertDontSee('Restorable');

        $this->assertSoftDeleted($media);
        Storage::disk('media-test')->assertExists($media->path);

        $component->set('collectionView', 'trash')
            ->assertSee('Restorable')
            ->callAction(TestAction::make('restore')->arguments(['media' => $media->getKey()]))
            ->assertNotified('Media restored.')
            ->assertDontSee('Restorable');

        $this->assertNotSoftDeleted($media);
        Storage::disk('media-test')->assertExists($media->path);
    }

    /** @param array<string, mixed> $attributes */
    private function createMedia(array $attributes = []): Media
    {
        static $sequence = 0;
        $sequence++;

        $createdAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);

        $media = Media::query()->create([
            'disk' => 'media-test', 'directory' => 'media/2026/09',
            'filename' => "file-{$sequence}.jpg", 'original_filename' => "original-{$sequence}.jpg",
            'mime_type' => 'image/jpeg', 'extension' => 'jpg', 'size' => 2048,
            'width' => 100, 'height' => 80, ...$attributes,
        ]);

        if (is_string($createdAt)) {
            $media->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $media;
    }

    private function fakeDisk(string $name, bool $public = false): void
    {
        config()->set("filesystems.disks.{$name}", array_filter([
            'driver' => 'local', 'root' => storage_path("framework/testing/disks/{$name}"),
            'url' => $public ? "https://media.test/{$name}" : null,
            'visibility' => $public ? 'public' : null,
        ]));

        Storage::fake($name, array_filter([
            'url' => $public ? "https://media.test/{$name}" : null,
            'visibility' => $public ? 'public' : null,
        ]));
    }
}
