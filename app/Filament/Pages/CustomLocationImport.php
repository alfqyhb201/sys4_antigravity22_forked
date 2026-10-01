<?php

namespace App\Filament\Pages;

use App\Livewire\LocationImporterComponent;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class CustomLocationImport extends Page
{
    protected static ?string $slug = 'custom-location-import';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-on-square-stack';

    protected static string $view = 'filament.pages.custom-location-import';

    protected static ?string $title = 'استيراد المواقع المتقدم';

    protected static ?string $navigationLabel = 'استيراد المواقع المتقدم';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 99;

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->can('create_location') ?? false;
    }

    protected function getViewData(): array
    {
        return [
            'locationImporterComponent' => LocationImporterComponent::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label('تحميل نموذج Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->url(route('locations.import.template')),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            '/admin/locations' => 'المواقع',
            static::getUrl() => static::getTitle(),
        ];
    }
}
