<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Invoice;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ClientStatementTable extends BaseWidget
{
    public Client $client;

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'كشف حساب مالي تفصيلي';

    protected int|string|array $columnSpan = 'full';

    public function mount(Client $client): void
    {
        $this->client = $client;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Invoice::query()->where('client_id', $this->client->id)->whereIn('status', ['posted', 'paid']))
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع الحركة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'invoice' ? 'فاتورة' : 'سند قبض')
                    ->color(fn ($state) => $state === 'invoice' ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('reference_number')
                    ->label('رقم المرجع')
                    ->searchable(),
                Tables\Columns\TextColumn::make('details')
                    ->label('البيان / التفاصيل')
                    ->weight('thin')
                    ->color('gray')
                    ->wrap(),
                Tables\Columns\TextColumn::make('debit')
                    ->label('مدين (فاتورة +)')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state, $record) => $state > 0 ? number_format((float) $state, 2).' '.($record->currency_symbol ?? 'ر.س') : '—')
                    ->description(fn ($record) => $record->debit > 0 && $record->is_foreign_currency ? '= '.number_format((float) $record->debit_base, 2).' '.$record->base_currency_symbol : null)
                    ->color('warning'),
                Tables\Columns\TextColumn::make('credit')
                    ->label('دائن (قبض -)')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state, $record) => $state > 0 ? number_format((float) $state, 2).' '.($record->currency_symbol ?? 'ر.س') : '—')
                    ->description(fn ($record) => $record->credit > 0 && $record->is_foreign_currency ? '= '.number_format((float) $record->credit_base, 2).' '.$record->base_currency_symbol : null)
                    ->color('success'),
                Tables\Columns\TextColumn::make('running_balance')
                    ->label('الرصيد المتبقي')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.($record->base_currency_symbol ?? 'ر.س'))
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
            ])
            ->recordUrl(null)
            ->recordAction(null)
            ->emptyStateHeading('لا توجد حركات مالية لهذا العميل')
            ->emptyStateDescription('ستظهر الفواتير الصادرة وسندات القبض هنا بالترتيب الزمني.')
            ->emptyStateIcon('heroicon-o-document-chart-bar');
    }

    public function getTableRecordKey($record): string
    {
        return (string) ($record->statement_id ?? ($record->type.'-'.$record->id));
    }

    public function getTableRecords(): \Illuminate\Database\Eloquent\Collection|\Illuminate\Contracts\Pagination\Paginator|\Illuminate\Contracts\Pagination\CursorPaginator
    {
        $data = app(\App\Services\FinancialDashboardService::class)->getClientStatementData($this->client);

        return new \Illuminate\Database\Eloquent\Collection($data['items']->all());
    }
}
