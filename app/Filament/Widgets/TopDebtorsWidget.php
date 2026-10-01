<?php

namespace App\Filament\Widgets;

use App\Filament\Enums\PaymentGateway;
use App\Filament\Pages\ClientFinancialDetail;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\FinancialDashboardService;
use App\Services\PaymentService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopDebtorsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static string $view = 'filament.widgets.top-debtors-widget';

    protected static ?string $heading = 'ملخص العملاء المالي';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public string $activeTab = 'all';

    public ?array $cachedTabCounts = null;

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    public function getTabCounts(): array
    {
        if ($this->cachedTabCounts === null) {
            $this->cachedTabCounts = app(FinancialDashboardService::class)->getClientFinancialTabCounts();
        }

        return $this->cachedTabCounts;
    }

    protected function getViewData(): array
    {
        return [
            'tabCounts' => $this->getTabCounts(),
        ];
    }

    public function table(Table $table): Table
    {
        $today = now()->startOfDay();

        return $table
            ->query(function () use ($today): Builder {
                $query = app(FinancialDashboardService::class)->topDebtorsQuery();

                if ($this->activeTab === 'critical_overdue') {
                    $query->whereHas('invoices', function (Builder $q) use ($today) {
                        $q->where('status', 'posted')
                            ->where('due_date', '<', $today);
                    })->whereRaw('(
                        SELECT COALESCE(SUM(
                            CASE 
                                WHEN (
                                    COALESCE(base_currency_amount, total_amount) 
                                    - COALESCE((SELECT SUM(COALESCE(base_currency_amount, amount)) FROM receipts WHERE receipts.invoice_id = invoices.id AND receipts.deleted_at IS NULL), 0)
                                    - COALESCE((SELECT SUM(COALESCE(receipt_allocations.amount * COALESCE(invoices.exchange_rate, 1.0), receipt_allocations.amount)) FROM receipt_allocations JOIN receipts ON receipts.id = receipt_allocations.receipt_id WHERE receipt_allocations.invoice_id = invoices.id AND receipts.deleted_at IS NULL), 0)
                                ) < 0 THEN 0 
                                ELSE (
                                    COALESCE(base_currency_amount, total_amount) 
                                    - COALESCE((SELECT SUM(COALESCE(base_currency_amount, amount)) FROM receipts WHERE receipts.invoice_id = invoices.id AND receipts.deleted_at IS NULL), 0)
                                    - COALESCE((SELECT SUM(COALESCE(receipt_allocations.amount * COALESCE(invoices.exchange_rate, 1.0), receipt_allocations.amount)) FROM receipt_allocations JOIN receipts ON receipts.id = receipt_allocations.receipt_id WHERE receipt_allocations.invoice_id = invoices.id AND receipts.deleted_at IS NULL), 0)
                                )
                            END
                        ), 0)
                        FROM invoices
                        WHERE invoices.client_id = clients.id
                        AND invoices.status = "posted"
                        AND invoices.deleted_at IS NULL
                    ) > 0');
                } elseif ($this->activeTab === 'expiring_soon') {
                    $query->whereHas('currentContract', function (Builder $q) use ($today) {
                        $q->where('status', 'active')
                            ->whereBetween('end_date', [$today, $today->copy()->addDays(7)]);
                    });
                } elseif ($this->activeTab === 'expired_suspended') {
                    $query->where(function (Builder $q) use ($today) {
                        $q->whereHas('currentContract', function (Builder $contractQ) use ($today) {
                            $contractQ->where(function (Builder $sub) use ($today) {
                                $sub->where('end_date', '<', $today)
                                    ->orWhereIn('status', ['suspended', 'expired']);
                            });
                        })
                            ->orWhere('status', false);
                    });
                }

                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('company')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-m-building-office-2')
                    ->description(fn (Client $record) => $record->phone ? "📞 {$record->phone}" : null)
                    ->url(fn (Client $record): string => ClientFinancialDetail::getUrl(['client' => $record])),

                Tables\Columns\TextColumn::make('subscription_period')
                    ->label('فترة الاشتراك')
                    ->state(function (Client $record) {
                        $contract = $record->currentContract;
                        if (! $contract || ! $contract->start_date || ! $contract->end_date) {
                            return 'بدون اشتراك نشط';
                        }
                        $start = \Illuminate\Support\Carbon::parse($contract->start_date)->format('Y-m-d');
                        $end = \Illuminate\Support\Carbon::parse($contract->end_date)->format('Y-m-d');

                        return "{$start} ⬅️ {$end}";
                    })
                    ->description(function (Client $record) {
                        $contract = $record->currentContract;
                        if (! $contract || ! $contract->end_date) {
                            return null;
                        }
                        $today = now()->startOfDay();
                        $endDate = \Illuminate\Support\Carbon::parse($contract->end_date)->startOfDay();
                        $daysRemaining = (int) $today->diffInDays($endDate, false);

                        if ($daysRemaining < 0) {
                            $abs = abs($daysRemaining);

                            return "منتهي منذ {$abs} يوم";
                        }
                        if ($daysRemaining === 0) {
                            return 'ينتهي اليوم!';
                        }

                        return "متبقي {$daysRemaining} يوم";
                    })
                    ->badge()
                    ->color(function (Client $record): string {
                        $contract = $record->currentContract;
                        if (! $contract || ! $contract->end_date) {
                            return 'gray';
                        }
                        $today = now()->startOfDay();
                        $endDate = \Illuminate\Support\Carbon::parse($contract->end_date)->startOfDay();
                        $daysRemaining = (int) $today->diffInDays($endDate, false);

                        if ($daysRemaining < 0 || $daysRemaining <= 2) {
                            return 'danger';
                        }
                        if ($daysRemaining <= 7) {
                            return 'warning';
                        }
                        if ($daysRemaining <= 14) {
                            return 'info';
                        }

                        return 'success';
                    }),

                Tables\Columns\TextColumn::make('currentContract.total_amount')
                    ->label('مبلغ الاشتراك')
                    ->formatStateUsing(fn ($state, Client $record) => $state !== null ? number_format($state).' '.($record->currentContract?->currency?->symbol ?? $record->currentContract?->currency?->currency ?? 'ر.س') : '-')
                    ->badge()
                    ->color('gray')
                    ->weight(FontWeight::Bold)
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('activity_status')
                    ->label('حالة الحساب')
                    ->badge()
                    ->state(fn (Client $record): string => $record->activity_status)
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending_arrears' => 'warning',
                        'auto_suspended', 'suspended' => 'danger',
                        'manually_suspended', 'expired' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'نشط',
                        'pending_arrears' => 'نشط (متأخرات)',
                        'auto_suspended' => 'موقف تلقائياً',
                        'manually_suspended' => 'موقف يدوياً',
                        'suspended' => 'موقف',
                        'expired' => 'منتهي',
                        default => $state,
                    })
                    ->description(fn (Client $record) => $record->currentContract?->is_under_lawsuit ? '⚖️ إشعار قانوني / مقاضاة' : null)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('outstanding_balance_display')
                    ->label('الرصيد المتبقي')
                    ->state(fn (Client $record): float => (float) $record->outstanding_balance)
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2).' '.(\App\Models\Currency::getBase()?->symbol ?? \App\Models\Currency::getBase()?->currency ?? 'ر.س'))
                    ->description(function (Client $record): ?string {
                        $descriptions = [];
                        $advance = (float) ($record->advance_balance ?? 0);
                        if ($advance > 0) {
                            $baseSymbol = \App\Models\Currency::getBase()?->symbol ?? \App\Models\Currency::getBase()?->currency ?? 'ر.س';
                            $descriptions[] = 'مقدم غير مخصص: '.number_format($advance, 2).' '.$baseSymbol;
                        }

                        $contractCurrency = $record->currentContract?->currency;
                        $baseCurrency = \App\Models\Currency::getBase();
                        if ($contractCurrency && $baseCurrency && (int) $contractCurrency->id !== (int) $baseCurrency->id && (float) $record->outstanding_balance > 0) {
                            $invRemaining = $record->invoices->where('status', 'posted')->sum('remaining');
                            if ($invRemaining > 0) {
                                $descriptions[] = '≈ '.number_format($invRemaining, 2).' '.($contractCurrency->symbol ?? $contractCurrency->currency);
                            }
                        }

                        return ! empty($descriptions) ? implode(' | ', $descriptions) : null;
                    })
                    ->color(fn ($state): string => $state > 0 ? 'danger' : 'success')
                    ->badge()
                    ->weight(FontWeight::Bold)
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('last_payment_date')
                    ->label('آخر سداد')
                    ->date()
                    ->placeholder('لا يوجد')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('recordPayment')
                        ->label('سداد')
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->visible(fn (Client $record): bool => auth()->user()->can('record_payment') && $record->currentContract !== null)
                        ->modalHeading(fn (Client $record): string => 'تسجيل سداد - '.$record->company)
                        ->modalDescription('قم بتسجيل سداد العميل وتوزيعه تلقائياً على الفواتير المستحقة.')
                        ->modalSubmitActionLabel('تأكيد السداد')
                        ->modalWidth('fit')
                        ->fillForm(function (Client $record): array {
                            $contractCurrencyId = $record->currentContract?->currency_id ?? \App\Models\Currency::getBase()?->id;
                            $baseCurrency = \App\Models\Currency::getBase();
                            $currencyService = app(\App\Services\CurrencyService::class);
                            $rate = ($contractCurrencyId && $baseCurrency && (int) $contractCurrencyId !== (int) $baseCurrency->id)
                                ? $currencyService->getLatestRate((int) $contractCurrencyId, (int) $baseCurrency->id)
                                : 1.0;

                            return [
                                'paid_currency_id' => $contractCurrencyId,
                                'exchange_rate' => $rate,
                                'amount' => 0.0,
                                'discount' => 0.0,
                                'payment_method' => PaymentGateway::Cash->value,
                                'bank_account_id' => null,
                                'receipt_date' => now()->toDateString(),
                            ];
                        })
                        ->form(function (Client $record): array {
                            return [
                                Forms\Components\Section::make('بيانات العميل المالية (للاطلاع فقط)')
                                    ->icon('heroicon-o-information-circle')
                                    ->schema([
                                        Forms\Components\Placeholder::make('payment_type_label')
                                            ->label('نوع الدفع')
                                            ->content(fn () => $record->currentContract?->payment_type === 'advance' ? 'مقدم' : ($record->currentContract?->payment_type === 'deferred' ? 'مؤخر' : '-')),
                                        Forms\Components\Placeholder::make('currency')
                                            ->label('العملة')
                                            ->content(fn () => $record->currentContract?->currency?->currency_name ?? $record->currentContract?->currency?->currency ?? '-'),
                                        Forms\Components\Placeholder::make('designs_count')
                                            ->label('التصاميم الشهرية')
                                            ->content(fn () => ($record->currentContract?->monthly_designs_count ?? 0).' تصاميم/شهر'),
                                        Forms\Components\Placeholder::make('monthly_amount')
                                            ->label('المبلغ الشهري')
                                            ->content(fn () => number_format(($record->currentContract?->total_amount ?? 0) + ($record->currentContract?->marketing_amount ?? 0)).' '.($record->currentContract?->currency?->currency ?? '')),
                                        Forms\Components\Placeholder::make('total_owed')
                                            ->label('إجمالي المستحق')
                                            ->content(fn () => number_format($record->invoices()->where('status', 'posted')->get()->sum(fn ($inv) => $inv->remaining)).' '.($record->currentContract?->currency?->currency ?? '')),
                                        Forms\Components\Placeholder::make('last_payment')
                                            ->label('تاريخ آخر سداد')
                                            ->content(fn () => $record->last_payment_date?->format('Y-m-d') ?? 'لا يوجد'),
                                    ])
                                    ->columns(3),
                                Forms\Components\Section::make('تفاصيل السداد')
                                    ->icon('heroicon-o-credit-card')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Select::make('paid_currency_id')
                                                    ->label('عملة الدفع')
                                                    ->options(\App\Models\Currency::where('is_active', true)->pluck('currency_name', 'id'))
                                                    ->default(fn () => $record->currentContract?->currency_id ?? \App\Models\Currency::getBase()?->id)
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                        $baseCurrency = \App\Models\Currency::getBase();
                                                        if ($baseCurrency && $state && (int) $state !== (int) $baseCurrency->id) {
                                                            $rate = app(\App\Services\CurrencyService::class)->getLatestRate((int) $state, (int) $baseCurrency->id);
                                                            $set('exchange_rate', $rate);
                                                        } else {
                                                            $set('exchange_rate', 1.0);
                                                        }
                                                    }),
                                                Forms\Components\TextInput::make('exchange_rate')
                                                    ->label('سعر الصرف (← العملة الأساسية)')
                                                    ->numeric()
                                                    ->default(1.0)
                                                    ->step(0.000001)
                                                    ->required()
                                                    ->helperText(fn () => 'سعر صرف عملة الدفع مقابل '.(\App\Models\Currency::getBase()?->currency_name ?? 'العملة الأساسية')),
                                            ]),
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\TextInput::make('amount')
                                                    ->label('المبلغ المقبوض فعلياً (الصافي)')
                                                    ->numeric()
                                                    ->required()
                                                    ->minValue(0.01)
                                                    ->suffix(fn (Forms\Get $get) => \App\Models\Currency::find($get('paid_currency_id'))?->symbol ?? \App\Models\Currency::find($get('paid_currency_id'))?->currency ?? 'YER')
                                                    ->live(onBlur: true),
                                                Forms\Components\TextInput::make('discount')
                                                    ->label('الخصم المسموح به')
                                                    ->visible(fn () => auth()->user()->can('approve_discount'))
                                                    ->numeric()
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->suffix(fn (Forms\Get $get) => \App\Models\Currency::find($get('paid_currency_id'))?->symbol ?? \App\Models\Currency::find($get('paid_currency_id'))?->currency ?? 'YER')
                                                    ->live(onBlur: true),
                                                Forms\Components\Placeholder::make('total_settled_placeholder')
                                                    ->label('إجمالي المبلغ للتسوية')
                                                    ->content(fn (Forms\Get $get) => number_format((float) ($get('amount') ?? 0.0) + (float) ($get('discount') ?? 0.0), 2).' '.(\App\Models\Currency::find($get('paid_currency_id'))?->symbol ?? \App\Models\Currency::find($get('paid_currency_id'))?->currency ?? 'YER')),
                                            ]),
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Select::make('payment_method')
                                                    ->label('طريقة الدفع')
                                                    ->options(PaymentGateway::class)
                                                    ->default(PaymentGateway::Cash->value)
                                                    ->required()
                                                    ->live(),
                                                Forms\Components\Select::make('bank_account_id')
                                                    ->label('حساب الصراف / البنك المستلِم')
                                                    ->options(\App\Models\BankAccount::pluck('name', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->nullable(),
                                                Forms\Components\TextInput::make('reference_number')
                                                    ->label('رقم المرجع / الحوالة')
                                                    ->placeholder('رقم الحوالة أو المرجع')
                                                    ->visible(fn (Forms\Get $get): bool => $get('payment_method') !== null && $get('payment_method') !== PaymentGateway::Cash->value)
                                                    ->required(fn (Forms\Get $get): bool => $get('payment_method') !== null && $get('payment_method') !== PaymentGateway::Cash->value),
                                                Forms\Components\DatePicker::make('receipt_date')
                                                    ->label('تاريخ السداد')
                                                    ->default(now())
                                                    ->required(),
                                                Forms\Components\Textarea::make('notes')
                                                    ->label('ملاحظات')
                                                    ->placeholder('ملاحظات إضافية (اختياري)')
                                                    ->rows(2)
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ];
                        })
                        ->action(function (Client $record, array $data) {
                            $service = new PaymentService;
                            try {
                                $paymentData = [
                                    'paid_currency_id' => $data['paid_currency_id'] ?? $record->currentContract?->currency_id,
                                    'exchange_rate' => (float) ($data['exchange_rate'] ?? 1.0),
                                    'bank_account_id' => $data['bank_account_id'] ?? null,
                                    'amount' => (float) $data['amount'],
                                    'discount' => (float) ($data['discount'] ?? 0.0),
                                    'payment_method' => $data['payment_method'],
                                    'receipt_date' => $data['receipt_date'],
                                    'reference_number' => $data['reference_number'] ?? null,
                                    'notes' => $data['notes'] ?? null,
                                    'created_by_user' => auth()->id(),
                                    'updated_by_user' => auth()->id(),
                                ];
                                $receipts = $service->recordPaymentWithDiscount($record, $paymentData);
                                $invoiceNumbers = $receipts->map(fn ($r) => $r->invoice?->invoice_number ?? 'سند دفعة مقدمة')->filter()->unique()->join('، ');
                                $totalPaid = number_format($receipts->sum('amount'), 2);
                                $currency = \App\Models\Currency::find($paymentData['paid_currency_id'])?->currency ?? 'YER';
                                Notification::make()
                                    ->title('✅ تم السداد بنجاح')
                                    ->body("تم تسديد إجمالي {$totalPaid} {$currency}. الفواتير المستهدفة: {$invoiceNumbers}")
                                    ->success()
                                    ->duration(8000)
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('❌ خطأ في السداد')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    // Tables\Actions\Action::make('buyAdditionalDesigns')
                    //     ->label('شراء تصاميم إضافية')
                    //     ->icon('heroicon-o-paint-brush')
                    //     ->color('warning')
                    //     ->modalHeading(fn (Client $record): string => 'شراء تصاميم إضافية - '.$record->company)
                    //     ->modalDescription('قم بإصدار فاتورة شراء تصاميم إضافية جديدة للعميل وسدادها.')
                    //     ->modalSubmitActionLabel('تأكيد الشراء')
                    //     ->modalWidth('xl')
                    //     ->fillForm(fn (Client $record): array => [
                    //         'design_quantity' => 1,
                    //         'per_design_price' => (float) ($record->currentContract?->additional_design_price ?? 0.0),
                    //         'pay_from_wallet' => false,
                    //     ])
                    //     ->form(function (Client $record): array {
                    //         return [
                    //             Forms\Components\Section::make('معلومات المحفظة')
                    //                 ->compact()
                    //                 ->schema([
                    //                     Forms\Components\Placeholder::make('wallet_balance_placeholder')
                    //                         ->label('رصيد المحفظة المتاح')
                    //                         ->content(fn () => number_format($record->wallet_balance, 2).' '.($record->currentContract?->currency?->currency ?? \App\Models\Currency::getBase()?->currency ?? 'YER')),
                    //                     Forms\Components\Placeholder::make('current_additional_balance')
                    //                         ->label('رصيد التصاميم الإضافية الحالي')
                    //                         ->content(fn () => ($record->additional_designs_balance ?? 0).' تصاميم'),
                    //                 ])
                    //                 ->columns(2),
                    //             Forms\Components\Section::make('تفاصيل الشراء')
                    //                 ->compact()
                    //                 ->schema([
                    //                     Forms\Components\Grid::make(2)
                    //                         ->schema([
                    //                             Forms\Components\TextInput::make('design_quantity')
                    //                                 ->label('عدد التصاميم')
                    //                                 ->numeric()
                    //                                 ->required()
                    //                                 ->minValue(1)
                    //                                 ->default(1)
                    //                                 ->live(onBlur: true),
                    //                             Forms\Components\TextInput::make('per_design_price')
                    //                                 ->label('سعر التصميم الواحد')
                    //                                 ->numeric()
                    //                                 ->required()
                    //                                 ->minValue(0)
                    //                                 ->suffix($record->currentContract?->currency?->currency ?? 'YER')
                    //                                 ->live(onBlur: true),
                    //                         ]),
                    //                     Forms\Components\Placeholder::make('total_cost_placeholder')
                    //                         ->label('إجمالي القيمة')
                    //                         ->content(fn (Forms\Get $get) => number_format((int) ($get('design_quantity') ?? 1) * (float) ($get('per_design_price') ?? 0.0), 2).' '.($record->currentContract?->currency?->currency ?? \App\Models\Currency::getBase()?->currency ?? 'YER')),
                    //                     Forms\Components\Toggle::make('pay_from_wallet')
                    //                         ->label('السداد الفوري من محفظة العميل')
                    //                         ->helperText('سيتم خصم قيمة التصاميم من رصيد محفظة العميل النقدية تلقائياً.')
                    //                         ->default(false)
                    //                         ->visible(fn () => $record->wallet_balance > 0),
                    //                 ]),
                    //         ];
                    //     })
                    //     ->action(function (Client $record, array $data) {
                    //         $service = new PaymentService;
                    //         try {
                    //             $paymentData = [
                    //                 'design_quantity' => (int) $data['design_quantity'],
                    //                 'per_design_price' => (float) $data['per_design_price'],
                    //                 'pay_from_wallet' => (bool) ($data['pay_from_wallet'] ?? false),
                    //                 'created_by_user' => auth()->id(),
                    //                 'updated_by_user' => auth()->id(),
                    //             ];
                    //             $invoice = $service->buyAdditionalDesigns($record, $paymentData);

                    //             $msg = "تم إصدار الفاتورة رقم {$invoice->invoice_number} لشراء {$data['design_quantity']} تصاميم إضافية.";
                    //             if ($data['pay_from_wallet'] && $record->wallet_balance > 0) {
                    //                 $msg .= ' وتم سدادها تلقائياً من محفظة العميل.';
                    //             }

                    //             Notification::make()
                    //                 ->title('✅ تم شراء التصاميم بنجاح')
                    //                 ->body($msg)
                    //                 ->success()
                    //                 ->duration(8000)
                    //                 ->send();
                    //         } catch (\Exception $e) {
                    //             Notification::make()
                    //                 ->title('❌ خطأ في عملية الشراء')
                    //                 ->body($e->getMessage())
                    //                 ->danger()
                    //                 ->send();
                    //         }
                    //     })
                    //     ->visible(fn (Client $record): bool => $record->currentContract !== null),
                    Tables\Actions\Action::make('renewContract')
                        ->label('تجديد الاشتراك')
                        ->icon('heroicon-o-arrow-path-rounded-square')
                        ->color('success')
                        ->visible(fn (Client $record): bool => $record->currentContract !== null && (auth()->user()?->can('create_contract') || auth()->user()?->can('update_contract') || auth()->user()?->hasRole(['admin', 'super_admin', 'accountant'])))
                        ->requiresConfirmation()
                        ->modalHeading('تجديد الاشتراك وإصدار فاتورة جديدة')
                        ->modalDescription('هل أنت متأكد من رغبتك في تمديد الاشتراك وتوليد فاتورة الدورة الجديدة لهذا العميل؟')
                        ->form([
                            Forms\Components\DatePicker::make('new_start_date')
                                ->label('تاريخ بداية الاشتراك الجديد')
                                ->default(function (Client $record) {
                                    $contract = $record->currentContract;
                                    if (! $contract || ! $contract->end_date) {
                                        return now();
                                    }

                                    return $contract->end_date->copy();
                                })
                                ->minDate(function (Client $record) {
                                    return $record->currentContract?->end_date?->copy() ?? now();
                                })
                                ->live()
                                ->afterStateUpdated(function ($state, Forms\Set $set, Client $record) {
                                    if ($state) {
                                        $billingCycle = $record->currentContract?->billing_cycle ?? 'monthly';
                                        $set('new_end_date', \App\Models\Contract::calculateEndDate($state, $billingCycle)->format('Y-m-d'));
                                    }
                                })
                                ->required(),
                            Forms\Components\DatePicker::make('new_end_date')
                                ->label('تاريخ نهاية الاشتراك الجديد')
                                ->default(function (Client $record) {
                                    $contract = $record->currentContract;
                                    if (! $contract || ! $contract->end_date) {
                                        return now()->addMonth();
                                    }

                                    return \App\Models\Contract::calculateEndDate($contract->end_date, $contract->billing_cycle)->format('Y-m-d');
                                })
                                ->minDate(function (Forms\Get $get) {
                                    return $get('new_start_date') ?? now();
                                })
                                ->required(),
                        ])
                        ->action(function (Client $record, array $data) {
                            abort_unless(auth()->user()?->can('create_contract') || auth()->user()?->can('update_contract') || auth()->user()?->hasRole(['admin', 'super_admin', 'accountant']), 403);

                            $contract = $record->currentContract;
                            if ($contract) {
                                try {
                                    $newContract = $contract->createRenewalContract(
                                        auth()->id(),
                                        \Illuminate\Support\Carbon::parse($data['new_end_date']),
                                        \Illuminate\Support\Carbon::parse($data['new_start_date'])
                                    );
                                    $latestInvoice = Invoice::where('contract_id', $newContract->id)->latest()->first();
                                    $invoiceNumber = $latestInvoice ? $latestInvoice->invoice_number : 'N/A';
                                    Notification::make()
                                        ->title('تم التجديد بنجاح')
                                        ->body("تم إنشاء اشتراك جديد رقم {$newContract->id} وإصدار الفاتورة رقم: {$invoiceNumber}")
                                        ->success()
                                        ->send();
                                } catch (\Exception $e) {
                                    Notification::make()
                                        ->title('خطأ في التجديد')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            } else {
                                Notification::make()
                                    ->title('تنبيه')
                                    ->body('لا يوجد اشتراك نشط لهذا العميل ليتم تجديده.')
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->visible(fn (Client $record): bool => $record->currentContract !== null),
                    Tables\Actions\Action::make('view')
                        ->label('الملف المالي')
                        ->icon('heroicon-o-credit-card')
                        ->url(fn (Client $record): string => ClientFinancialDetail::getUrl(['client' => $record])),
                ])->label('إجراءات'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subscription_expiry')
                    ->label('تاريخ انتهاء الاشتراك')
                    ->options([
                        'expiring_2' => 'تنتهي خلال يومين',
                        'expiring_7' => 'تنتهي خلال 7 أيام',
                        'expiring_14' => 'تنتهي خلال 14 يوم',
                        'expired' => 'منتهية بالفعل',
                        'expiring_or_expired' => 'قرب الانتهاء أو منتهية',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (! $value) {
                            return $query;
                        }

                        $today = now()->startOfDay();

                        return match ($value) {
                            'expiring_2' => $query->whereHas('currentContract', fn (Builder $q) => $q->whereBetween('end_date', [$today, $today->copy()->addDays(2)])),
                            'expiring_7' => $query->whereHas('currentContract', fn (Builder $q) => $q->whereBetween('end_date', [$today, $today->copy()->addDays(7)])),
                            'expiring_14' => $query->whereHas('currentContract', fn (Builder $q) => $q->whereBetween('end_date', [$today, $today->copy()->addDays(14)])),
                            'expired' => $query->whereHas('currentContract', fn (Builder $q) => $q->where('end_date', '<', $today)),
                            'expiring_or_expired' => $query->whereHas('currentContract', fn (Builder $q) => $q->where('end_date', '<=', $today->copy()->addDays(30))),
                            default => $query,
                        };
                    }),
            ])
            ->defaultSort('outstanding_balance', 'desc');
    }
}
