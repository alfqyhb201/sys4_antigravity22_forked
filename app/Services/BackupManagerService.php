<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupManagerService
{
    /**
     * جلب قائمة كافة ملفات النسخ الاحتياطية المتوفرة عبر الأقراص.
     *
     * @return array<int, array{disk: string, path: string, filename: string, size_bytes: int, size_formatted: string, created_at: int, created_at_formatted: string, age: string}>
     */
    public function getBackups(): array
    {
        $disks = config('backup.backup.destination.disks', ['local']);
        $appName = config('backup.backup.name', config('app.name', 'laravel-backup'));
        $backups = [];

        foreach ($disks as $disk) {
            try {
                $storage = Storage::disk($disk);
                $files = $storage->allFiles($appName);

                // فحص المجلد الرئيسي أيضاً في حال لم يتم وضعها بمجلد باسم التطبيق
                if (empty($files)) {
                    $files = $storage->allFiles();
                }

                foreach ($files as $file) {
                    if (str_ends_with($file, '.zip')) {
                        $size = (int) $storage->size($file);
                        $lastModified = (int) $storage->lastModified($file);

                        $backups[] = [
                            'disk' => $disk,
                            'path' => $file,
                            'filename' => basename($file),
                            'size_bytes' => $size,
                            'size_formatted' => $this->formatBytes($size),
                            'created_at' => $lastModified,
                            'created_at_formatted' => Carbon::createFromTimestamp($lastModified)->timezone(config('app.timezone', 'Asia/Riyadh'))->format('Y-m-d H:i:s'),
                            'age' => Carbon::createFromTimestamp($lastModified)->diffForHumans(),
                        ];
                    }
                }
            } catch (\Throwable) {
                // تجاهل القرص في حال عدم توفره أو تعذر الاتصال
            }
        }

        // ترتيب تنازلي حسب الأحدث
        usort($backups, fn ($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    /**
     * جلب إحصائيات عامة حول النسخ الاحتياطية.
     *
     * @return array{total_count: int, total_size_formatted: string, latest_backup: ?string, latest_age: ?string, active_disks: array<string>}
     */
    public function getStatistics(): array
    {
        $backups = $this->getBackups();
        $totalBytes = array_sum(array_column($backups, 'size_bytes'));
        $latest = $backups[0] ?? null;

        return [
            'total_count' => count($backups),
            'total_size_formatted' => $this->formatBytes($totalBytes),
            'latest_backup' => $latest['created_at_formatted'] ?? 'لا توجد نسخ سابقة',
            'latest_age' => $latest['age'] ?? 'غير متوفر',
            'active_disks' => config('backup.backup.destination.disks', ['local']),
        ];
    }

    /**
     * إنشاء نسخة احتياطية جديدة (قاعدة البيانات فقط أو كاملة) وحفظها على الأقراص المحددة.
     *
     * @param  array<string>|null  $disks
     * @return array{success: bool, message: string, output: string}
     */
    public function createBackup(bool $onlyDb = false, ?array $disks = null): array
    {
        @ini_set('max_execution_time', '300');

        try {
            $dumper = app(NativeDatabaseDumperService::class);
            $targetDisks = ! empty($disks) ? $disks : config('backup.backup.destination.disks', ['local', 'google']);

            if ($onlyDb) {
                $result = $dumper->dumpDatabaseToDisk($targetDisks);
            } else {
                $result = $dumper->dumpFullBackupToDisk($targetDisks);
            }

            $savedDisksStr = ! empty($result['saved_disks']) ? implode(', ', $result['saved_disks']) : 'القرص الافتراضي';

            return [
                'success' => $result['success'],
                'message' => $result['message'],
                'output' => $result['success'] ? "تم إنشاء وحفظ ملف النسخة بنجاح:\n- الملف: {$result['filename']}\n- المسار: {$result['path']}\n- الحجم: ".$this->formatBytes($result['size'])."\n- الأقراص المكتملة: {$savedDisksStr}" : $result['message'],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'خطأ أثناء تشغيل النسخ الاحتياطي: '.$e->getMessage(),
                'output' => $e->getTraceAsString(),
            ];
        }
    }

    /**
     * تنظيف النسخ الاحتياطية القديمة بحسب قواعد الضبط.
     *
     * @return array{success: bool, message: string, output: string}
     */
    public function cleanOldBackups(): array
    {
        try {
            $exitCode = Artisan::call('backup:clean');
            $output = Artisan::output();

            return [
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? 'تم فحص وتنظيف النسخ الاحتياطية القديمة بنجاح.' : 'حدث خطأ أثناء تنظيف النسخ.',
                'output' => $output,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'خطأ في تنظيف النسخ: '.$e->getMessage(),
                'output' => $e->getTraceAsString(),
            ];
        }
    }

    /**
     * تحميل ملف نسخة احتياطية من القرص المحدد.
     */
    public function downloadBackup(string $disk, string $path): StreamedResponse
    {
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            abort(404, 'ملف النسخة الاحتياطية غير موجود على القرص.');
        }

        return $storage->download($path, basename($path));
    }

    /**
     * حذف ملف نسخة احتياطية محددة.
     */
    public function deleteBackup(string $disk, string $path): bool
    {
        try {
            $storage = Storage::disk($disk);
            if ($storage->exists($path)) {
                return $storage->delete($path);
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * تنسيق حجم البايت لوحدة مقروءة.
     */
    public function formatBytes(?int $bytes, int $precision = 2): string
    {
        if ($bytes === null || $bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}
