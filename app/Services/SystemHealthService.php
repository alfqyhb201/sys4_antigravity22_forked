<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SystemHealthService
{
    /**
     * جلب كافة معلومات ومؤشرات السيرفر وبيئة التشغيل.
     *
     * @return array<string, mixed>
     */
    public function getSystemHealthOverview(): array
    {
        return [
            'environment' => $this->getEnvironmentInfo(),
            'database' => $this->getDatabaseInfo(),
            'storage' => $this->getStorageInfo(),
            'drivers' => $this->getDriversInfo(),
            'maintenance' => $this->getMaintenanceInfo(),
        ];
    }

    /**
     * معلومات بيئة التشغيل وإصدارات النظام.
     */
    public function getEnvironmentInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'app_env' => config('app.env', 'production'),
            'app_debug' => config('app.debug', false),
            'app_url' => config('app.url'),
            'timezone' => config('app.timezone', 'Asia/Riyadh'),
            'server_os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI / Local Server',
            'max_execution_time' => ini_get('max_execution_time').'s',
            'memory_limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * معلومات قاعدة البيانات وحجمها الفعلي.
     */
    public function getDatabaseInfo(): array
    {
        $defaultConnection = config('database.default');
        $dbName = config("database.connections.{$defaultConnection}.database");
        $sizeMb = null;
        $tablesCount = 0;

        try {
            if ($defaultConnection === 'mysql' || $defaultConnection === 'mariadb') {
                $result = DB::select(
                    'SELECT 
                        COUNT(table_name) AS tables_count, 
                        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                    FROM information_schema.TABLES 
                    WHERE table_schema = ?',
                    [$dbName]
                );

                if (! empty($result)) {
                    $sizeMb = (float) ($result[0]->size_mb ?? 0);
                    $tablesCount = (int) ($result[0]->tables_count ?? 0);
                }
            } elseif ($defaultConnection === 'sqlite') {
                if (file_exists($dbName)) {
                    $sizeMb = round(filesize($dbName) / 1024 / 1024, 2);
                }
            }
        } catch (\Throwable $e) {
            $sizeMb = null;
        }

        return [
            'driver' => $defaultConnection,
            'database_name' => $dbName,
            'size_mb' => $sizeMb,
            'tables_count' => $tablesCount,
            'formatted_size' => $sizeMb !== null ? "{$sizeMb} MB" : 'غير متاح',
        ];
    }

    /**
     * معلومات وسعات التخزين.
     */
    public function getStorageInfo(): array
    {
        $basePath = base_path();
        $freeSpace = @disk_free_space($basePath);
        $totalSpace = @disk_total_space($basePath);

        $usedSpace = ($totalSpace && $freeSpace) ? ($totalSpace - $freeSpace) : null;
        $usagePercentage = ($totalSpace && $usedSpace) ? round(($usedSpace / $totalSpace) * 100, 1) : null;

        $storageDirSize = $this->getDirectorySize(storage_path());

        return [
            'free_bytes' => $freeSpace,
            'total_bytes' => $totalSpace,
            'used_bytes' => $usedSpace,
            'usage_percentage' => $usagePercentage,
            'formatted_free' => $this->formatBytes($freeSpace),
            'formatted_total' => $this->formatBytes($totalSpace),
            'formatted_used' => $this->formatBytes($usedSpace),
            'storage_dir_size' => $this->formatBytes($storageDirSize),
        ];
    }

    /**
     * معلومات المحركات المعرفة (Cache, Queue, Session).
     */
    public function getDriversInfo(): array
    {
        return [
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_driver' => config('queue.default'),
            'mail_driver' => config('mail.default'),
            'filesystem_driver' => config('filesystems.default'),
        ];
    }

    /**
     * معلومات وضع الصيانة الحالي.
     */
    public function getMaintenanceInfo(): array
    {
        $isDown = app()->isDownForMaintenance();
        $data = [];

        if ($isDown) {
            $downFile = storage_path('framework/down');
            if (file_exists($downFile)) {
                $data = json_decode(file_get_contents($downFile), true) ?: [];
            }
        }

        return [
            'is_down' => $isDown,
            'secret' => $data['secret'] ?? null,
            'retry' => $data['retry'] ?? null,
            'status' => $data['status'] ?? 503,
            'message' => $data['message'] ?? 'النظام يخضع لأعمال الصيانة الدورية حالياً.',
        ];
    }

    /**
     * حساب حجم مجلد بالكامل بأمان.
     */
    private function getDirectorySize(string $path): int
    {
        if (! File::exists($path)) {
            return 0;
        }

        $size = 0;
        try {
            foreach (File::allFiles($path) as $file) {
                $size += $file->getSize();
            }
        } catch (\Throwable) {
            // في حال وجود ملف مقفل أو خطأ في الصلاحيات
        }

        return $size;
    }

    /**
     * تنسيق الحجم بالبايت إلى وحدة مقروءة (GB, MB, KB).
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
