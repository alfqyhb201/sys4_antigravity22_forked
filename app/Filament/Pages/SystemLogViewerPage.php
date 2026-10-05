<?php

namespace App\Filament\Pages;

use App\Services\SystemLogReaderService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * صفحة مستعرض سجلات الأخطاء الحية (حصرية لـ Super Admin).
 *
 * تتيح قراءة وفلترة وبحث وتحميل وتفريغ ملفات سجلات النظام (laravel.log).
 */
class SystemLogViewerPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.system-log-viewer-page';

    protected static ?string $navigationGroup = 'إدارة النظام (Super Admin)';

    protected static ?string $navigationLabel = 'سجلات الأخطاء';

    protected static ?string $title = 'مستعرض سجلات الأخطاء الحية';

    protected static ?string $slug = 'system-logs';

    protected static ?int $navigationSort = 3;

    /**
     * ملف السجل المختار حالياً.
     */
    public string $selectedFile = 'laravel.log';

    /**
     * مستوى الخطأ المختار للفلترة.
     */
    public string $selectedLevel = 'all';

    /**
     * عبارة البحث النصي.
     */
    public string $search = '';

    /**
     * رقم الصفحة الحالية.
     */
    public int $page = 1;

    /**
     * عدد السجلات في كل صفحة.
     */
    public int $perPage = 25;

    /**
     * قائمة ملفات السجلات المتوفرة.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $availableFiles = [];

    /**
     * بيانات السجلات والنتائج والإحصائيات.
     *
     * @var array<string, mixed>
     */
    public array $logsData = [];

    /**
     * السجل النشط المعروض في مودال تتبع الخطأ (Stack Trace).
     *
     * @var array<string, mixed>|null
     */
    public ?array $activeEntry = null;

    /**
     * حالة ظهور مودال تفاصيل الخطأ.
     */
    public bool $showModal = false;

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
    public function mount(SystemLogReaderService $logService): void
    {
        abort_unless(static::canAccess(), 403, 'غير مصرح لك بالوصول لمستعرض سجلات النظام.');

        $this->availableFiles = $logService->getAvailableLogFiles();
        if (! empty($this->availableFiles)) {
            $this->selectedFile = $this->availableFiles[0]['name'];
        }

        $this->loadLogs($logService);
    }

    /**
     * تحميل السجلات وتحديث البيانات.
     */
    public function loadLogs(?SystemLogReaderService $logService = null): void
    {
        $logService = $logService ?? app(SystemLogReaderService::class);
        $this->availableFiles = $logService->getAvailableLogFiles();

        $this->logsData = $logService->getLogs(
            filename: $this->selectedFile,
            level: $this->selectedLevel,
            search: $this->search,
            page: $this->page,
            perPage: $this->perPage
        );

        if (isset($this->logsData['current_page'])) {
            $this->page = $this->logsData['current_page'];
        }
    }

    /**
     * تغيير ملف السجل المختار.
     */
    public function changeFile(string $filename, SystemLogReaderService $logService): void
    {
        $this->selectedFile = $filename;
        $this->page = 1;
        $this->loadLogs($logService);
    }

    /**
     * تغيير فلتر المستوى.
     */
    public function changeLevel(string $level, SystemLogReaderService $logService): void
    {
        $this->selectedLevel = $level;
        $this->page = 1;
        $this->loadLogs($logService);
    }

    /**
     * تحديث فوري عند تغيير نص البحث.
     */
    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->loadLogs();
    }

    /**
     * الانتقال للصفحة التالية.
     */
    public function nextPage(): void
    {
        $lastPage = $this->logsData['last_page'] ?? 1;
        if ($this->page < $lastPage) {
            $this->page++;
            $this->loadLogs();
        }
    }

    /**
     * الانتقال للصفحة السابقة.
     */
    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->loadLogs();
        }
    }

    /**
     * الانتقال لصفحة محددة.
     */
    public function gotoPage(int $page): void
    {
        $lastPage = $this->logsData['last_page'] ?? 1;
        if ($page >= 1 && $page <= $lastPage) {
            $this->page = $page;
            $this->loadLogs();
        }
    }

    /**
     * فتح مودال تفاصيل الخطأ وتتبع الـ Stack Trace.
     */
    public function openStackTraceModal(string $entryId, SystemLogReaderService $logService): void
    {
        $this->activeEntry = $logService->getLogEntryById($this->selectedFile, $entryId);
        $this->showModal = true;
    }

    /**
     * إغلاق المودال.
     */
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->activeEntry = null;
    }

    /**
     * تفريغ ملف السجل المختار.
     */
    public function clearLog(SystemLogReaderService $logService): void
    {
        $cleared = $logService->clearLogFile($this->selectedFile);
        $this->page = 1;
        $this->loadLogs($logService);

        if ($cleared) {
            Notification::make()
                ->title('تم تفريغ ملف السجل بنجاح ✅')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('تعذر تفريغ ملف السجل.')
                ->danger()
                ->send();
        }
    }

    /**
     * تنزيل ملف السجل الحالي.
     */
    public function downloadLog(SystemLogReaderService $logService): StreamedResponse
    {
        return $logService->downloadLogFile($this->selectedFile);
    }
}
