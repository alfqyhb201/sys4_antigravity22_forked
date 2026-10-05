<?php

namespace App\Filament\Pages;

use App\Services\BackupManagerService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * صفحة مركز النسخ الاحتياطي (حصرية لـ Super Admin).
 *
 * إدارة وتوليد وتحميل وحذف النسخ الاحتياطية لقاعدة البيانات والملفات.
 */
class BackupManagerPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static string $view = 'filament.pages.backup-manager-page';

    protected static ?string $navigationGroup = 'إدارة النظام (Super Admin)';

    protected static ?string $navigationLabel = 'مركز النسخ الاحتياطي';

    protected static ?string $title = 'مركز النسخ الاحتياطي للنظام';

    protected static ?string $slug = 'backup-manager';

    protected static ?int $navigationSort = 2;

    /**
     * قائمة ملفات النسخ الاحتياطية.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $backups = [];

    /**
     * إحصائيات النسخ الاحتياطية.
     *
     * @var array<string, mixed>
     */
    public array $statistics = [];

    /**
     * مخرجات آخر أمر تشغيلي.
     */
    public ?string $lastCommandOutput = null;

    /**
     * التحقق من الصلاحية: متاحة فقط لـ Super Admin.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole('super_admin');
    }

    /**
     * تهيئة الصفحة عند التحميل.
     */
    public function mount(BackupManagerService $backupService): void
    {
        abort_unless(static::canAccess(), 403, 'غير مصرح لك بالوصول لمركز النسخ الاحتياطي.');

        $this->refreshData($backupService);
    }

    /**
     * تحديث قائمة النسخ والإحصائيات.
     */
    public function refreshData(?BackupManagerService $backupService = null): void
    {
        $backupService = $backupService ?? app(BackupManagerService::class);
        $this->backups = $backupService->getBackups();
        $this->statistics = $backupService->getStatistics();
    }

    /**
     * حالة ظهور نافذة خيارات النسخ الاحتياطي.
     */
    public bool $showBackupModal = false;

    /**
     * نوع النسخ المطلوب ('db' لقاعدة البيانات، 'full' للنسخة الشاملة).
     */
    public string $backupType = 'db';

    /**
     * الأقراص المختارة لتخزين النسخة.
     *
     * @var array<string>
     */
    public array $targetDisks = ['local', 'google'];

    /**
     * فتح نافذة خيارات وجهة النسخ الاحتياطي.
     */
    public function openBackupModal(string $type = 'db'): void
    {
        $this->backupType = in_array($type, ['db', 'full']) ? $type : 'db';
        $this->targetDisks = $this->backupType === 'db' ? ['local', 'google'] : ['local'];
        $this->showBackupModal = true;
    }

    /**
     * إغلاق نافذة خيارات النسخ.
     */
    public function closeBackupModal(): void
    {
        $this->showBackupModal = false;
    }

    /**
     * تنفيذ عملية النسخ الاحتياطي بناءً على الخيارات المحددة.
     */
    public function runSelectedBackup(BackupManagerService $backupService): void
    {
        if (empty($this->targetDisks)) {
            Notification::make()
                ->title('يرجى اختيار قرص تخزين واحد على الأقل.')
                ->warning()
                ->send();

            return;
        }

        $onlyDb = ($this->backupType === 'db');
        $result = $backupService->createBackup(onlyDb: $onlyDb, disks: $this->targetDisks);

        $this->lastCommandOutput = $result['output'];
        $this->showBackupModal = false;
        $this->refreshData($backupService);

        $notif = Notification::make()->title($result['message']);
        $result['success'] ? $notif->success() : $notif->danger();
        $notif->send();
    }

    /**
     * إنشاء نسخة احتياطية لقاعدة البيانات فقط مباشرة.
     */
    public function createDatabaseBackup(BackupManagerService $backupService): void
    {
        $result = $backupService->createBackup(onlyDb: true, disks: ['local', 'google']);

        $this->lastCommandOutput = $result['output'];
        $this->refreshData($backupService);

        $notif = Notification::make()->title($result['message']);
        $result['success'] ? $notif->success() : $notif->danger();
        $notif->send();
    }

    /**
     * إنشاء نسخة احتياطية كاملة (الملفات وقاعدة البيانات).
     */
    public function createFullBackup(BackupManagerService $backupService): void
    {
        $result = $backupService->createBackup(onlyDb: false, disks: ['local']);

        $this->lastCommandOutput = $result['output'];
        $this->refreshData($backupService);

        $notif = Notification::make()->title($result['message']);
        $result['success'] ? $notif->success() : $notif->danger();
        $notif->send();
    }

    /**
     * تنظيف النسخ الاحتياطية القديمة.
     */
    public function cleanOldBackups(BackupManagerService $backupService): void
    {
        $result = $backupService->cleanOldBackups();

        $this->lastCommandOutput = $result['output'];
        $this->refreshData($backupService);

        $notif = Notification::make()->title($result['message']);
        $result['success'] ? $notif->success() : $notif->danger();
        $notif->send();
    }

    /**
     * تحميل ملف نسخة احتياطية محددة.
     */
    public function downloadBackup(string $disk, string $path, BackupManagerService $backupService): StreamedResponse
    {
        return $backupService->downloadBackup($disk, $path);
    }

    /**
     * حذف ملف نسخة احتياطية محددة.
     */
    public function deleteBackup(string $disk, string $path, BackupManagerService $backupService): void
    {
        $deleted = $backupService->deleteBackup($disk, $path);
        $this->refreshData($backupService);

        if ($deleted) {
            Notification::make()
                ->title('تم حذف ملف النسخة الاحتياطية بنجاح.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('تعذر حذف ملف النسخة الاحتياطية.')
                ->danger()
                ->send();
        }
    }
}
