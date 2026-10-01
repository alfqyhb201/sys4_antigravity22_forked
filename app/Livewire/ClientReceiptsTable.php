<?php

namespace App\Livewire;

use App\Filament\Enums\PaymentGateway;
use App\Models\Client;
use App\Models\Receipt;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ClientReceiptsTable extends BaseWidget
{
    public Client $client;

    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'سندات القبض';

    protected int|string|array $columnSpan = 'full';

    public function mount(Client $client): void
    {
        $this->client = $client;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Receipt::where('client_id', $this->client->id)
                    ->with(['invoice', 'paidCurrency'])
            )
            ->emptyStateHeading('لا توجد سندات قبض لهذا العميل')
            ->emptyStateDescription('ستظهر هنا كل عمليات السداد المرتبطة بالعميل.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->columns([
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('رقم الفاتورة')
                    ->placeholder('سند دفعة مقدمة')
                    ->searchable()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->weight('black')
                    ->state(fn ($record) => $record->original_amount ?? $record->amount)
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.($record->paidCurrency?->symbol ?? $record->paidCurrency?->currency ?? 'ر.س'))
                    ->description(fn ($record) => $record->paid_currency_id && $record->paid_currency_id !== \App\Models\Currency::getBase()?->id && $record->exchange_rate
                        ? 'سعر الصرف: '.((float) $record->exchange_rate)
                        : null)
                    ->sortable(),
                Tables\Columns\TextColumn::make('unallocated_amount')
                    ->label('حالة التخصيص')
                    ->badge()
                    ->state(function (Receipt $record) {
                        if ($record->invoice_id !== null) {
                            return 'مسددة لفاتورة';
                        }
                        if ((float) $record->unallocated_amount > 0) {
                            $currency = $record->paidCurrency?->symbol ?? $record->paidCurrency?->currency ?? '';

                            return 'متاح: '.number_format((float) $record->unallocated_amount, 2).' '.$currency;
                        }

                        return 'مخصصة بالكامل';
                    })
                    ->color(fn (Receipt $record) => match (true) {
                        $record->invoice_id !== null => 'primary',
                        (float) $record->unallocated_amount > 0 => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('receipt_date')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->formatStateUsing(fn (string $state): string => PaymentGateway::tryFrom($state)?->getLabel() ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('reference_number')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('رقم المرجع'),
                Tables\Columns\TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->placeholder('-')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('عرض')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalWidth('Large')
                    ->modalContent(fn (Receipt $record) => view('filament.receipts.receipt-view', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق'),
                Tables\Actions\EditAction::make('edit')
                    ->label('تعديل')
                    ->url(fn (Receipt $record): string => \App\Filament\Resources\ReceiptResource::getUrl('edit', ['record' => $record])),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('إضافة سند قبض')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => \App\Filament\Resources\ReceiptResource::getUrl('create', ['client_id' => $this->client->id])),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('إضافة سند قبض')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => \App\Filament\Resources\ReceiptResource::getUrl('create', ['client_id' => $this->client->id])),
            ])
            ->defaultSort('receipt_date', 'desc');
    }
}
