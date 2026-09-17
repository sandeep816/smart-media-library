<?php

namespace FilamentMediaLibrary\Tests\Feature\Filament\Forms\Components;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use FilamentMediaLibrary\Contracts\MediaAttacher;
use FilamentMediaLibrary\Filament\Forms\Components\MediaPicker;
use FilamentMediaLibrary\Livewire\MediaPickerModal;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Support\MediaType;
use FilamentMediaLibrary\Tests\Fixtures\TestArticle;
use FilamentMediaLibrary\Tests\Fixtures\User;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use LogicException;

class MediaPickerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $user = User::factory()->create();
        $this->actingAs($user);

        DbSchema::create('test_articles', function ($table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->string('featured_image_uuid')->nullable();
            $table->json('gallery_uuids')->nullable();
            $table->timestamps();
        });

        Storage::fake('public');
    }

    public function test_component_instantiation_and_fluent_configuration(): void
    {
        $picker = MediaPicker::make('featured_image')
            ->image()
            ->single()
            ->collection('hero')
            ->allowUpload(true);

        $this->assertFalse($picker->isMultiple());
        $this->assertSame('hero', $picker->getCollection());
        $this->assertSame(['image'], $picker->getAcceptedMediaTypes());
        $this->assertTrue($picker->allowsUpload());

        $multiplePicker = MediaPicker::make('gallery')
            ->multiple()
            ->reorderable()
            ->maxItems(5)
            ->document();

        $this->assertTrue($multiplePicker->isMultiple());
        $this->assertTrue($multiplePicker->isReorderable());
        $this->assertSame(5, $multiplePicker->getMaxItems());
        $this->assertSame(['document'], $multiplePicker->getAcceptedMediaTypes());
    }

    public function test_reorderable_throws_exception_when_evaluated_on_single_mode(): void
    {
        $picker = MediaPicker::make('avatar')
            ->single()
            ->reorderable(true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Reordering can only be enabled when multiple selection is enabled on the MediaPicker.');
        $picker->isReorderable();
    }

    public function test_single_value_mode_hydration_and_dehydration(): void
    {
        $media = $this->createMedia(['filename' => 'single.jpg']);

        $picker = $this->createPicker('featured_image')->single();

        // Hydration
        $picker->state($media->uuid);
        $this->assertSame($media->uuid, $picker->getState());

        // Dehydration
        $dehydrated = $picker->getStateToDehydrate($picker->getState());
        $this->assertSame($media->uuid, $dehydrated[$picker->getStatePath()]);

        // Presentations
        $presentations = $picker->getSelectedMediaPresentations();
        $this->assertCount(1, $presentations);
        $this->assertSame($media->uuid, $presentations[0]['uuid']);
        $this->assertSame('single.jpg', $presentations[0]['display_name']);
        $this->assertSame(MediaType::Image, $presentations[0]['type']);

        // Remove
        $picker->removeMediaItem($media->uuid);
        $this->assertNull($picker->getState());
        $dehydrated = $picker->getStateToDehydrate($picker->getState());
        $this->assertNull($dehydrated[$picker->getStatePath()]);
    }

    public function test_multiple_value_mode_hydration_ordering_and_reordering(): void
    {
        $m1 = $this->createMedia(['filename' => '1.jpg']);
        $m2 = $this->createMedia(['filename' => '2.jpg']);
        $m3 = $this->createMedia(['filename' => '3.jpg']);

        $picker = $this->createPicker('gallery')
            ->multiple()
            ->reorderable();

        // Hydration preserving order
        $picker->state([$m3->uuid, $m1->uuid, $m2->uuid]);
        $this->assertSame([$m3->uuid, $m1->uuid, $m2->uuid], $picker->getState());

        $presentations = $picker->getSelectedMediaPresentations();
        $this->assertCount(3, $presentations);
        $this->assertSame($m3->uuid, $presentations[0]['uuid']);
        $this->assertSame($m1->uuid, $presentations[1]['uuid']);
        $this->assertSame($m2->uuid, $presentations[2]['uuid']);

        // Move $m1 up (swap with $m3)
        $picker->moveItem($m1->uuid, -1);
        $this->assertSame([$m1->uuid, $m3->uuid, $m2->uuid], $picker->getState());

        // Move $m1 down (swap with $m3)
        $picker->moveItem($m1->uuid, 1);
        $this->assertSame([$m3->uuid, $m1->uuid, $m2->uuid], $picker->getState());

        // Remove $m3
        $picker->removeMediaItem($m3->uuid);
        $this->assertSame([$m1->uuid, $m2->uuid], $picker->getState());

        // Dehydration
        $dehydrated = $picker->getStateToDehydrate($picker->getState());
        $this->assertSame([$m1->uuid, $m2->uuid], $dehydrated[$picker->getStatePath()]);
    }

    public function test_type_restrictions_validation(): void
    {
        $image = $this->createMedia(['filename' => 'photo.jpg', 'mime_type' => 'image/jpeg']);
        $doc = $this->createMedia(['filename' => 'report.pdf', 'mime_type' => 'application/pdf']);

        $imagePicker = $this->createPicker('image_field')->image()->single();

        // Image in image picker passes
        $imagePicker->state($image->uuid);
        $rules = $imagePicker->getValidationRules();

        $validator = validator(['image_field' => $image->uuid], ['image_field' => $rules]);
        $this->assertFalse($validator->fails());

        // Document in image picker fails
        $validator = validator(['image_field' => $doc->uuid], ['image_field' => $rules]);
        $this->assertTrue($validator->fails());
    }

    public function test_soft_deleted_and_invalid_uuids_handled_gracefully(): void
    {
        $trashed = $this->createMedia(['filename' => 'trashed.jpg']);
        $trashed->delete();

        $picker = $this->createPicker('image')->single();
        $picker->state($trashed->uuid);

        // Presentations reports trashed without crashing
        $presentations = $picker->getSelectedMediaPresentations();
        $this->assertCount(1, $presentations);
        $this->assertTrue($presentations[0]['is_trashed']);

        // Non-existent UUID does not crash
        $picker->state('non-existent-uuid-0000');
        $this->assertSame([], $picker->getSelectedMediaPresentations());

        // Validation rejects soft-deleted media
        $rules = $picker->getValidationRules();
        $validator = validator(['image' => $trashed->uuid], ['image' => $rules]);
        $this->assertTrue($validator->fails());
    }

    public function test_attachment_mode_saves_relationships(): void
    {
        $m1 = $this->createMedia(['filename' => 'attach1.jpg']);
        $m2 = $this->createMedia(['filename' => 'attach2.jpg']);

        $article = TestArticle::query()->create(['title' => 'Article']);

        $picker = $this->createPicker('featured_media')
            ->relationship('featured')
            ->single();

        $picker->model($article);
        $picker->record($article);

        // Save relationships
        $picker->state($m1->uuid);
        $picker->saveRelationships();

        $this->assertSame([$m1->uuid], app(MediaAttacher::class)->getAttachedMediaUuids($article, 'featured'));

        // Load relationships on edit
        $picker->state(null);
        $picker->loadStateFromRelationships(true);
        $this->assertSame($m1->uuid, $picker->getState());

        // Sync with multiple
        $multiPicker = $this->createPicker('gallery_media')
            ->relationship('gallery')
            ->multiple();

        $multiPicker->model($article);
        $multiPicker->record($article);
        $multiPicker->state([$m1->uuid, $m2->uuid]);
        $multiPicker->saveRelationships();

        $this->assertSame([$m1->uuid, $m2->uuid], app(MediaAttacher::class)->getAttachedMediaUuids($article, 'gallery'));
    }

    public function test_media_picker_modal_component_lifecycle(): void
    {
        $m1 = $this->createMedia(['title' => 'Alpha photo', 'mime_type' => 'image/jpeg']);
        $m2 = $this->createMedia(['title' => 'Beta video', 'mime_type' => 'video/mp4']);
        $m3 = $this->createMedia(['title' => 'Gamma audio', 'mime_type' => 'audio/mpeg']);

        // Test single mode selection and replacement
        Livewire::test(MediaPickerModal::class, [
            'statePath' => 'data.image',
            'isMultiple' => false,
            'acceptedTypes' => ['image'],
            'allowUpload' => true,
            'currentState' => null,
        ])
            ->assertSee('Alpha photo')
            ->assertDontSee('Beta video') // Excluded by acceptedTypes
            ->call('toggleSelect', $m1->uuid)
            ->assertSet('temporarySelection', [$m1->uuid])
            ->call('confirmSelection')
            ->assertDispatched('media-picker-confirmed', statePath: 'data.image', selection: $m1->uuid);

        // Test multiple mode selection and maxItems
        Livewire::test(MediaPickerModal::class, [
            'statePath' => 'data.gallery',
            'isMultiple' => true,
            'maxItems' => 2,
            'acceptedTypes' => [],
            'allowUpload' => true,
            'currentState' => [$m1->uuid],
        ])
            ->assertSet('temporarySelection', [$m1->uuid])
            ->call('toggleSelect', $m2->uuid)
            ->assertSet('temporarySelection', [$m1->uuid, $m2->uuid])
            // 3rd selection should be blocked by maxItems = 2
            ->call('toggleSelect', $m3->uuid)
            ->assertSet('temporarySelection', [$m1->uuid, $m2->uuid])
            ->call('confirmSelection')
            ->assertDispatched('media-picker-confirmed', statePath: 'data.gallery', selection: [$m1->uuid, $m2->uuid]);
    }

    public function test_media_picker_modal_search_and_filters(): void
    {
        $this->createMedia(['title' => 'UniqueSearchableTitle', 'original_filename' => 'file1.jpg']);
        $this->createMedia(['title' => 'OtherDocument', 'original_filename' => 'file2.jpg']);

        Livewire::test(MediaPickerModal::class, [
            'statePath' => 'data.test',
            'isMultiple' => false,
            'acceptedTypes' => [],
            'currentState' => null,
        ])
            ->set('search', 'UniqueSearchable')
            ->assertSee('UniqueSearchableTitle')
            ->assertDontSee('OtherDocument')
            ->call('clearFilters')
            ->assertSee('OtherDocument');
    }

    public function test_media_picker_modal_upload_integration(): void
    {
        $file = UploadedFile::fake()->image('picker-upload.png', 100, 100);

        Livewire::test(MediaPickerModal::class, [
            'statePath' => 'data.image',
            'isMultiple' => false,
            'acceptedTypes' => ['image'],
            'allowUpload' => true,
            'currentState' => null,
        ])
            ->set('uploadedFile', $file)
            ->assertHasNoErrors()
            ->assertSee('picker-upload.png');

        $createdMedia = Media::query()->where('original_filename', 'picker-upload.png')->first();
        $this->assertNotNull($createdMedia);
        $this->assertSame(MediaType::Image, $createdMedia->type);
    }

    public function test_media_picker_modal_upload_rejects_incompatible_type(): void
    {
        // Fake text file for image-only picker
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        Livewire::test(MediaPickerModal::class, [
            'statePath' => 'data.image',
            'isMultiple' => false,
            'acceptedTypes' => ['image'],
            'allowUpload' => true,
            'currentState' => null,
        ])
            ->set('uploadedFile', $file)
            ->assertSet('uploadErrorMessage', 'The uploaded file does not match the accepted media types for this field.');

        $this->assertNull(Media::query()->where('original_filename', 'document.pdf')->first());
    }

    public function test_form_component_renders_in_livewire_form(): void
    {
        $media = $this->createMedia(['title' => 'Selected Photo']);

        Livewire::test(TestMediaPickerForm::class)
            ->assertSee('No media selected')
            ->fillForm(['featured_image' => $media->uuid])
            ->assertSee('Selected Photo');
    }

    private function createPicker(string $name = 'featured_image'): MediaPicker
    {
        $livewire = new TestMediaPickerForm;
        $schema = Schema::make($livewire);
        $picker = MediaPicker::make($name);
        $picker->container($schema);

        return $picker;
    }

    private function createMedia(array $overrides = []): Media
    {
        $filename = $overrides['filename'] ?? 'test.jpg';
        Storage::disk('public')->put('media/'.$filename, 'dummy-content');

        return Media::query()->create(array_merge([
            'filename' => $filename,
            'original_filename' => $filename,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 1024,
            'disk' => 'public',
            'directory' => 'media',
        ], $overrides));
    }
}

class TestMediaPickerForm extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                MediaPicker::make('featured_image')
                    ->image()
                    ->single(),
                MediaPicker::make('gallery')
                    ->multiple()
                    ->reorderable(),
            ])
            ->statePath('data');
    }

    public function render()
    {
        return <<<'HTML'
        <div>
            {{ $this->form }}
        </div>
        HTML;
    }
}
