<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class CacheManagerService
{
    /**
     * تفريغ كاش التطبيق والإعدادات.
     *
     * @return array{success: bool, message: string}
     */
    public function clearAppCache(): array
    {
        try {
            // 1. تفريغ كاش التطبيق
            Cache::flush();

            // 2. حذف ملف كاش الإعدادات
            $configPath = app()->getCachedConfigPath();
            if (File::exists($configPath)) {
                File::delete($configPath);
            }

            return [
                'success' => true,
                'message' => 'تم تفريغ كاش التطبيق وكاش الإعدادات (Config & Cache) بنجاح.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'خطأ أثناء تفريغ كاش التطبيق: '.$e->getMessage(),
            ];
        }
    }

    /**
     * تفريغ كاش المسارات (Routes).
     *
     * @return array{success: bool, message: string}
     */
    public function clearRouteCache(): array
    {
        try {
            $routesPath = app()->getCachedRoutesPath();
            if (File::exists($routesPath)) {
                File::delete($routesPath);
            }

            return [
                'success' => true,
                'message' => 'تم تفريغ كاش المسارات (Routes) بنجاح.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'خطأ أثناء تفريغ كاش المسارات: '.$e->getMessage(),
            ];
        }
    }

    /**
     * تفريغ كاش القوالب (Views).
     *
     * @return array{success: bool, message: string}
     */
    public function clearViewCache(): array
    {
        try {
            $viewsPath = config('view.compiled');
            if ($viewsPath && File::isDirectory($viewsPath)) {
                foreach (File::glob("{$viewsPath}/*") as $viewFile) {
                    if (File::isFile($viewFile) && ! str_ends_with($viewFile, '.gitignore')) {
                        File::delete($viewFile);
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'تم تفريغ كاش القوالب (Blade Views) بنجاح.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'خطأ أثناء تفريغ كاش القوالب: '.$e->getMessage(),
            ];
        }
    }

    /**
     * تفريغ كاش Filament وأيقونات Blade.
     *
     * @return array{success: bool, message: string}
     */
    public function clearFilamentCache(): array
    {
        try {
            // 1. حذف كاش أيقونات Blade
            $bladeIconsPath = base_path('bootstrap/cache/blade-icons.php');
            if (File::exists($bladeIconsPath)) {
                File::delete($bladeIconsPath);
            }

            // 2. تفريغ مجلد كاش Filament
            $filamentCacheDir = base_path('bootstrap/cache/filament');
            if (File::isDirectory($filamentCacheDir)) {
                File::deleteDirectory($filamentCacheDir);
            }

            return [
                'success' => true,
                'message' => 'تم تفريغ كاش لوحة Filament ومكوناتها وأيقوناتها بنجاح.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'خطأ أثناء تفريغ كاش Filament: '.$e->getMessage(),
            ];
        }
    }

    /**
     * تفريغ الكاش الشامل للنظام بالكامل.
     *
     * @return array{success: bool, message: string, details: array<string>}
     */
    public function clearAllCache(): array
    {
        $details = [];

        // 1. تفريغ التطبيق والإعدادات
        $appResult = $this->clearAppCache();
        $details[] = $appResult['message'];

        // 2. تفريغ المسارات
        $routeResult = $this->clearRouteCache();
        $details[] = $routeResult['message'];

        // 3. تفريغ القوالب
        $viewResult = $this->clearViewCache();
        $details[] = $viewResult['message'];

        // 4. تفريغ كاش الأحداث والحزم
        try {
            $eventsPath = app()->getCachedEventsPath();
            if (File::exists($eventsPath)) {
                File::delete($eventsPath);
            }

            $servicesPath = app()->getCachedServicesPath();
            if (File::exists($servicesPath)) {
                File::delete($servicesPath);
            }

            $packagesPath = app()->getCachedPackagesPath();
            if (File::exists($packagesPath)) {
                File::delete($packagesPath);
            }

            $details[] = 'تم تنظيف كاش الأحداث (Events) والخدمات المحملة.';
        } catch (\Throwable) {
            // صامت
        }

        // 5. تفريغ Filament
        $filamentResult = $this->clearFilamentCache();
        $details[] = $filamentResult['message'];

        return [
            'success' => true,
            'message' => 'تم تنظيف وإفراغ كافة ملفات الكاش للنظام بالكامل بنجاح ⚡',
            'details' => $details,
        ];
    }
}
