<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Invoice;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ClientInvoicesTable extends BaseWidget
{
    public Client $client;

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'الفواتير';

    protected int|string|array $columnSpan = 'full';

    public function mount(Client $client): void
    {
        $this->client = $client;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::where('client_id', $this->client->id)
                    ->with(['items', 'receipts', 'currency'])
            )
            ->emptyStateHeading('لا توجد فواتير لهذا العميل')
            ->emptyStateDescription('سيظهر سجل الفواتير هنا بعد إصدار أول فاتورة.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('رقم الفاتورة')
                    ->searchable()
                    ->color('info')
                    // ->size(TextColumnSize::ExtraSmall)
                    ->sortable(),
                Tables\Columns\TextColumn::make('issue_date')
                    ->label('تاريخ الإصدار')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('تاريخ الاستحقاق')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('المبلغ')
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.($record->currency?->symbol ?? $record->currency?->currency ?? 'ر.س'))
                    ->description(fn ($record) => $record->currency_id && $record->currency_id !== \App\Models\Currency::getBase()?->id && $record->exchange_rate
                        ? 'سعر الصرف: '.((float) $record->exchange_rate)
                        : null)
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining')
                    ->label('المتبقي')
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.($record->currency?->symbol ?? $record->currency?->currency ?? 'ر.س'))
                    ->color(fn ($state): string => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'مسودة',
                        'posted' => 'مستحقة',
                        'paid' => 'مدفوعة',
                        'cancelled' => 'ملغاة',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'posted' => 'warning',
                        'paid' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('عرض')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalWidth('Large')
                    ->modalContent(fn (Invoice $record) => view('filament.invoices.invoice-view', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق'),
                Tables\Actions\EditAction::make('edit')
                    ->label('تعديل')
                    ->url(fn (Invoice $record): string => \App\Filament\Resources\InvoiceResource::getUrl('edit', ['record' => $record])),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('إضافة فاتورة')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => \App\Filament\Resources\InvoiceResource::getUrl('create', ['client_id' => $this->client->id])),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('إضافة فاتورة')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => \App\Filament\Resources\InvoiceResource::getUrl('create', ['client_id' => $this->client->id])),
            ])
            ->defaultSort('issue_date', 'desc');
    }
}
