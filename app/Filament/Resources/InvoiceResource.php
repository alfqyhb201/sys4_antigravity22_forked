<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers\AdditionalDesignTasksRelationManager;
use App\Models\Invoice;
use App\Services\PaymentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'المالية';

    protected static ?string $modelLabel = 'فاتورة';

    protected static ?string $pluralModelLabel = 'الفواتير';

    protected static ?string $slug = 'invoices';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_any_invoice');
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()->can('view_invoice');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create_invoice');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can('update_invoice');
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->can('delete_invoice');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->can('delete_invoice');
    }

    public static function canRestoreAny(): bool
    {
        return auth()->user()->can('delete_invoice');
    }

    public static function canRestore(Model $record): bool
    {
        return auth()->user()->can('delete_invoice');
    }

    public static function canForceDeleteAny(): bool
    {
        return auth()->user()->can('delete_invoice');
    }

    public static function canForceDelete(Model $record): bool
    {
        return auth()->user()->can('delete_invoice');
    }

    public static function updateInvoiceTotal(Forms\Get $get, Forms\Set $set): void
    {
        $items = $get('items') ?? $get('../../items');
        $total = collect($items)->sum(fn ($item) => (float) ($item['total'] ?? 0));

        if ($get('items') !== null) {
            $set('total_amount', $total);
        } else {
            $set('../../total_amount', $total);
        }
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Section::make('معلومات الفاتورة')
                            ->schema([
                                Forms\Components\Select::make('client_id')
                                    ->label('العميل')
                                    ->relationship('client', 'company')
                                    ->default(fn () => request()->query('client_id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set) => $set('contract_id', null)),
                                Forms\Components\Select::make('contract_id')
                                    ->label('الاشتراك')
                                    ->relationship(
                                        'contract',
                                        'id',
                                        fn (Builder $query, Forms\Get $get) => $get('client_id')
                                            ? $query->where('client_id', $get('client_id'))
                                            : $query->whereRaw('1 = 0')
                                    )
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "[{$record->start_date->format('Y-m-d')}] - ({$record->total_amount} {$record->currency->symbol}) - ".match ($record->billing_cycle) {
                                        'weekly' => 'أسبوعي',
                                        'yearly' => 'سنوي',
                                        default => 'شهري',
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (Forms\Get $get) => empty($get('client_id')))
                                    ->placeholder(fn (Forms\Get $get) => empty($get('client_id')) ? 'يرجى اختيار العميل أولاً' : 'اختر الاشتراك...')
                                    ->nullable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $contract = \App\Models\Contract::find($state);
                                            if ($contract?->currency_id) {
                                                $set('currency_id', $contract->currency_id);
                                                // تحديث سعر الصرف تلقائياً
                                                $baseCurrency = \App\Models\Currency::getBase();
                                                if ($baseCurrency && $contract->currency_id !== $baseCurrency->id) {
                                                    $rate = app(\App\Services\CurrencyService::class)->getLatestRate($contract->currency_id, $baseCurrency->id);
                                                    $set('exchange_rate', $rate);
                                                } else {
                                                    $set('exchange_rate', 1.0);
                                                }
                                            }
                                        }
                                    }),
                                Forms\Components\Select::make('currency_id')
                                    ->label('العملة')
                                    ->relationship('currency', 'currency_name')
                                    ->default(fn () => \App\Models\Currency::getBase()?->id ?? \App\Models\Currency::first()?->id)
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $baseCurrency = \App\Models\Currency::getBase();
                                            if ($baseCurrency && (int) $state !== $baseCurrency->id) {
                                                $rate = app(\App\Services\CurrencyService::class)->getLatestRate((int) $state, $baseCurrency->id);
                                                $set('exchange_rate', $rate);
                                            } else {
                                                $set('exchange_rate', 1.0);
                                            }
                                        }
                                    }),
                                Forms\Components\TextInput::make('exchange_rate')
                                    ->label('سعر الصرف (← العملة الأساسية)')
                                    ->numeric()
                                    ->default(1.0)
                                    ->step(0.000001)
                                    ->helperText(fn () => 'سعر صرف عملة الفاتورة مقابل '.(\App\Models\Currency::getBase()?->currency_name ?? 'العملة الأساسية'))
                                    ->visible(fn (Forms\Get $get) => $get('currency_id') && (int) $get('currency_id') !== (\App\Models\Currency::getBase()?->id ?? 0)),
                                Forms\Components\TextInput::make('invoice_number')
                                    ->label('رقم الفاتورة')
                                    ->disabled()
                                    ->dehydrated(),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\DatePicker::make('issue_date')
                                            ->label('تاريخ الإصدار')
                                            ->required()
                                            ->default(now()),
                                        Forms\Components\DatePicker::make('due_date')
                                            ->label('تاريخ الاستحقاق')
                                            ->required()
                                            ->default(now()->addDays(30)),
                                    ]),
                                Forms\Components\Select::make('status')
                                    ->label('الحالة')
                                    ->options([
                                        'draft' => 'مسودة',
                                        'posted' => 'مستحقة',
                                        'paid' => 'مدفوعة',
                                        'cancelled' => 'ملغاة',
                                    ])
                                    ->required()
                                    ->default('posted'),
                                Forms\Components\TextInput::make('total_amount')
                                    ->label('إجمالي الفاتورة')
                                    ->numeric()
                                    ->readOnly()
                                    ->default(0)
                                    ->extraInputAttributes(['data-calc' => 'invoice-total']),
                                Forms\Components\Textarea::make('notes')
                                    ->label('ملاحظات')
                                    ->nullable(),
                            ])
                            ->columnSpan(1),
                        Forms\Components\Section::make('بنود الفاتورة')
                            ->schema([
                                Forms\Components\Repeater::make('items')
                                    ->relationship('items')
                                    ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set) => self::updateInvoiceTotal($get, $set))
                                    ->afterStateHydrated(fn (Forms\Get $get, Forms\Set $set) => self::updateInvoiceTotal($get, $set))
                                    ->schema([
                                        Forms\Components\TextInput::make('description')
                                            ->label('الوصف')
                                            ->required(),
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\TextInput::make('quantity')
                                                    ->label('الكمية')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->required()
                                                    ->extraInputAttributes([
                                                        'data-calc' => 'quantity',
                                                        'x-on:input' => '
                                                            let row = $el.closest(".fi-fo-repeater-item") || $el.closest(".grid");
                                                            let qty = parseFloat($el.value) || 0;
                                                            let unitEl = row?.querySelector("[data-calc=unit_amount]");
                                                            let totalEl = row?.querySelector("[data-calc=total]");
                                                            let unit = parseFloat(unitEl?.value) || 0;
                                                            if (totalEl) {
                                                                totalEl.value = (qty * unit).toFixed(2);
                                                                totalEl.dispatchEvent(new Event("input", { bubbles: true }));
                                                            }
                                                            let grandTotal = 0;
                                                            document.querySelectorAll("[data-calc=total]").forEach(el => {
                                                                grandTotal += parseFloat(el.value) || 0;
                                                            });
                                                            let invTotalEl = document.querySelector("[data-calc=invoice-total]");
                                                            if (invTotalEl) {
                                                                invTotalEl.value = grandTotal.toFixed(2);
                                                                invTotalEl.dispatchEvent(new Event("input", { bubbles: true }));
                                                            }
                                                        ',
                                                    ]),
                                                Forms\Components\TextInput::make('unit_amount')
                                                    ->label('سعر الوحدة')
                                                    ->numeric()
                                                    ->required()
                                                    ->extraInputAttributes([
                                                        'data-calc' => 'unit_amount',
                                                        'x-on:input' => '
                                                            let row = $el.closest(".fi-fo-repeater-item") || $el.closest(".grid");
                                                            let unit = parseFloat($el.value) || 0;
                                                            let qtyEl = row?.querySelector("[data-calc=quantity]");
                                                            let totalEl = row?.querySelector("[data-calc=total]");
                                                            let qty = parseFloat(qtyEl?.value) || 0;
                                                            if (totalEl) {
                                                                totalEl.value = (qty * unit).toFixed(2);
                                                                totalEl.dispatchEvent(new Event("input", { bubbles: true }));
                                                            }
                                                            let grandTotal = 0;
                                                            document.querySelectorAll("[data-calc=total]").forEach(el => {
                                                                grandTotal += parseFloat(el.value) || 0;
                                                            });
                                                            let invTotalEl = document.querySelector("[data-calc=invoice-total]");
                                                            if (invTotalEl) {
                                                                invTotalEl.value = grandTotal.toFixed(2);
                                                                invTotalEl.dispatchEvent(new Event("input", { bubbles: true }));
                                                            }
                                                        ',
                                                    ]),
                                                Forms\Components\TextInput::make('total')
                                                    ->label('الإجمالي')
                                                    ->numeric()
                                                    ->readOnly()
                                                    ->extraInputAttributes(['data-calc' => 'total']),
                                            ]),
                                    ])
                                    ->defaultItems(1)
                                    ->reorderable(false),
                            ])
                            ->columnSpan(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('رقم الفاتورة')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.company')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('issue_date')
                    ->label('تاريخ الإصدار')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('تاريخ الاستحقاق')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('المبلغ')
                    ->formatStateUsing(fn ($record) => number_format((float) $record->total_amount, 2).' '.($record->currency?->symbol ?? $record->currency?->currency ?? ''))
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency.currency_name')
                    ->label('العملة')
                    ->badge()
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client_id')
                    ->label('العميل')
                    ->relationship('client', 'company')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('issue_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('من تاريخ إصدار'),
                        Forms\Components\DatePicker::make('until')
                            ->label('إلى تاريخ إصدار'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issue_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'من تاريخ إصدار: '.Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'إلى تاريخ إصدار: '.Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
                // Tables\Filters\Filter::make('overdue')
                //     ->label('فواتير متأخرة السداد')
                //     ->query(fn (Builder $query): Builder => $query->where('status', 'posted')->whereDate('due_date', '<', now()))
                //     ->toggle(),
                Tables\Filters\SelectFilter::make('currency_id')
                    ->label('العملة')
                    ->relationship('currency', 'currency_name')
                    ->preload(),
                Tables\Filters\TrashedFilter::make()
                    ->label('السجلات المحذوفة'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(['sm' => 2, 'lg' => 4])
            ->actions([
                Tables\Actions\Action::make('viewInvoice')
                    ->label('عرض وطباعة')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->modalWidth(MaxWidth::FourExtraLarge)
                    ->modalContent(fn (Invoice $record) => view('filament.invoices.invoice-view', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق'),
                Tables\Actions\Action::make('applyAdvances')
                    ->label('تسوية من الدفعات المقدمة')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('success')
                    ->visible(fn (Invoice $record): bool => $record->status !== 'paid'
                        && $record->client !== null
                        && $record->client->total_advance_balance > 0
                        && $record->remaining > 0
                        && (auth()->user()?->can('create_receipt') || auth()->user()?->can('record_payment') || auth()->user()?->hasRole(['admin', 'super_admin', 'accountant'])))
                    ->requiresConfirmation()
                    ->modalHeading('تسوية الفاتورة من الدفعات المقدمة')
                    ->modalDescription(fn (Invoice $record): string => 'المتبقي من الفاتورة: '.number_format($record->remaining, 2).' '.($record->currency?->symbol ?? '').' | إجمالي الدفعات المقدمة المتاحة للعميل: '.number_format($record->client->total_advance_balance, 2).' هل تريد تطبيق الدفعات المقدمة لسداد هذه الفاتورة؟')
                    ->modalSubmitActionLabel('تأكيد التسوية')
                    ->action(function (Invoice $record) {
                        abort_unless(auth()->user()?->can('create_receipt') || auth()->user()?->can('record_payment') || auth()->user()?->hasRole(['admin', 'super_admin', 'accountant']), 403);

                        try {
                            $allocations = app(PaymentService::class)->autoAllocateAdvances($record);
                            $totalAllocated = number_format($allocations->sum('amount'), 2);
                            $currency = $record->currency?->symbol ?? $record->currency?->currency ?? '';

                            Notification::make()
                                ->title('✅ تمت التسوية بنجاح')
                                ->body("تم تخصيص {$totalAllocated} {$currency} من الدفعات المقدمة لصالح الفاتورة #{$record->invoice_number}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('❌ تعذر إجراء التسوية')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('printDebtorsReport')
                    ->label('تقرير مديونيات العملاء (PDF)')
                    ->icon('heroicon-o-printer')
                    ->color('danger')
                    ->url(route('reports.client-debtors'), shouldOpenInNewTab: true),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AdditionalDesignTasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->with(['items', 'currency', 'client', 'contract', 'receipts', 'allocations']);
    }
}
