<?php

namespace App\Filament\Pages;

use App\Livewire\InvoiceImporterComponent;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class CustomInvoiceImport extends Page
{
    protected static ?string $slug = 'custom-invoice-import';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-on-square-stack';

    protected static string $view = 'filament.pages.custom-invoice-import';

    /**
     * عنوان الصفحة في المتصفح.
     */
    protected static ?string $title = 'استيراد الفواتير المتقدم';

    /**
     * عنوان القائمة الجانبية.
     */
    protected static ?string $navigationLabel = 'استيراد الفواتير المتقدم';

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

        return $user?->can('create_invoice') ?? false;
    }

    /**
     * تمرير مكوّن Livewire إلى الواجهة.
     */
    protected function getViewData(): array
    {
        return [
            'invoiceImporterComponent' => InvoiceImporterComponent::class,
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
                ->url(route('invoices.import.template')),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            '/admin/invoices' => 'الفواتير',
            static::getUrl() => static::getTitle(),
        ];
    }
}
