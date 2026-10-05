<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PDO;
use ZipArchive;

class NativeDatabaseDumperService
{
    /**
     * إنشاء نسخة احتياطية لقاعدة البيانات عبر محرك PHP PDO المباشر.
     *
     * @param  string|array<string>  $disks
     * @return array{success: bool, message: string, filename: string, path: string, size: int, saved_disks: array<string>}
     */
    public function dumpDatabaseToDisk(string|array $disks = ['local'], ?string $customName = null): array
    {
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '512M');

        $appName = config('backup.backup.name', config('app.name', 'TrueERP'));
        $timestamp = Carbon::now()->format('Y-m-d-H-i-s');
        $zipFilename = ($customName ?: "{$appName}-db-{$timestamp}").'.zip';
        $tempDir = storage_path('app/backup-temp');

        if (! File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $sqlFilePath = "{$tempDir}/database.sql";
        $zipFilePath = "{$tempDir}/{$zipFilename}";

        try {
            // 1. إنشاء ملف SQL
            $this->generateSqlDumpFile($sqlFilePath);

            // 2. ضغط الملف داخل ZipArchive
            $zip = new ZipArchive;
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('تعذر إنشاء ملف الأرشيف المضغوط (ZipArchive).');
            }

            $zip->addFile($sqlFilePath, 'db-dumps/mysql-database.sql');
            $zip->close();

            // 3. نقل ملف الـ Zip إلى الأقراص المحددة
            $relativeStoragePath = "{$appName}/{$zipFilename}";
            $zipContents = file_get_contents($zipFilePath);
            $destinationDisks = is_array($disks) ? $disks : [$disks];
            $savedDisks = [];

            foreach ($destinationDisks as $d) {
                try {
                    Storage::disk($d)->put($relativeStoragePath, $zipContents);
                    $savedDisks[] = strtoupper($d);
                } catch (\Throwable $diskErr) {
                    \Illuminate\Support\Facades\Log::warning("تعذر حفظ النسخة الاحتياطية على القرص {$d}: ".$diskErr->getMessage());
                }
            }

            $fileSize = filesize($zipFilePath);

            // 4. تنظيف الملفات المؤقتة
            @unlink($sqlFilePath);
            @unlink($zipFilePath);

            $disksText = ! empty($savedDisks) ? implode(' و ', $savedDisks) : 'القرص الافتراضي';

            return [
                'success' => ! empty($savedDisks),
                'message' => "تم إنشاء نسخة احتياطية لقاعدة البيانات بنجاح وحفظها على ({$disksText}) ✅",
                'filename' => $zipFilename,
                'path' => $relativeStoragePath,
                'size' => $fileSize,
                'saved_disks' => $savedDisks,
            ];
        } catch (\Throwable $e) {
            if (file_exists($sqlFilePath)) {
                @unlink($sqlFilePath);
            }
            if (file_exists($zipFilePath)) {
                @unlink($zipFilePath);
            }

            return [
                'success' => false,
                'message' => 'فشلت عملية إنشاء النسخة: '.$e->getMessage(),
                'filename' => '',
                'path' => '',
                'size' => 0,
                'saved_disks' => [],
            ];
        }
    }

    /**
     * إنشاء نسخة احتياطية كاملة (الملفات وقاعدة البيانات).
     *
     * @param  string|array<string>  $disks
     * @return array{success: bool, message: string, filename: string, path: string, size: int, saved_disks: array<string>}
     */
    public function dumpFullBackupToDisk(string|array $disks = ['local']): array
    {
        @ini_set('max_execution_time', '600');
        @ini_set('memory_limit', '1024M');

        $appName = config('backup.backup.name', config('app.name', 'TrueERP'));
        $timestamp = Carbon::now()->format('Y-m-d-H-i-s');
        $zipFilename = "{$appName}-full-{$timestamp}.zip";
        $tempDir = storage_path('app/backup-temp');

        if (! File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $sqlFilePath = "{$tempDir}/database.sql";
        $zipFilePath = "{$tempDir}/{$zipFilename}";

        try {
            // 1. تصدير قاعدة البيانات
            $this->generateSqlDumpFile($sqlFilePath);

            // 2. ضغط قاعدة البيانات وملفات المشروع الأساسية
            $zip = new ZipArchive;
            if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('تعذر فتح ملف الـ Zip.');
            }

            // إضافة الـ SQL
            $zip->addFile($sqlFilePath, 'db-dumps/mysql-database.sql');

            // إضافة ملفات التطبيق (مع استثناء vendor, node_modules, storage/framework)
            $this->addDirectoryToZip(base_path('app'), $zip, 'app');
            $this->addDirectoryToZip(base_path('config'), $zip, 'config');
            $this->addDirectoryToZip(base_path('database'), $zip, 'database');
            $this->addDirectoryToZip(base_path('resources'), $zip, 'resources');
            $this->addDirectoryToZip(base_path('routes'), $zip, 'routes');
            $this->addDirectoryToZip(base_path('public'), $zip, 'public', ['storage', 'hot']);
            $this->addDirectoryToZip(storage_path('app/public'), $zip, 'storage/app/public');

            $rootFiles = [
                '.env.example',
                'composer.json',
                'composer.lock',
                'package.json',
                'vite.config.js',
                'artisan',
            ];

            foreach ($rootFiles as $rf) {
                $filePath = base_path($rf);
                if (file_exists($filePath) && is_readable($filePath)) {
                    $zip->addFile($filePath, $rf);
                }
            }

            $zip->close();

            // 3. نقل ملف الـ Zip إلى الأقراص المحددة
            $relativeStoragePath = "{$appName}/{$zipFilename}";
            $zipContents = file_get_contents($zipFilePath);
            $destinationDisks = is_array($disks) ? $disks : [$disks];
            $savedDisks = [];

            foreach ($destinationDisks as $d) {
                try {
                    Storage::disk($d)->put($relativeStoragePath, $zipContents);
                    $savedDisks[] = strtoupper($d);
                } catch (\Throwable $diskErr) {
                    \Illuminate\Support\Facades\Log::warning("تعذر حفظ النسخة الشاملة على القرص {$d}: ".$diskErr->getMessage());
                }
            }

            $fileSize = filesize($zipFilePath);

            // 4. تنظيف
            @unlink($sqlFilePath);
            @unlink($zipFilePath);

            $disksText = ! empty($savedDisks) ? implode(' و ', $savedDisks) : 'القرص الافتراضي';

            return [
                'success' => ! empty($savedDisks),
                'message' => "تم إنشاء نسخة احتياطية شاملة للنظام بنجاح وحفظها على ({$disksText}) 📦",
                'filename' => $zipFilename,
                'path' => $relativeStoragePath,
                'size' => $fileSize,
                'saved_disks' => $savedDisks,
            ];
        } catch (\Throwable $e) {
            if (file_exists($sqlFilePath)) {
                @unlink($sqlFilePath);
            }
            if (file_exists($zipFilePath)) {
                @unlink($zipFilePath);
            }

            return [
                'success' => false,
                'message' => 'فشلت عملية النسخ الشامل: '.$e->getMessage(),
                'filename' => '',
                'path' => '',
                'size' => 0,
                'saved_disks' => [],
            ];
        }
    }

    /**
     * توليد ملف SQL متكامل عبر PDO.
     */
    private function generateSqlDumpFile(string $outputPath): void
    {
        $handle = fopen($outputPath, 'w');
        if (! $handle) {
            throw new \RuntimeException('تعذر فتح ملف التفريغ للكتابة.');
        }

        $pdo = DB::connection()->getPdo();
        $dbName = config('database.connections.mysql.database', 'database');

        fwrite($handle, "-- TrueERP Database Backup\n");
        fwrite($handle, '-- Generated: '.Carbon::now()->toDateTimeString()."\n");
        fwrite($handle, "-- Database: {$dbName}\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
        fwrite($handle, "SET NAMES utf8mb4;\n\n");

        $tables = DB::select('SHOW TABLES');

        foreach ($tables as $tableObj) {
            $tableName = current((array) $tableObj);

            // جلب بنية الجدول
            $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
            $propName = 'Create Table';
            $createStatement = $createTable[0]->$propName ?? null;

            if ($createStatement) {
                fwrite($handle, "-- --------------------------------------------------------\n");
                fwrite($handle, "-- Table structure for table `{$tableName}`\n");
                fwrite($handle, "-- --------------------------------------------------------\n\n");
                fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                fwrite($handle, $createStatement.";\n\n");
            }

            // جلب بيانات الجدول على دفعات
            $count = DB::table($tableName)->count();
            if ($count > 0) {
                fwrite($handle, "-- Dumping data for table `{$tableName}`\n");

                DB::table($tableName)->orderBy(DB::raw('1'))->chunk(500, function ($rows) use ($handle, $tableName, $pdo) {
                    $valuesArr = [];
                    foreach ($rows as $row) {
                        $rowValues = [];
                        foreach ((array) $row as $val) {
                            if ($val === null) {
                                $rowValues[] = 'NULL';
                            } elseif (is_numeric($val) && ! is_string($val)) {
                                $rowValues[] = $val;
                            } else {
                                $rowValues[] = $pdo->quote((string) $val);
                            }
                        }
                        $valuesArr[] = '('.implode(', ', $rowValues).')';
                    }

                    if (! empty($valuesArr)) {
                        $insertSql = "INSERT INTO `{$tableName}` VALUES \n".implode(",\n", $valuesArr).";\n";
                        fwrite($handle, $insertSql);
                    }
                });

                fwrite($handle, "\n");
            }
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
    }

    /**
     * إضافة محتويات مجلد إلى ملف Zip بشكل تكراري وآمن.
     *
     * @param  array<string>  $excludePrefixes
     */
    private function addDirectoryToZip(string $directory, ZipArchive $zip, string $localPrefix, array $excludePrefixes = []): void
    {
        if (! File::isDirectory($directory)) {
            return;
        }

        try {
            foreach (File::allFiles($directory) as $file) {
                // استثناء الروابط الرمزية والمجلدات الوهمية لتفادي أخطاء الصلاحيات في ويندوز
                if ($file->isLink() || is_link($file->getPathname())) {
                    continue;
                }

                $relativePathname = str_replace('\\', '/', $file->getRelativePathname());

                foreach ($excludePrefixes as $exclude) {
                    if (str_starts_with($relativePathname, $exclude)) {
                        continue 2;
                    }
                }

                $realPath = $file->getRealPath();
                if ($realPath && is_file($realPath) && is_readable($realPath)) {
                    $zip->addFile($realPath, $localPrefix.'/'.$relativePathname);
                }
            }
        } catch (\Throwable) {
            // تجاهل أي مجلد غير قابل للقراءة بأمان
        }
    }
}
