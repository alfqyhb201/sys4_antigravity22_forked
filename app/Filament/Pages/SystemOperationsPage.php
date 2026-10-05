<?php

namespace App\Filament\Pages;

use App\Services\CacheManagerService;
use App\Services\SystemHealthService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * صفحة مركز صيانة وعمليات النظام (حصرية لـ Super Admin).
 *
 * تتضمن بطاقات معلومات السيرفر، أدوات تفريغ الكاش بضغطة زر، وإدارة وضع الصيانة.
 */
class SystemOperationsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string $view = 'filament.pages.system-operations-page';

    protected static ?string $navigationGroup = 'إدارة النظام (Super Admin)';

    protected static ?string $navigationLabel = 'صيانة وعمليات النظام';

    protected static ?string $title = 'مركز صيانة وعمليات النظام';

    protected static ?string $slug = 'system-operations';

    protected static ?int $navigationSort = 1;

    /**
     * بيانات المؤشرات وصحة النظام.
     */
    public array $healthData = [];

    /**
     * حقول إعداد وضع الصيانة.
     */
    public string $maintenanceSecret = '';

    public string $maintenanceMessage = 'النظام يخضع لأعمال الصيانة الدورية حالياً، سنعود للعمل قريباً.';

    public int $maintenanceRetry = 60;

    /**
     * مخرجات آخر أمر تم تنفيذه (إن وُجد).
     */
    public ?string $lastCommandOutput = null;

    /**
     * التحقق من صلاحية الوصول: مقتصرة تماماً على Super Admin.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole('super_admin');
    }

    /**
     * إعداد الصفحة عند التحميل.
     */
    public function mount(SystemHealthService $healthService): void
    {
        abort_unless(static::canAccess(), 403, 'غير مصرح لك بالوصول لمركز صيانة النظام.');

        $this->refreshHealthData($healthService);
        $this->generateRandomSecret();
    }

    /**
     * تحديث بيانات المؤشرات الحية.
     */
    public function refreshHealthData(?SystemHealthService $healthService = null): void
    {
        $healthService = $healthService ?? app(SystemHealthService::class);
        $this->healthData = $healthService->getSystemHealthOverview();
    }

    /**
     * توليد مفتاح مرور سري عشوائي لوضع الصيانة.
     */
    public function generateRandomSecret(): void
    {
        $this->maintenanceSecret = Str::lower(Str::random(12));
    }

    /**
     * 1. تفريغ كاش التطبيق والإعدادات.
     */
    public function clearAppCache(CacheManagerService $cacheManager): void
    {
        $result = $cacheManager->clearAppCache();
        $this->lastCommandOutput = $result['message'];
        $this->refreshHealthData();

        $notif = Notification::make()->title($result['message']);
        $result['success'] ? $notif->success() : $notif->danger();
        $notif->send();
    }

    /**
     * 2. تفريغ كاش المسارات (Routes).
     */
    public function clearRouteCache(CacheManagerService $cacheManager): void
    {
        $result = $cacheManager->clearRouteCache();
        $this->lastCommandOutput = $result['message'];
        $this->refreshHealthData();

        $notif = Notification::make()->title($result['message']);
        $result['success'] ? $notif->success() : $notif->danger();
        $notif->send();
    }

    /**
     * 3. تفريغ كاش القوالب (Views).
     */
    public function clearViewCache(CacheManagerService $cacheManager): void
    {
        $result = $cacheManager->clearViewCache();
        $this->lastCommandOutput = $result['message'];
        $this->refreshHealthData();

        $notif = Notification::make()->title($result['message']);
        $result['success'] ? $notif->success() : $notif->danger();
        $notif->send();
    }

    /**
     * 4. تفريغ كاش Filament.
     */
    public function clearFilamentCache(CacheManagerService $cacheManager): void
    {
        $result = $cacheManager->clearFilamentCache();
        $this->lastCommandOutput = $result['message'];
        $this->refreshHealthData();

        $notif = Notification::make()->title($result['message']);
        $result['success'] ? $notif->success() : $notif->danger();
        $notif->send();
    }

    /**
     * 5. تفريغ الكاش الشامل.
     */
    public function clearAllCache(CacheManagerService $cacheManager): void
    {
        $result = $cacheManager->clearAllCache();
        $this->lastCommandOutput = implode("\n", $result['details'] ?? [$result['message']]);
        $this->refreshHealthData();

        $notif = Notification::make()->title($result['message']);
        $result['success'] ? $notif->success() : $notif->danger();
        $notif->send();
    }

    /**
     * 6. تحسين وتخزين كاش الإنتاج (Optimize).
     */
    public function optimizeForProduction(): void
    {
        try {
            Artisan::call('optimize');
            $output = Artisan::output();

            $this->lastCommandOutput = $output;
            $this->refreshHealthData();

            Notification::make()
                ->title('تم بناء وتحسين كاش الإنتاج بنجاح 🚀')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('خطأ أثناء بناء كاش الإنتاج')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * تفعيل وضع الصيانة.
     */
    public function enableMaintenance(): void
    {
        try {
            $params = [];
            if (! empty($this->maintenanceSecret)) {
                $params['--secret'] = $this->maintenanceSecret;
            }
            if (! empty($this->maintenanceRetry)) {
                $params['--retry'] = (int) $this->maintenanceRetry;
            }

            Artisan::call('down', $params);

            $this->refreshHealthData();

            Notification::make()
                ->title('تم تفعيل وضع الصيانة للنظام بنجاح ⚠️')
                ->warning()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('خطأ في تفعيل وضع الصيانة')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * تعطيل وضع الصيانة والعودة للعمل.
     */
    public function disableMaintenance(): void
    {
        try {
            Artisan::call('up');

            $this->refreshHealthData();

            Notification::make()
                ->title('تم تعطيل وضع الصيانة وعاد النظام للعمل بشكل طبيعي ✅')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('خطأ في تعطيل وضع الصيانة')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
