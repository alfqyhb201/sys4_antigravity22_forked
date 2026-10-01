<?php

namespace App\Filament\Pages;

use App\Livewire\ClientImporterComponent;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class CustomClientImport extends Page
{
    protected static ?string $slug = 'custom-client-import';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-on-square-stack';

    protected static string $view = 'filament.pages.custom-client-import';

    /**
     * عنوان الصفحة في المتصفح.
     */
    protected static ?string $title = 'استيراد العملاء';

    /**
     * عنوان القائمة الجانبية.
     */
    protected static ?string $navigationLabel = 'استيراد العملاء';

    /**
     * مجموعة التنقل.
     */
    protected static ?string $navigationGroup = 'CRM';

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

        return $user?->can('create_client') ?? false;
    }

    /**
     * تمرير مكوّن Livewire إلى الواجهة.
     */
    protected function getViewData(): array
    {
        return [
            'clientImporterComponent' => ClientImporterComponent::class,
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
                ->url(route('clients.import.template')),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            '/admin/clients' => 'العملاء',
            static::getUrl() => static::getTitle(),
        ];
    }
}
