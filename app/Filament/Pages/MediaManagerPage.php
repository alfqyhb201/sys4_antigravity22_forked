<?php

namespace App\Filament\Pages;

use App\Services\MediaManagerService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaManagerPage extends Page
{
    use WithPagination;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'إدارة الوسائط';

    protected static ?string $title = 'إدارة وسائط النظام والملفات غير المرتبطة';

    protected static ?string $slug = 'media-manager';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static string $view = 'filament.pages.media-manager-page';

    /**
     * فلتر التصنيف النشط (all, clients, client-templates, ideas, profile_images, livewire-tmp, other).
     */
    public string $selectedCategory = 'all';

    /**
     * فلتر نوع الملف (all, image, design, document, archive, other).
     */
    public string $selectedFileType = 'all';

    /**
     * نص البحث السريع.
     */
    public string $searchQuery = '';

    /**
     * عرض الملفات اليتيمة فقط (الافتراضي: نعم).
     */
    public bool $onlyOrphans = true;

    /**
     * وضع العرض: شبكة كروت (grid) أو جدول (table).
     */
    public string $viewMode = 'grid';

    /**
     * مصفوفة مسارات الملفات المحددة للعمليات الجماعية.
     *
     * @var array<string>
     */
    public array $selectedFiles = [];

    /**
     * عدد العناصر بالصفحة.
     */
    public int $perPage = 24;

    /**
     * بيانات مودال المعاينة السريعة.
     */
    public ?string $previewUrl = null;

    public ?string $previewName = null;

    public ?string $previewSize = null;

    public ?string $previewType = null;

    public ?string $previewPath = null;

    /**
     * التحقق من الصلاحيات للدخول للصفحة.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'admin'])
            || $user->can('view_media_manager');
    }

    /**
     * إعادة تعيين الصفحة عند تغيير أي فلتر.
     */
    public function updatedSelectedCategory(): void
    {
        $this->resetPage();
        $this->selectedFiles = [];
    }

    public function updatedSelectedFileType(): void
    {
        $this->resetPage();
        $this->selectedFiles = [];
    }

    public function updatedSearchQuery(): void
    {
        $this->resetPage();
        $this->selectedFiles = [];
    }

    public function updatedOnlyOrphans(): void
    {
        $this->resetPage();
        $this->selectedFiles = [];
    }

    /**
     * الحصول على إحصائيات التخزين الشاملة.
     */
    public function getStorageStats(): array
    {
        return app(MediaManagerService::class)->getStorageStatistics();
    }

    /**
     * جلب قائمة الملفات المفلترة والمقسمة لصفحات.
     */
    public function getFilesData(): array
    {
        $mediaService = app(MediaManagerService::class);
        $all = $mediaService->scanFiles(
            'public',
            $this->selectedCategory,
            $this->selectedFileType,
            $this->searchQuery,
            $this->onlyOrphans
        );

        $total = count($all);
        $offset = ($this->getPage() - 1) * $this->perPage;
        $sliced = array_slice($all, $offset, $this->perPage);

        $lastPage = max(1, (int) ceil($total / $this->perPage));

        return [
            'items' => $sliced,
            'total' => $total,
            'current_page' => $this->getPage(),
            'last_page' => $lastPage,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $this->perPage, $total),
        ];
    }

    /**
     * تحديد / إلغاء تحديد ملف فردي.
     */
    public function toggleSelectFile(string $path): void
    {
        if (in_array($path, $this->selectedFiles)) {
            $this->selectedFiles = array_values(array_diff($this->selectedFiles, [$path]));
        } else {
            $this->selectedFiles[] = $path;
        }
    }

    /**
     * تحديد كافة ملفات الصفحة الحالية.
     */
    public function selectAllInPage(): void
    {
        $files = $this->getFilesData()['items'];
        $pagePaths = array_column($files, 'path');

        $this->selectedFiles = array_values(array_unique(array_merge($this->selectedFiles, $pagePaths)));
    }

    /**
     * إلغاء تحديد كافة الملفات.
     */
    public function deselectAll(): void
    {
        $this->selectedFiles = [];
    }

    /**
     * فتح مودال معاينة الملف.
     */
    public function openPreviewModal(string $path, ?string $url, string $name, string $size, string $type): void
    {
        $this->previewPath = $path;
        $this->previewUrl = $url;
        $this->previewName = $name;
        $this->previewSize = $size;
        $this->previewType = $type;

        $this->dispatch('open-modal', id: 'media-preview-modal');
    }

    /**
     * إغلاق مودال المعاينة.
     */
    public function closePreviewModal(): void
    {
        $this->previewPath = null;
        $this->previewUrl = null;
        $this->previewName = null;
        $this->previewSize = null;
        $this->previewType = null;

        $this->dispatch('close-modal', id: 'media-preview-modal');
    }

    /**
     * تحميل ملف محدد إلى جهاز المستخدم.
     */
    public function downloadFile(string $path, string $disk = 'public'): ?StreamedResponse
    {
        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            Notification::make()
                ->title('الملف غير موجود')
                ->body('لم يتم العثور على الملف في التخزين.')
                ->danger()
                ->send();

            return null;
        }

        return $storage->download($path, basename($path));
    }

    /**
     * حذف ملف فردي.
     */
    public function deleteSingleFile(string $path, string $disk = 'public'): void
    {
        $mediaService = app(MediaManagerService::class);
        $success = $mediaService->deleteFile($path, $disk);

        if ($success) {
            $this->selectedFiles = array_values(array_diff($this->selectedFiles, [$path]));
            if ($this->previewPath === $path) {
                $this->closePreviewModal();
            }

            Notification::make()
                ->title('تم حذف الملف بنجاح')
                ->body("تم إزالة الملف {$path} نهائياً من التخزين.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('تعذر حذف الملف')
                ->body('حدث خطأ أثناء محاولة حذف الملف من القرص.')
                ->danger()
                ->send();
        }
    }

    /**
     * حذف الملفات المحددة جماعياً.
     */
    public function deleteSelected(): void
    {
        if (empty($this->selectedFiles)) {
            Notification::make()
                ->title('تنبيه')
                ->body('لم يتم تحديد أي ملفات لحذفها.')
                ->warning()
                ->send();

            return;
        }

        $mediaService = app(MediaManagerService::class);
        $result = $mediaService->deleteFiles($this->selectedFiles, 'public');

        $this->selectedFiles = [];

        Notification::make()
            ->title('تم تنظيف الملفات المحددة')
            ->body("تم حذف {$result['deleted_count']} ملف، وتم تحرير مساحة قدرها {$result['freed_size_formatted']}.")
            ->success()
            ->send();
    }

    /**
     * تنظيف وحذف جميع الملفات اليتيمة في النظام (مع حاجز أمان ساعة واحدة للملفات الحديثة).
     */
    public function cleanAllOrphans(int $safetyHours = 1): void
    {
        $mediaService = app(MediaManagerService::class);
        $result = $mediaService->cleanAllOrphanedFiles($safetyHours);

        $this->selectedFiles = [];
        $this->resetPage();

        Notification::make()
            ->title('تم التنظيف الشامل للملفات اليتيمة')
            ->body("تم حذف {$result['deleted_count']} ملف يتيم، واسترجاع مساحة {$result['freed_size_formatted']}.")
            ->success()
            ->send();
    }

    /**
     * تنظيف الملفات المؤقتة في مجلد livewire-tmp الأقدم من 24 ساعة.
     */
    public function cleanTempFiles(int $olderThanHours = 24): void
    {
        $mediaService = app(MediaManagerService::class);
        $result = $mediaService->cleanLivewireTmp($olderThanHours);

        Notification::make()
            ->title('تم تنظيف الملفات المؤقتة')
            ->body("تم حذف {$result['deleted_count']} ملف مؤقت، واسترجاع مساحة {$result['freed_size_formatted']}.")
            ->success()
            ->send();
    }
}
