<?php

namespace App\Filament\Pages;

use App\Models\Client;
use App\Services\FinancialDashboardService;
use Filament\Pages\Page;

class ClientFinancialDetail extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $slug = 'client/{client}/financial';

    protected static string $view = 'filament.pages.client-financial-detail';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'الملف المالي';

    public Client $client;

    /**
     * التحقق من صلاحية وصول المستخدم للملف المالي للعميل.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin'])
            || $user->can('view_client_financial');
    }

    public function mount(Client $client): void
    {
        abort_unless(static::canAccess(), 403, 'غير مصرح لك بالاطلاع على الملف المالي للعميل.');

        $this->client = app(FinancialDashboardService::class)->prepareClientFinancialProfile($client);
    }

    public function getBreadcrumbs(): array
    {
        return [
            AccountingDashboard::getUrl() => 'لوحة التحكم المالية',
            '#' => $this->client->company,
        ];
    }
}
