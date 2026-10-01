<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientTagDistribution;
use App\Models\ClientTemplate;
use App\Models\DesignTask;
use App\Models\Idea;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaManagerService
{
    /**
     * استخراج كافة مسارات الملفات المسجلة والمرتبطة فعلياً في قاعدة البيانات.
     *
     * @return array<string, bool>
     */
    public function getAllDatabaseFilePaths(): array
    {
        $trackedPaths = [];

        $addPath = function ($val) use (&$trackedPaths) {
            if (empty($val)) {
                return;
            }

            if (is_array($val)) {
                foreach ($val as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $normalized = trim(str_replace('\\', '/', $item), '/');
                        $trackedPaths[$normalized] = true;
                    }
                }
            } elseif (is_string($val)) {
                $trimmed = trim($val);
                if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                    $decoded = json_decode($trimmed, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $item) {
                            if (is_string($item) && trim($item) !== '') {
                                $normalized = trim(str_replace('\\', '/', $item), '/');
                                $trackedPaths[$normalized] = true;
                            }
                        }

                        return;
                    }
                }

                $normalized = trim(str_replace('\\', '/', $trimmed), '/');
                if ($normalized !== '') {
                    $trackedPaths[$normalized] = true;
                }
            }
        };

        // 1. مهام التصميم
        DesignTask::query()
            ->select(['reference_files', 'design_files', 'revision_files'])
            ->chunk(250, function ($tasks) use ($addPath) {
                foreach ($tasks as $task) {
                    $addPath($task->reference_files);
                    $addPath($task->design_files);
                    $addPath($task->revision_files);
                }
            });

        // 2. توزيع التاقات والمرفقات ومرفقات المراجع
        ClientTagDistribution::query()
            ->select(['attachment_path', 'reviewer_attachments'])
            ->chunk(250, function ($records) use ($addPath) {
                foreach ($records as $record) {
                    $addPath($record->attachment_path);
                    $addPath($record->reviewer_attachments);
                }
            });

        // 3. قوالب العملاء
        ClientTemplate::query()
            ->select(['file'])
            ->chunk(250, function ($templates) use ($addPath) {
                foreach ($templates as $tpl) {
                    $addPath($tpl->file);
                }
            });

        // 4. شعارات العملاء
        Client::query()
            ->whereNotNull('logo_path')
            ->pluck('logo_path')
            ->each($addPath);

        // 5. صور المستخدمين الشخصية
        User::query()
            ->whereNotNull('profile_image')
            ->pluck('profile_image')
            ->each($addPath);

        // 6. ملفات ومرفقات الأفكار
        Idea::query()
            ->whereNotNull('idea_file')
            ->pluck('idea_file')
            ->each($addPath);

        return $trackedPaths;
    }

    /**
     * مسح وفحص الملفات في التخزين وتحديد حالتها (يتيمة أو مرتبطة).
     *
     * @param  string  $disk  القرص المستهدف (public / local)
     * @param  string|null  $category  التصنيف (clients, client-templates, ideas, profile_images, livewire-tmp, other)
     * @param  string|null  $fileType  نوع الملف (image, design, document, archive, other)
     * @param  string|null  $search  نص البحث في المسار أو الاسم
     * @param  bool|null  $onlyOrphans  حصر النتائج بالملفات اليتيمة فقط
     * @return array<array-key, array{
     *     path: string,
     *     disk: string,
     *     filename: string,
     *     extension: string,
     *     size_bytes: int,
     *     size_formatted: string,
     *     last_modified: Carbon,
     *     last_modified_formatted: string,
     *     is_orphan: bool,
     *     is_temp: bool,
     *     is_image: bool,
     *     category: string,
     *     category_label: string,
     *     type: string,
     *     type_label: string,
     *     url: string|null,
     *     thumb_url: string|null
     * }>
     */
    public function scanFiles(
        string $disk = 'public',
        ?string $category = null,
        ?string $fileType = null,
        ?string $search = null,
        ?bool $onlyOrphans = false
    ): array {
        $storage = Storage::disk($disk);
        if (! $storage->exists('')) {
            return [];
        }

        $allRawFiles = $storage->allFiles();
        $trackedPaths = $this->getAllDatabaseFilePaths();
        $results = [];

        foreach ($allRawFiles as $filePath) {
            $normalizedPath = trim(str_replace('\\', '/', $filePath), '/');

            // استبعاد ملفات النظام المخفية
            $baseName = basename($normalizedPath);
            if ($baseName === '.gitignore' || str_starts_with($baseName, '.')) {
                continue;
            }

            // التحقق إن كان الملف صورة مصغرة لملف مسجل في قاعدة البيانات
            $isLinked = isset($trackedPaths[$normalizedPath]);
            if (! $isLinked && str_contains($normalizedPath, '/thumbnails/')) {
                $baseWithoutThumb = preg_replace('/\/thumbnails\/(.+)\.(webp|jpg|png)$/i', '/$1', $normalizedPath);
                foreach (['.jpeg', '.jpg', '.png', '.webp', '.gif', '.svg'] as $ext) {
                    if (isset($trackedPaths[$baseWithoutThumb.$ext])) {
                        $isLinked = true;
                        break;
                    }
                }
            }

            $isOrphan = ! $isLinked;
            $isTemp = str_starts_with($normalizedPath, 'livewire-tmp');

            // فلترة باليتيمة فقط إن طلبت
            if ($onlyOrphans && ! $isOrphan) {
                continue;
            }

            $fileCat = $this->determineCategory($normalizedPath);
            if ($category && $category !== 'all' && $fileCat['key'] !== $category) {
                continue;
            }

            $ext = strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION));
            $typeInfo = $this->determineFileType($ext);

            if ($fileType && $fileType !== 'all' && $typeInfo['key'] !== $fileType) {
                continue;
            }

            // فلترة بالبحث
            if (! empty($search)) {
                $searchLower = mb_strtolower($search);
                $nameLower = mb_strtolower($baseName);
                $pathLower = mb_strtolower($normalizedPath);

                if (! str_contains($nameLower, $searchLower) && ! str_contains($pathLower, $searchLower)) {
                    continue;
                }
            }

            $sizeBytes = 0;
            $lastModifiedTimestamp = time();

            try {
                $sizeBytes = $storage->size($filePath);
                $lastModifiedTimestamp = $storage->lastModified($filePath);
            } catch (\Throwable $e) {
                Log::debug("Error reading file stats for {$filePath}: ".$e->getMessage());
            }

            $lastModified = Carbon::createFromTimestamp($lastModifiedTimestamp);
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg']);

            $url = null;
            $thumbUrl = null;

            if ($disk === 'public') {
                $url = $storage->url($normalizedPath);
                if ($isImage) {
                    $thumbUrl = $url;
                }
            }

            $results[] = [
                'path' => $normalizedPath,
                'disk' => $disk,
                'filename' => $baseName,
                'extension' => $ext,
                'size_bytes' => $sizeBytes,
                'size_formatted' => $this->formatBytes($sizeBytes),
                'last_modified' => $lastModified,
                'last_modified_formatted' => $lastModified->diffForHumans(),
                'is_orphan' => $isOrphan,
                'is_temp' => $isTemp,
                'is_image' => $isImage,
                'category' => $fileCat['key'],
                'category_label' => $fileCat['label'],
                'type' => $typeInfo['key'],
                'type_label' => $typeInfo['label'],
                'url' => $url,
                'thumb_url' => $thumbUrl,
            ];
        }

        // ترتيب النتائج بحسب أحدث تعديل أولاً
        usort($results, fn ($a, $b) => $b['last_modified']->timestamp <=> $a['last_modified']->timestamp);

        return $results;
    }

    /**
     * استخراج إحصائيات التخزين الشاملة (الحجم الكلي، اليتيم، المرتبط، المؤقت، والتصنيفات).
     */
    public function getStorageStatistics(): array
    {
        $allPublicFiles = $this->scanFiles('public', null, null, null, false);
        $localTempFiles = [];

        if (Storage::disk('local')->exists('livewire-tmp')) {
            $rawLocal = Storage::disk('local')->allFiles('livewire-tmp');
            foreach ($rawLocal as $f) {
                $normalized = trim(str_replace('\\', '/', $f), '/');
                $size = 0;
                $mtime = time();
                try {
                    $size = Storage::disk('local')->size($f);
                    $mtime = Storage::disk('local')->lastModified($f);
                } catch (\Throwable $e) {
                }
                $localTempFiles[] = [
                    'path' => $normalized,
                    'disk' => 'local',
                    'size_bytes' => $size,
                    'last_modified' => Carbon::createFromTimestamp($mtime),
                ];
            }
        }

        $totalPublicBytes = 0;
        $orphanedBytes = 0;
        $orphanedCount = 0;
        $linkedBytes = 0;
        $linkedCount = 0;
        $categoriesStats = [];

        foreach ($allPublicFiles as $file) {
            $bytes = $file['size_bytes'];
            $totalPublicBytes += $bytes;
            $catKey = $file['category'];
            $catLabel = $file['category_label'];

            if (! isset($categoriesStats[$catKey])) {
                $categoriesStats[$catKey] = [
                    'key' => $catKey,
                    'label' => $catLabel,
                    'total_count' => 0,
                    'total_bytes' => 0,
                    'orphaned_count' => 0,
                    'orphaned_bytes' => 0,
                ];
            }

            $categoriesStats[$catKey]['total_count']++;
            $categoriesStats[$catKey]['total_bytes'] += $bytes;

            if ($file['is_orphan']) {
                $orphanedCount++;
                $orphanedBytes += $bytes;
                $categoriesStats[$catKey]['orphaned_count']++;
                $categoriesStats[$catKey]['orphaned_bytes'] += $bytes;
            } else {
                $linkedCount++;
                $linkedBytes += $bytes;
            }
        }

        $tempBytes = 0;
        $tempCount = count($localTempFiles);
        foreach ($localTempFiles as $tf) {
            $tempBytes += $tf['size_bytes'];
        }

        // تنسيق الأحجام للتصنيفات
        foreach ($categoriesStats as &$cs) {
            $cs['total_size_formatted'] = $this->formatBytes($cs['total_bytes']);
            $cs['orphaned_size_formatted'] = $this->formatBytes($cs['orphaned_bytes']);
        }

        return [
            'total_files_count' => count($allPublicFiles),
            'total_size_bytes' => $totalPublicBytes,
            'total_size_formatted' => $this->formatBytes($totalPublicBytes),

            'orphaned_files_count' => $orphanedCount,
            'orphaned_size_bytes' => $orphanedBytes,
            'orphaned_size_formatted' => $this->formatBytes($orphanedBytes),

            'linked_files_count' => $linkedCount,
            'linked_size_bytes' => $linkedBytes,
            'linked_size_formatted' => $this->formatBytes($linkedBytes),

            'temp_files_count' => $tempCount,
            'temp_size_bytes' => $tempBytes,
            'temp_size_formatted' => $this->formatBytes($tempBytes),

            'categories' => array_values($categoriesStats),
        ];
    }

    /**
     * حذف ملف فردي مع صورته المصغرة إن وجدت.
     */
    public function deleteFile(string $path, string $disk = 'public'): bool
    {
        $storage = Storage::disk($disk);
        $normalizedPath = trim(str_replace('\\', '/', $path), '/');

        if (! $storage->exists($normalizedPath)) {
            return false;
        }

        try {
            // حذف الصورة المصغرة إن وجدت
            if ($disk === 'public') {
                app(ImageThumbnailService::class)->deleteThumbnail($normalizedPath);
            }

            $deleted = $storage->delete($normalizedPath);

            // تنظيف المجلد الأب إذا أصبح فارغاً (لتجنب المجلدات المهجورة)
            $this->cleanEmptyParentDirectory($storage, dirname($normalizedPath));

            return $deleted;
        } catch (\Throwable $e) {
            Log::error("Failed to delete media file {$normalizedPath}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * حذف مصفوفة من الملفات دفعة واحدة مع حساب الحجم المحرر.
     *
     * @param  array<string>  $paths
     * @return array{deleted_count: int, freed_bytes: int, freed_size_formatted: string}
     */
    public function deleteFiles(array $paths, string $disk = 'public'): array
    {
        $storage = Storage::disk($disk);
        $deletedCount = 0;
        $freedBytes = 0;

        foreach ($paths as $path) {
            $normalizedPath = trim(str_replace('\\', '/', $path), '/');
            if ($storage->exists($normalizedPath)) {
                try {
                    $freedBytes += $storage->size($normalizedPath);
                } catch (\Throwable $e) {
                }

                if ($this->deleteFile($normalizedPath, $disk)) {
                    $deletedCount++;
                }
            }
        }

        return [
            'deleted_count' => $deletedCount,
            'freed_bytes' => $freedBytes,
            'freed_size_formatted' => $this->formatBytes($freedBytes),
        ];
    }

    /**
     * تنظيف شامل لكافة الملفات اليتيمة غير المرتبطة بقاعدة البيانات.
     *
     * @param  int  $olderThanHours  حماية الملفات المرفوعة مؤخراً (بالساعات)
     * @return array{deleted_count: int, freed_bytes: int, freed_size_formatted: string}
     */
    public function cleanAllOrphanedFiles(int $olderThanHours = 1): array
    {
        $orphanedFiles = $this->scanFiles('public', null, null, null, true);
        $thresholdTime = now()->subHours($olderThanHours);

        $pathsToDelete = [];
        foreach ($orphanedFiles as $file) {
            // التحقق من أن عمر الملف تجاوز حاجز الأمان
            if ($file['last_modified']->lessThanOrEqualTo($thresholdTime)) {
                $pathsToDelete[] = $file['path'];
            }
        }

        return $this->deleteFiles($pathsToDelete, 'public');
    }

    /**
     * تنظيف الملفات المؤقتة في مجلد livewire-tmp.
     *
     * @param  int  $olderThanHours  حذف الملفات الأقدم من عدد محدد من الساعات (افتراضياً: 24 ساعة)
     * @return array{deleted_count: int, freed_bytes: int, freed_size_formatted: string}
     */
    public function cleanLivewireTmp(int $olderThanHours = 24): array
    {
        $storage = Storage::disk('local');
        $deletedCount = 0;
        $freedBytes = 0;
        $thresholdTime = now()->subHours($olderThanHours);

        if ($storage->exists('livewire-tmp')) {
            $files = $storage->allFiles('livewire-tmp');
            foreach ($files as $f) {
                try {
                    $mtime = Carbon::createFromTimestamp($storage->lastModified($f));
                    if ($mtime->lessThanOrEqualTo($thresholdTime)) {
                        $freedBytes += $storage->size($f);
                        if ($storage->delete($f)) {
                            $deletedCount++;
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        // أيضاً فحص إن وجد مجلد livewire-tmp في قرص public
        $publicStorage = Storage::disk('public');
        if ($publicStorage->exists('livewire-tmp')) {
            $pFiles = $publicStorage->allFiles('livewire-tmp');
            foreach ($pFiles as $f) {
                try {
                    $mtime = Carbon::createFromTimestamp($publicStorage->lastModified($f));
                    if ($mtime->lessThanOrEqualTo($thresholdTime)) {
                        $freedBytes += $publicStorage->size($f);
                        if ($publicStorage->delete($f)) {
                            $deletedCount++;
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        return [
            'deleted_count' => $deletedCount,
            'freed_bytes' => $freedBytes,
            'freed_size_formatted' => $this->formatBytes($freedBytes),
        ];
    }

    /**
     * حذف المجلد الأب إذا كان فارغاً بعد حذف محتواه.
     */
    protected function cleanEmptyParentDirectory($storage, string $dir): void
    {
        if (empty($dir) || $dir === '.' || $dir === '/' || in_array($dir, ['clients', 'ideas', 'client-templates', 'profile_images', 'livewire-tmp'])) {
            return;
        }

        try {
            $files = $storage->files($dir);
            $dirs = $storage->directories($dir);

            if (empty($files) && empty($dirs)) {
                $storage->deleteDirectory($dir);
                // تكرار تنظيف المجلد الأب الأعلى
                $this->cleanEmptyParentDirectory($storage, dirname($dir));
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * تحديد تصنيف الملف بناءً على مساره.
     *
     * @return array{key: string, label: string}
     */
    public function determineCategory(string $path): array
    {
        if (str_starts_with($path, 'clients/')) {
            return ['key' => 'clients', 'label' => 'مهام وتصاميم العملاء'];
        }
        if (str_starts_with($path, 'client-templates/')) {
            return ['key' => 'client-templates', 'label' => 'قوالب العملاء'];
        }
        if (str_starts_with($path, 'ideas/')) {
            return ['key' => 'ideas', 'label' => 'مرفقات الأفكار'];
        }
        if (str_starts_with($path, 'profile_images/')) {
            return ['key' => 'profile_images', 'label' => 'صور المستخدمين'];
        }
        if (str_starts_with($path, 'livewire-tmp/')) {
            return ['key' => 'livewire-tmp', 'label' => 'ملفات رفع مؤقتة'];
        }

        return ['key' => 'other', 'label' => 'وسائط وملفات أخرى'];
    }

    /**
     * تحديد نوع الملف وفق امتداده.
     *
     * @return array{key: string, label: string}
     */
    public function determineFileType(string $extension): array
    {
        $ext = strtolower($extension);

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp', 'avif'])) {
            return ['key' => 'image', 'label' => 'صور'];
        }
        if (in_array($ext, ['ai', 'psd', 'cdr', 'eps', 'indd', 'xd', 'fig', 'sketch'])) {
            return ['key' => 'design', 'label' => 'ملفات تصميم'];
        }
        if (in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'rtf'])) {
            return ['key' => 'document', 'label' => 'مستندات'];
        }
        if (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
            return ['key' => 'archive', 'label' => 'ملفات مضغوطة'];
        }

        return ['key' => 'other', 'label' => 'ملفات أخرى'];
    }

    /**
     * تنسيق حجم البايت إلى وحدات مقروءة (B, KB, MB, GB).
     */
    public function formatBytes(int|float $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision).' '.$units[$pow];
    }
}
