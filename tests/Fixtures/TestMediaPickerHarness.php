<?php

namespace FilamentMediaLibrary\Tests\Fixtures;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use FilamentMediaLibrary\Filament\Forms\Components\MediaPicker;
use Livewire\Component;

class TestMediaPickerHarness extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public ?array $data = [];

    public ?TestArticle $record = null;

    public function mount(?int $articleId = null): void
    {
        $this->record = $articleId
            ? TestArticle::query()->find($articleId)
            : TestArticle::query()->firstOrCreate([], [
                'featured_image_uuid' => null,
                'gallery_media_uuids' => [],
            ]);

        $this->form->fill([
            'featured_image_uuid' => $this->record?->featured_image_uuid,
            'gallery_media_uuids' => $this->record?->gallery_media_uuids ?? [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                MediaPicker::make('featured_image_uuid')
                    ->label('Featured Image')
                    ->image()
                    ->single(),
                MediaPicker::make('gallery_media_uuids')
                    ->label('Gallery')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->maxItems(5),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        if (! $this->record) {
            $this->record = TestArticle::query()->firstOrCreate([]);
        }

        $galleryUuids = is_array($state['gallery_media_uuids'] ?? null)
            ? array_values(array_filter($state['gallery_media_uuids'], fn ($id): bool => is_string($id) && filled($id)))
            : [];

        $this->record->update([
            'featured_image_uuid' => filled($state['featured_image_uuid'] ?? null) ? (string) $state['featured_image_uuid'] : null,
            'gallery_media_uuids' => $galleryUuids,
        ]);

        Notification::make()
            ->title('Media selections saved')
            ->success()
            ->send();
    }

    public function render(): string
    {
        return <<<'HTML'
        <div>
            {{ $this->form }}
        </div>
        HTML;
    }
}
