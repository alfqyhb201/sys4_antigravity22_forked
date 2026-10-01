<?php

namespace App\Console\Commands;

use App\Models\ClientTemplate;
use App\Services\ImageThumbnailService;
use Illuminate\Console\Command;

class GenerateClientTemplateThumbnails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'templates:generate-thumbnails {--force : Regenerate thumbnails even if they already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate lightweight WebP thumbnails for all client templates';

    /**
     * Execute the console command.
     */
    public function handle(ImageThumbnailService $thumbnailService): int
    {
        $force = (bool) $this->option('force');

        $templates = ClientTemplate::query()
            ->whereNotNull('file')
            ->where('file', '!=', '')
            ->get();

        if ($templates->isEmpty()) {
            $this->info('No client templates found with uploaded files.');

            return self::SUCCESS;
        }

        $this->info("Processing {$templates->count()} client templates...");
        $bar = $this->output->createProgressBar($templates->count());
        $bar->start();

        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($templates as $template) {
            if (! $force && $thumbnailService->thumbnailExists($template->file)) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $result = $thumbnailService->generateThumbnail($template->file);
            if ($result) {
                $generated++;
            } else {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Completed!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Generated / Updated', $generated],
                ['Skipped (Already exists)', $skipped],
                ['Failed / Not Found', $failed],
                ['Total Processed', $templates->count()],
            ]
        );

        return self::SUCCESS;
    }
}
