<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('customImport')
                ->label('استيراد متقدم')
                ->icon('heroicon-o-arrow-up-on-square-stack')
                ->color('success')
                ->url(\App\Filament\Pages\CustomInvoiceImport::getUrl()),
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('جميع الفواتير'),
            'draft' => Tab::make('المسودة')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft')),
            'posted' => Tab::make('المستحقة')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'posted')),
            'paid' => Tab::make('المدفوعة')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid')),
            'cancelled' => Tab::make('الملغاة')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'cancelled')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'posted';
    }
}
