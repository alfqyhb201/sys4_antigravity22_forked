<?php

namespace App\Filament\Pages;

use App\Livewire\TagImporterComponent;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class CustomTagImport extends Page
{
    protected static ?string $slug = 'custom-tag-import';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-on-square-stack';

    protected static string $view = 'filament.pages.custom-tag-import';

    /**
     * عنوان الصفحة في المتصفح.
     */
    protected static ?string $title = 'استيراد الوسوم';

    /**
     * عنوان القائمة الجانبية.
     */
    protected static ?string $navigationLabel = 'استيراد الوسوم';

    /**
     * مجموعة التنقل.
     */
    protected static ?string $navigationGroup = 'المحتوى';

    /**
     * ترتيب القائمة.
     */
    protected static ?int $navigationSort = 5;

    /**
     * إذن الوصول.
     */
    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->can('create_tag') ?? false;
    }

    /**
     * تمرير مكون Livewire إلى الواجهة.
     */
    protected function getViewData(): array
    {
        return [
            'tagImporterComponent' => TagImporterComponent::class,
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            '/admin/tags' => 'الوسوم',
            static::getUrl() => static::getTitle(),
        ];
    }
}
