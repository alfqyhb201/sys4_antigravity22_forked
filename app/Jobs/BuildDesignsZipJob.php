<?php

namespace App\Jobs;

use App\Models\ClientTagDistribution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class BuildDesignsZipJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    /**
     * @param  array<int, int>  $recordIds
     */
    public function __construct(
        public array $recordIds,
        public string $token,
        public int $userId,
    ) {}

    public static function cacheKey(string $token): string
    {
        return 'designs_zip:'.$token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function progress(string $token): ?array
    {
        return Cache::get(self::cacheKey($token));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function putProgress(string $token, array $data): void
    {
        Cache::put(self::cacheKey($token), $data, now()->addHour());
    }

    public function handle(): void
    {
        $records = ClientTagDistribution::query()
            ->with(['clientDesigner.client', 'idea'])
            ->whereIn('id', $this->recordIds)
            ->get();

        $total = max($records->count(), 1);
        $relativePath = 'tmp-zips/designs-'.$this->token.'.zip';
        Storage::disk('local')->makeDirectory('tmp-zips');
        $zipPath = Storage::disk('local')->path($relativePath);

        $this->report(0, 'running');

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->report(0, 'failed', null, 'تعذر إنشاء ملف الأرشيف.');

            return;
        }

        $added = 0;
        $processed = 0;
        $publicDisk = Storage::disk('public');

        foreach ($records as $record) {
            $processed++;

            if ($record->attachment_path) {
                $filePath = $publicDisk->path($record->attachment_path);
                if (is_file($filePath)) {
                    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                    $safeClient = Str::slug($record->clientDesigner?->client?->company ?? 'client', '_');
                    $safeIdea = Str::slug(Str::limit($record->idea?->name ?? 'idea', 20), '_');
                    $nameInZip = "{$safeClient}_{$safeIdea}_{$record->id}.{$extension}";

                    $zip->addFile($filePath, $nameInZip);
                    $zip->setCompressionName($nameInZip, ZipArchive::CM_STORE);
                    $added++;
                }
            }

            $this->report((int) floor(($processed / $total) * 90), 'running');
        }

        $zip->registerProgressCallback(0.05, function (float $rate): void {
            $this->report(90 + (int) floor($rate * 10), 'running');
        });
        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            $this->report(100, 'empty', null, 'العناصر المحددة لا تحتوي على ملفات مرفقة متوفرة على الخادم.');

            return;
        }

        $this->report(100, 'done', $relativePath);
    }

    public function failed(Throwable $exception): void
    {
        $this->report(0, 'failed', null, 'حدث خطأ أثناء تجهيز الملف.');
    }

    private function report(int $percent, string $status, ?string $file = null, ?string $message = null): void
    {
        self::putProgress($this->token, [
            'user_id' => $this->userId,
            'percent' => min($percent, 100),
            'status' => $status,
            'file' => $file,
            'message' => $message,
        ]);
    }
}
