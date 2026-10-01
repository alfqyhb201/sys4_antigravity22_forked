<?php

namespace App\Filament\Resources\ReceiptResource\Pages;

use App\Filament\Resources\ReceiptResource;
use App\Models\Receipt;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListReceipts extends ListRecords
{
    protected static string $resource = ReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('customImport')
                ->label('استيراد متقدم')
                ->icon('heroicon-o-arrow-up-on-square-stack')
                ->color('success')
                ->url(\App\Filament\Pages\CustomReceiptImport::getUrl()),
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('جميع السندات')
                ->badge(fn () => Receipt::count()),
            'invoiced' => Tab::make('مسددة لفواتير')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('invoice_id'))
                ->badge(fn () => Receipt::whereNotNull('invoice_id')->count()),
            'advances_available' => Tab::make('دفعات مقدمة متاحة')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('invoice_id')->where('unallocated_amount', '>', 0))
                ->badge(fn () => Receipt::whereNull('invoice_id')->where('unallocated_amount', '>', 0)->count())
                ->badgeColor('success'),
            'advances_exhausted' => Tab::make('دفعات مستهلكة')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('invoice_id')->where('unallocated_amount', '<=', 0))
                ->badge(fn () => Receipt::whereNull('invoice_id')->where('unallocated_amount', '<=', 0)->count())
                ->badgeColor('gray'),
            'today' => Tab::make('مقبوضات اليوم')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('receipt_date', today()))
                ->badge(fn () => Receipt::whereDate('receipt_date', today())->count())
                ->badgeColor('warning'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
