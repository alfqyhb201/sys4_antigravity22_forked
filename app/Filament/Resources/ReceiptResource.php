<?php

namespace App\Filament\Resources;

use App\Filament\Enums\PaymentGateway;
use App\Filament\Resources\ReceiptResource\Pages;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Services\CurrencyService;
use App\Services\PaymentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReceiptResource extends Resource
{
    protected static ?string $model = Receipt::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'المالية';

    protected static ?string $modelLabel = 'سند قبض';

    protected static ?string $pluralModelLabel = 'سندات القبض';

    protected static ?string $slug = 'receipts';

    protected static ?string $recordTitleAttribute = 'id';

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_any_receipt');
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()->can('view_receipt');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('create_receipt');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can('update_receipt');
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->can('delete_receipt');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->can('delete_receipt');
    }

    public static function canRestoreAny(): bool
    {
        return auth()->user()->can('delete_receipt');
    }

    public static function canRestore(Model $record): bool
    {
        return auth()->user()->can('delete_receipt');
    }

    public static function canForceDeleteAny(): bool
    {
        return auth()->user()->can('delete_receipt');
    }

    public static function canForceDelete(Model $record): bool
    {
        return auth()->user()->can('delete_receipt');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Section::make('معلومات العميل والفاتورة')
                            ->schema([
                                Forms\Components\Select::make('client_id')
                                    ->label('العميل')
                                    ->relationship('client', 'company')
                                    ->default(fn () => request()->query('client_id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Forms\Set $set) => $set('invoice_id', null)),
                                Forms\Components\Select::make('invoice_id')
                                    ->label('الفاتورة')
                                    ->relationship(
                                        'invoice',
                                        'invoice_number',
                                        fn (\Illuminate\Database\Eloquent\Builder $query, Forms\Get $get) => $get('client_id')
                                            ? $query->where('client_id', $get('client_id'))
                                            : $query->whereRaw('1 = 0')
                                    )
                                    ->getOptionLabelFromRecordUsing(function ($record) {
                                        $statusLabel = match ($record->status) {
                                            'draft' => 'مسودة',
                                            'posted' => 'مستحقة',
                                            'paid' => 'مدفوعة',
                                            'cancelled' => 'ملغاة',
                                            default => $record->status,
                                        };
                                        $currency = $record->currency?->symbol ?? $record->currency?->currency ?? '';

                                        return "{$record->invoice_number} - ({$record->total_amount} {$currency}) - [{$statusLabel}]";
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (Forms\Get $get) => empty($get('client_id')))
                                    ->placeholder(fn (Forms\Get $get) => empty($get('client_id')) ? 'يرجى اختيار العميل أولاً' : 'إيداع محفظة (بدون فاتورة)')
                                    ->nullable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                        if ($state) {
                                            $invoice = Invoice::find($state);
                                            if ($invoice?->currency_id) {
                                                $paidCurrencyId = (int) ($get('paid_currency_id') ?? 0);
                                                if (! $paidCurrencyId) {
                                                    $set('paid_currency_id', $invoice->currency_id);
                                                    $paidCurrencyId = $invoice->currency_id;
                                                }

                                                $baseCurrency = \App\Models\Currency::getBase();
                                                if ($baseCurrency && $paidCurrencyId !== $baseCurrency->id) {
                                                    $rate = app(CurrencyService::class)->getLatestRate($paidCurrencyId, $baseCurrency->id);
                                                    $set('exchange_rate', $rate);
                                                } else {
                                                    $set('exchange_rate', 1.0);
                                                }

                                                if ($paidCurrencyId !== (int) $invoice->currency_id) {
                                                    $crossRate = app(CurrencyService::class)->getLatestRate($paidCurrencyId, $invoice->currency_id);
                                                    $set('conversion_rate', $crossRate);
                                                    $orig = (float) ($get('original_amount') ?? 0);
                                                    if ($orig > 0) {
                                                        $set('amount', round($orig * $crossRate, 2));
                                                    }
                                                } else {
                                                    $set('conversion_rate', 1.0);
                                                    $orig = (float) ($get('original_amount') ?? 0);
                                                    if ($orig > 0) {
                                                        $set('amount', $orig);
                                                    }
                                                }
                                            }
                                        }
                                    }),
                                Forms\Components\Select::make('paid_currency_id')
                                    ->label('عملة الدفع')
                                    ->relationship('paidCurrency', 'currency_name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                        if (! $state) {
                                            return;
                                        }
                                        $baseCurrency = \App\Models\Currency::getBase();
                                        if ($baseCurrency && (int) $state !== $baseCurrency->id) {
                                            $rate = app(CurrencyService::class)->getLatestRate((int) $state, $baseCurrency->id);
                                            $set('exchange_rate', $rate);
                                        } else {
                                            $set('exchange_rate', 1.0);
                                        }

                                        $invoiceId = $get('invoice_id');
                                        if ($invoiceId) {
                                            $invoice = Invoice::find($invoiceId);
                                            if ($invoice && (int) $invoice->currency_id !== (int) $state) {
                                                $crossRate = app(CurrencyService::class)->getLatestRate((int) $state, $invoice->currency_id);
                                                $set('conversion_rate', $crossRate);
                                                $orig = (float) ($get('original_amount') ?? 0);
                                                if ($orig > 0) {
                                                    $set('amount', round($orig * $crossRate, 2));
                                                }
                                            } else {
                                                $set('conversion_rate', 1.0);
                                                $orig = (float) ($get('original_amount') ?? 0);
                                                if ($orig > 0) {
                                                    $set('amount', $orig);
                                                }
                                            }
                                        }
                                    }),
                            ])
                            ->columnSpan(1),
                        Forms\Components\Section::make('تفاصيل الدفع')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('original_amount')
                                            ->label(function (Forms\Get $get) {
                                                $currency = $get('paid_currency_id') ? \App\Models\Currency::find($get('paid_currency_id')) : null;
                                                $symbol = $currency ? " ({$currency->symbol})" : '';
                                                $invoiceId = $get('invoice_id');
                                                $invoice = $invoiceId ? Invoice::find($invoiceId) : null;
                                                $isDiff = $invoice && $get('paid_currency_id') && (int) $invoice->currency_id !== (int) $get('paid_currency_id');

                                                return $isDiff ? "المبلغ المدفوع{$symbol}" : "المبلغ{$symbol}";
                                            })
                                            ->numeric()
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                                $paidCurrencyId = (int) ($get('paid_currency_id') ?? 0);
                                                $invoiceId = $get('invoice_id');
                                                $invoice = $invoiceId ? Invoice::find($invoiceId) : null;
                                                $invoiceCurrencyId = $invoice?->currency_id;

                                                if ($state && $paidCurrencyId && $invoiceCurrencyId && $paidCurrencyId !== $invoiceCurrencyId) {
                                                    $rate = (float) ($get('conversion_rate') ?? 0);
                                                    if ($rate <= 0) {
                                                        $rate = app(CurrencyService::class)->getLatestRate($paidCurrencyId, $invoiceCurrencyId);
                                                        $set('conversion_rate', $rate);
                                                    }
                                                    $set('amount', round((float) $state * $rate, 2));
                                                } elseif ($state) {
                                                    $set('amount', (float) $state);
                                                }
                                            }),
                                        Forms\Components\TextInput::make('conversion_rate')
                                            ->label(function (Forms\Get $get) {
                                                $paidCurrency = $get('paid_currency_id') ? \App\Models\Currency::find($get('paid_currency_id')) : null;
                                                $paidSymbol = $paidCurrency?->symbol ?? 'عملة الدفع';
                                                $invoice = $get('invoice_id') ? Invoice::find($get('invoice_id')) : null;
                                                $invSymbol = $invoice?->currency?->symbol ?? 'عملة الفاتورة';

                                                return "سعر التحويل (1 {$paidSymbol} = ? {$invSymbol})";
                                            })
                                            ->helperText(function (Forms\Get $get) {
                                                $paidCurrency = $get('paid_currency_id') ? \App\Models\Currency::find($get('paid_currency_id')) : null;
                                                $paidSymbol = $paidCurrency?->symbol ?? '';
                                                $invoice = $get('invoice_id') ? Invoice::find($get('invoice_id')) : null;
                                                $invSymbol = $invoice?->currency?->symbol ?? '';

                                                return "سعر تحويل {$paidSymbol} إلى {$invSymbol} المعتمد لهذا السند";
                                            })
                                            ->numeric()
                                            ->step(0.000001)
                                            ->live(onBlur: true)
                                            ->dehydrated(false)
                                            ->visible(function (Forms\Get $get) {
                                                $invoiceId = $get('invoice_id');
                                                $paidCurrencyId = (int) ($get('paid_currency_id') ?? 0);
                                                if (! $invoiceId || ! $paidCurrencyId) {
                                                    return false;
                                                }
                                                $invoice = Invoice::find($invoiceId);

                                                return $invoice && (int) $invoice->currency_id !== $paidCurrencyId;
                                            })
                                            ->afterStateHydrated(function (Forms\Get $get, Forms\Set $set) {
                                                $paidCurrencyId = (int) ($get('paid_currency_id') ?? 0);
                                                $invoiceId = $get('invoice_id');
                                                $invoice = $invoiceId ? Invoice::find($invoiceId) : null;
                                                if ($invoice && (int) $invoice->currency_id !== $paidCurrencyId) {
                                                    $orig = (float) ($get('original_amount') ?? 0);
                                                    $amt = (float) ($get('amount') ?? 0);
                                                    if ($orig > 0 && $amt > 0) {
                                                        $set('conversion_rate', round($amt / $orig, 6));
                                                    } else {
                                                        $rate = app(CurrencyService::class)->getLatestRate($paidCurrencyId, $invoice->currency_id);
                                                        $set('conversion_rate', $rate);
                                                    }
                                                }
                                            })
                                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                                $orig = (float) ($get('original_amount') ?? 0);
                                                $rate = (float) $state;
                                                if ($orig > 0 && $rate > 0) {
                                                    $set('amount', round($orig * $rate, 2));
                                                }
                                            }),
                                        Forms\Components\TextInput::make('amount')
                                            ->label(function (Forms\Get $get) {
                                                $invoice = $get('invoice_id') ? Invoice::find($get('invoice_id')) : null;
                                                $symbol = $invoice?->currency?->symbol ? " ({$invoice->currency->symbol})" : '';

                                                return "المبلغ المُسدد من الفاتورة{$symbol}";
                                            })
                                            ->numeric()
                                            ->helperText('المبلغ الذي سيُخصم فعلياً من رصيد الفاتورة بعد التحويل')
                                            ->visible(function (Forms\Get $get) {
                                                $invoiceId = $get('invoice_id');
                                                $paidCurrencyId = (int) ($get('paid_currency_id') ?? 0);
                                                if (! $invoiceId || ! $paidCurrencyId) {
                                                    return false;
                                                }
                                                $invoice = Invoice::find($invoiceId);

                                                return $invoice && (int) $invoice->currency_id !== $paidCurrencyId;
                                            })
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Forms\Get $get, Forms\Set $set) {
                                                $orig = (float) ($get('original_amount') ?? 0);
                                                $amt = (float) $state;
                                                if ($orig > 0 && $amt > 0) {
                                                    $set('conversion_rate', round($amt / $orig, 6));
                                                }
                                            })
                                            ->dehydrated(),
                                        Forms\Components\TextInput::make('exchange_rate')
                                            ->label(function () {
                                                $baseName = \App\Models\Currency::getBase()?->currency_name ?? 'العملة الأساسية';

                                                return "سعر صرف الدفع (← {$baseName})";
                                            })
                                            ->numeric()
                                            ->default(1.0)
                                            ->step(0.000001)
                                            ->helperText(function (Forms\Get $get) {
                                                $paidCurrency = $get('paid_currency_id') ? \App\Models\Currency::find($get('paid_currency_id')) : null;
                                                $paidName = $paidCurrency?->currency_name ?? 'عملة الدفع';
                                                $baseName = \App\Models\Currency::getBase()?->currency_name ?? 'العملة الأساسية';

                                                return "سعر صرف {$paidName} مقابل {$baseName} لإثبات القيد المحاسبي بالدفاتر";
                                            })
                                            ->visible(fn (Forms\Get $get) => $get('paid_currency_id') && (int) $get('paid_currency_id') !== (\App\Models\Currency::getBase()?->id ?? 0)),
                                        Forms\Components\DatePicker::make('receipt_date')
                                            ->label('تاريخ السند')
                                            ->required()
                                            ->default(now()),
                                        Forms\Components\Select::make('payment_method')
                                            ->label('طريقة الدفع')
                                            ->options(PaymentGateway::class)
                                            ->required(),
                                        Forms\Components\Select::make('bank_account_id')
                                            ->label('حساب الصراف / البنك المستلِم')
                                            ->relationship('bankAccount', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('اسم الحساب / الصراف')
                                                    ->placeholder('مثال: الكريمي - حساب صنعاء')
                                                    ->required()
                                                    ->maxLength(255),
                                            ])
                                            ->createOptionUsing(function (array $data) {
                                                return \App\Models\BankAccount::create([
                                                    'name' => $data['name'],
                                                    'created_by_user' => auth()->id(),
                                                    'updated_by_user' => auth()->id(),
                                                ])->getKey();
                                            })
                                            ->nullable(),
                                        Forms\Components\TextInput::make('reference_number')
                                            ->label('رقم المرجع / الإشعار')
                                            ->nullable(),
                                    ]),
                                Forms\Components\Textarea::make('notes')
                                    ->label('ملاحظات')
                                    ->nullable()
                                    ->columnSpanFull(),
                            ])
                            ->columnSpan(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('receipt_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'client',
                'invoice.currency',
                'paidCurrency',
                'bankAccount',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('client.company')
                    ->label('العميل والسند')
                    ->weight('bold')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search) {
                            $q->where('reference_number', 'like', "%{$search}%")
                                ->orWhere('notes', 'like', "%{$search}%")
                                ->orWhereHas('client', function (Builder $clientQ) use ($search) {
                                    $clientQ->where('company', 'like', "%{$search}%")
                                        ->orWhere('client_name', 'like', "%{$search}%")
                                        ->orWhere('contact_number', 'like', "%{$search}%");
                                })
                                ->orWhereHas('invoice', function (Builder $invQ) use ($search) {
                                    $invQ->where('invoice_number', 'like', "%{$search}%");
                                });
                        });
                    })
                    ->description(function (Receipt $record) {
                        if ($record->invoice) {
                            return '📄 فاتورة #'.$record->invoice->invoice_number;
                        }

                        return '💼 دفعة مقدمة (محفظة)';
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ والحالة')
                    ->weight('bold')
                    ->formatStateUsing(function (Receipt $record) {
                        $currency = $record->invoice?->currency?->symbol
                            ?? $record->invoice?->currency?->currency
                            ?? $record->paidCurrency?->symbol
                            ?? '';

                        return number_format((float) $record->amount, 2).' '.$currency;
                    })
                    ->description(function (Receipt $record) {
                        if ($record->invoice_id !== null) {
                            return new \Illuminate\Support\HtmlString('<span class="inline-flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-primary-500"></span> مسددة لفاتورة</span>');
                        }
                        if ((float) $record->unallocated_amount > 0) {
                            $currency = $record->paidCurrency?->symbol ?? $record->paidCurrency?->currency ?? '';

                            return new \Illuminate\Support\HtmlString('<span class="inline-flex items-center gap-1 text-xs text-success-600 dark:text-success-400 font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-success-500"></span> دفعة مقدمة (متاح: '.number_format((float) $record->unallocated_amount, 2).' '.$currency.')</span>');
                        }

                        return new \Illuminate\Support\HtmlString('<span class="inline-flex items-center gap-1 text-xs text-gray-500 font-medium"><span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> دفعة مقدمة (مخصصة بالكامل)</span>');
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('طريقة الدفع والحساب')
                    ->formatStateUsing(fn (string $state): string => PaymentGateway::tryFrom($state)?->getLabel() ?? $state)
                    ->badge()
                    ->description(function (Receipt $record) {
                        $parts = [];
                        if ($record->bankAccount?->name) {
                            $parts[] = '🏦 '.$record->bankAccount->name;
                        }
                        if ($record->reference_number) {
                            $parts[] = 'مرجع: '.$record->reference_number;
                        }

                        return count($parts) ? implode(' | ', $parts) : null;
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('receipt_date')
                    ->label('التاريخ')
                    ->date('Y/m/d')
                    ->description(fn (Receipt $record) => $record->receipt_date?->diffForHumans())
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('receipt_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('من تاريخ'),
                        Forms\Components\DatePicker::make('until')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('receipt_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('receipt_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'من تاريخ: '.\Illuminate\Support\Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'إلى تاريخ: '.\Illuminate\Support\Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
                Tables\Filters\SelectFilter::make('client_id')
                    ->label('العميل')
                    ->relationship('client', 'company')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('allocation_status')
                    ->label('نوع السند / التخصيص')
                    ->options([
                        'invoiced' => 'مسدد لفاتورة',
                        'advance_available' => 'دفعة مقدمة (متاح رصيد)',
                        'advance_exhausted' => 'دفعة مقدمة (مستهلكة بالكامل)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'invoiced' => $query->whereNotNull('invoice_id'),
                            'advance_available' => $query->whereNull('invoice_id')->where('unallocated_amount', '>', 0),
                            'advance_exhausted' => $query->whereNull('invoice_id')->where('unallocated_amount', '<=', 0),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('bank_account_id')
                    ->label('الصراف / الحساب المستلِم')
                    ->relationship('bankAccount', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('paid_currency_id')
                    ->label('عملة الدفع')
                    ->relationship('paidCurrency', 'currency_name')
                    ->preload(),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options(PaymentGateway::class),
                Tables\Filters\TrashedFilter::make()
                    ->label('السجلات المحذوفة'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(['sm' => 2, 'lg' => 4])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('allocateToInvoice')
                        ->label('تخصيص على فاتورة')
                        ->icon('heroicon-o-arrow-right-end-on-rectangle')
                        ->color('success')
                        ->visible(fn (Receipt $record): bool => $record->invoice_id === null
                            && (float) $record->unallocated_amount > 0
                            && (auth()->user()?->can('create_receipt') || auth()->user()?->can('record_payment') || auth()->user()?->hasRole(['admin', 'super_admin', 'accountant'])))
                        ->form(function (Receipt $record) {
                            return [
                                Forms\Components\Select::make('invoice_id')
                                    ->label('الفاتورة المستحقة')
                                    ->options(function () use ($record) {
                                        return $record->client?->invoices()
                                            ->where('status', 'posted')
                                            ->get()
                                            ->filter(fn ($inv) => $inv->remaining > 0)
                                            ->mapWithKeys(function ($inv) {
                                                $curr = $inv->currency?->symbol ?? $inv->currency?->currency ?? '';

                                                return [$inv->id => "{$inv->invoice_number} (المتبقي: {$inv->remaining} {$curr})"];
                                            }) ?? [];
                                    })
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) use ($record) {
                                        if ($state) {
                                            $invoice = Invoice::find($state);
                                            if ($invoice) {
                                                $set('amount', min((float) $record->unallocated_amount, (float) $invoice->remaining));
                                            }
                                        }
                                    }),
                                Forms\Components\TextInput::make('amount')
                                    ->label('المبلغ المراد تخصيصه (بعملة الفاتورة)')
                                    ->numeric()
                                    ->required(),
                            ];
                        })
                        ->action(function (Receipt $record, array $data) {
                            abort_unless(auth()->user()?->can('create_receipt') || auth()->user()?->can('record_payment') || auth()->user()?->hasRole(['admin', 'super_admin', 'accountant']), 403);

                            $invoice = Invoice::findOrFail($data['invoice_id']);
                            $amount = (float) $data['amount'];
                            try {
                                app(PaymentService::class)->allocateReceiptToInvoice($record, $invoice, $amount);
                                Notification::make()
                                    ->title('✅ تم التخصيص بنجاح')
                                    ->body("تم تخصيص {$amount} لصالح الفاتورة #{$invoice->invoice_number}")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('❌ تعذر التخصيص')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                    Tables\Actions\ForceDeleteAction::make(),
                ]),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReceipts::route('/'),
            'create' => Pages\CreateReceipt::route('/create'),
            'edit' => Pages\EditReceipt::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
