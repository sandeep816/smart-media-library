<?php

namespace FilamentMediaLibrary\Commands;

use FilamentMediaLibrary\Contracts\MediaConversionManager;
use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Support\MediaType;
use Illuminate\Console\Command;
use Throwable;

class RegenerateMediaConversionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media-library:regenerate
                            {--preset= : Specific conversion preset to regenerate}
                            {--force : Force regeneration even if conversion already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate image conversions for stored media items';

    public function handle(MediaConversionManager $conversionManager): int
    {
        $preset = $this->option('preset');
        $force = (bool) $this->option('force');

        $this->info('Starting media conversions regeneration...');

        $query = Media::query()
            ->where('mime_type', 'like', 'image/%');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No image media items found.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        $failed = 0;

        $query->chunk(100, function ($mediaItems) use ($conversionManager, $preset, $force, $bar, &$processed, &$failed): void {
            /** @var Media $media */
            foreach ($mediaItems as $media) {
                if ($media->type !== MediaType::Image) {
                    $bar->advance();

                    continue;
                }

                try {
                    $conversionManager->generate($media, $preset, $force);
                    $processed++;
                } catch (Throwable $exception) {
                    $failed++;
                    $this->newLine();
                    $this->warn("Failed processing Media ID {$media->getKey()} ({$media->uuid}): {$exception->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Completed. Successfully processed: {$processed} media items. Failed: {$failed}.");

        return self::SUCCESS;
    }
}
