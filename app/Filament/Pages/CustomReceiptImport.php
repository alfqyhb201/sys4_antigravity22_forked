<?php

namespace App\Filament\Pages;

use App\Livewire\ReceiptImporterComponent;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class CustomReceiptImport extends Page
{
    protected static ?string $slug = 'custom-receipt-import';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-on-square-stack';

    protected static string $view = 'filament.pages.custom-receipt-import';

    /**
     * عنوان الصفحة في المتصفح.
     */
    protected static ?string $title = 'استيراد سندات القبض المتقدم';

    /**
     * عنوان القائمة الجانبية.
     */
    protected static ?string $navigationLabel = 'استيراد سندات القبض المتقدم';

    /**
     * مجموعة التنقل.
     */
    protected static ?string $navigationGroup = 'المالية';

    /**
     * ترتيب القائمة.
     */
    protected static ?int $navigationSort = 99;

    /**
     * إذن الوصول.
     */
    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->can('create_receipt') ?? false;
    }

    /**
     * تمرير مكوّن Livewire إلى الواجهة.
     */
    protected function getViewData(): array
    {
        return [
            'receiptImporterComponent' => ReceiptImporterComponent::class,
        ];
    }

    /**
     * إجراءات رأس الصفحة.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('تحميل نموذج Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(route('receipts.import.template')),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            '/admin/receipts' => 'سندات القبض',
            static::getUrl() => static::getTitle(),
        ];
    }
}
