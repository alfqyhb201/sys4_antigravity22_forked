<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemLogReaderService
{
    /**
     * المسار الأساسي لمجلد السجلات.
     */
    protected string $logPath;

    public function __construct()
    {
        $this->logPath = storage_path('logs');
    }

    /**
     * جلب قائمة كافة ملفات السجلات المتوفرة في المجلد.
     *
     * @return array<int, array{name: string, path: string, size_bytes: int, size_formatted: string, modified_at: int, modified_at_formatted: string}>
     */
    public function getAvailableLogFiles(): array
    {
        if (! File::isDirectory($this->logPath)) {
            return [];
        }

        $files = File::files($this->logPath);
        $logFiles = [];

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // تجاهل الملفات المخفية وغير التابعة للسجلات
            if (str_starts_with($filename, '.') || ! str_ends_with($filename, '.log')) {
                continue;
            }

            $size = $file->getSize();
            $modified = $file->getMTime();

            $logFiles[] = [
                'name' => $filename,
                'path' => $file->getPathname(),
                'size_bytes' => $size,
                'size_formatted' => $this->formatBytes($size),
                'modified_at' => $modified,
                'modified_at_formatted' => Carbon::createFromTimestamp($modified)->timezone(config('app.timezone', 'Asia/Riyadh'))->format('Y-m-d H:i:s'),
            ];
        }

        // الترتيب: جعل ملف laravel.log أولاً، ثم الأحدث تعديلاً
        usort($logFiles, function ($a, $b) {
            if ($a['name'] === 'laravel.log') {
                return -1;
            }
            if ($b['name'] === 'laravel.log') {
                return 1;
            }

            return $b['modified_at'] <=> $a['modified_at'];
        });

        return $logFiles;
    }

    /**
     * قراءة وفلترة وترقيم سجلات الأخطاء من ملف محدد.
     *
     * @return array{
     *     entries: array<int, array<string, mixed>>,
     *     total_matching: int,
     *     total_in_file: int,
     *     current_page: int,
     *     per_page: int,
     *     last_page: int,
     *     stats: array<string, mixed>
     * }
     */
    public function getLogs(string $filename = 'laravel.log', ?string $level = null, ?string $search = null, int $page = 1, int $perPage = 25): array
    {
        $filename = basename($filename);
        $filePath = $this->logPath.DIRECTORY_SEPARATOR.$filename;

        if (! File::exists($filePath) || ! is_readable($filePath)) {
            return [
                'entries' => [],
                'total_matching' => 0,
                'total_in_file' => 0,
                'current_page' => 1,
                'per_page' => $perPage,
                'last_page' => 1,
                'stats' => [
                    'total' => 0,
                    'error_count' => 0,
                    'warning_count' => 0,
                    'info_count' => 0,
                    'debug_count' => 0,
                    'file_size' => '0 B',
                    'last_modified' => '—',
                ],
            ];
        }

        $allEntries = $this->parseLogFile($filePath);
        $stats = $this->calculateStats($allEntries, $filePath);

        // تطبيق فلتر المستوى
        $filtered = $allEntries;
        if (! empty($level) && $level !== 'all') {
            $filtered = array_filter($filtered, function ($entry) use ($level) {
                $entryLevel = strtoupper($entry['level']);

                return match (strtolower($level)) {
                    'errors' => in_array($entryLevel, ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR']),
                    'warning', 'warnings' => $entryLevel === 'WARNING',
                    'info' => in_array($entryLevel, ['INFO', 'NOTICE']),
                    'debug' => $entryLevel === 'DEBUG',
                    default => strtolower($entryLevel) === strtolower($level),
                };
            });
        }

        // تطبيق البحث النصي
        if (! empty($search)) {
            $searchLower = mb_strtolower(trim($search));
            $filtered = array_filter($filtered, function ($entry) use ($searchLower) {
                return str_contains(mb_strtolower($entry['message']), $searchLower)
                    || str_contains(mb_strtolower($entry['stack_trace']), $searchLower)
                    || str_contains(mb_strtolower($entry['timestamp']), $searchLower);
            });
        }

        // عكس الترتيب لعرض الأحدث أولاً
        $filtered = array_reverse(array_values($filtered));

        $totalMatching = count($filtered);
        $page = max(1, $page);
        $lastPage = max(1, (int) ceil($totalMatching / $perPage));
        if ($page > $lastPage && $totalMatching > 0) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * $perPage;
        $paginatedEntries = array_slice($filtered, $offset, $perPage);

        return [
            'entries' => $paginatedEntries,
            'total_matching' => $totalMatching,
            'total_in_file' => count($allEntries),
            'current_page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'stats' => $stats,
        ];
    }

    /**
     * استخراج سجل محدد عبر معرفه (ID).
     */
    public function getLogEntryById(string $filename, string $id): ?array
    {
        $filename = basename($filename);
        $filePath = $this->logPath.DIRECTORY_SEPARATOR.$filename;

        if (! File::exists($filePath)) {
            return null;
        }

        $allEntries = $this->parseLogFile($filePath);
        foreach ($allEntries as $entry) {
            if ($entry['id'] === $id) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * تفريغ محتوى ملف السجل.
     */
    public function clearLogFile(string $filename): bool
    {
        $filename = basename($filename);
        $filePath = $this->logPath.DIRECTORY_SEPARATOR.$filename;

        if (! File::exists($filePath)) {
            return false;
        }

        try {
            $handle = fopen($filePath, 'w');
            if ($handle) {
                fclose($handle);

                return true;
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * حذف ملف سجل إضافي (مع منع حذف ملف laravel.log وتفريغه بدلاً من حذفه).
     */
    public function deleteLogFile(string $filename): bool
    {
        $filename = basename($filename);
        $filePath = $this->logPath.DIRECTORY_SEPARATOR.$filename;

        if (! File::exists($filePath)) {
            return false;
        }

        if ($filename === 'laravel.log') {
            return $this->clearLogFile($filename);
        }

        try {
            return File::delete($filePath);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * تنزيل ملف السجل.
     */
    public function downloadLogFile(string $filename): StreamedResponse
    {
        $filename = basename($filename);
        $filePath = $this->logPath.DIRECTORY_SEPARATOR.$filename;

        if (! File::exists($filePath)) {
            abort(404, 'ملف السجل غير موجود.');
        }

        return response()->streamDownload(function () use ($filePath) {
            $stream = fopen($filePath, 'r');
            while (! feof($stream)) {
                echo fread($stream, 1024 * 64);
                flush();
            }
            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * تحليل ملف السجل واستخراج السجلات وبنيتها.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseLogFile(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return [];
        }

        $entries = [];
        $currentEntry = null;
        $index = 0;

        // النمط القياسي لسجلات Laravel Monolog
        $pattern = '/^\[(?P<date>\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[\+-]\d{2}:?\d{2}|Z)?)\]\s*(?P<env>\w+)\.(?P<level>[A-Z]+):\s*(?P<message>.*)$/';

        while (($line = fgets($handle)) !== false) {
            if (preg_match($pattern, $line, $matches)) {
                if ($currentEntry !== null) {
                    $entries[] = $this->finalizeEntry($currentEntry);
                }

                $index++;
                $currentEntry = [
                    'id' => md5($matches['date'].$matches['level'].$index),
                    'index' => $index,
                    'timestamp' => $matches['date'],
                    'env' => $matches['env'],
                    'level' => strtoupper($matches['level']),
                    'message' => trim($matches['message']),
                    'stack_trace' => '',
                ];
            } elseif ($currentEntry !== null) {
                $currentEntry['stack_trace'] .= $line;
            }
        }

        if ($currentEntry !== null) {
            $entries[] = $this->finalizeEntry($currentEntry);
        }

        fclose($handle);

        return $entries;
    }

    /**
     * تجهيز الحقول التكميلية للسجل.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    protected function finalizeEntry(array $entry): array
    {
        $level = $entry['level'];

        $entry['badge_color'] = match ($level) {
            'EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR' => 'danger',
            'WARNING' => 'warning',
            'NOTICE', 'INFO' => 'info',
            'DEBUG' => 'gray',
            default => 'primary',
        };

        $entry['badge_label'] = match ($level) {
            'EMERGENCY' => 'طوارئ (Emergency)',
            'ALERT' => 'تنبيه حرج (Alert)',
            'CRITICAL' => 'حرج (Critical)',
            'ERROR' => 'خطأ (Error)',
            'WARNING' => 'تحذير (Warning)',
            'NOTICE' => 'إشعار (Notice)',
            'INFO' => 'معلومات (Info)',
            'DEBUG' => 'تصحيح (Debug)',
            default => $level,
        };

        try {
            $carbon = Carbon::parse($entry['timestamp'])->timezone(config('app.timezone', 'Asia/Riyadh'));
            $entry['formatted_time'] = $carbon->format('Y-m-d H:i:s');
            $entry['human_time'] = $carbon->diffForHumans();
        } catch (\Throwable) {
            $entry['formatted_time'] = $entry['timestamp'];
            $entry['human_time'] = '—';
        }

        $entry['stack_trace'] = trim($entry['stack_trace']);

        return $entry;
    }

    /**
     * احتساب إحصائيات السجلات ومستويات الأخطاء.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    protected function calculateStats(array $entries, string $filePath): array
    {
        $errorCount = 0;
        $warningCount = 0;
        $infoCount = 0;
        $debugCount = 0;

        foreach ($entries as $entry) {
            $lvl = $entry['level'];
            if (in_array($lvl, ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR'])) {
                $errorCount++;
            } elseif ($lvl === 'WARNING') {
                $warningCount++;
            } elseif (in_array($lvl, ['INFO', 'NOTICE'])) {
                $infoCount++;
            } elseif ($lvl === 'DEBUG') {
                $debugCount++;
            }
        }

        $size = file_exists($filePath) ? filesize($filePath) : 0;
        $lastModified = file_exists($filePath) ? filemtime($filePath) : time();

        return [
            'total' => count($entries),
            'error_count' => $errorCount,
            'warning_count' => $warningCount,
            'info_count' => $infoCount,
            'debug_count' => $debugCount,
            'file_size' => $this->formatBytes($size),
            'last_modified' => Carbon::createFromTimestamp($lastModified)->timezone(config('app.timezone', 'Asia/Riyadh'))->format('Y-m-d H:i:s'),
        ];
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
