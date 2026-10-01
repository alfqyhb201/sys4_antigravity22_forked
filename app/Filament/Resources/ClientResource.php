<?php

namespace App\Filament\Resources;

use App\Filament\Components\UserTrackingSection;
use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * مورد Filament لإدارة العملاء (Clients).
 *
 * يوفر هذا المورد واجهة متكاملة لإنشاء وعرض وتعديل وحذف بيانات العملاء،
 * بما في ذلك معلوماتهم الأساسية، الاشتراكات، التقييمات، وغيرها.
 */
class ClientResource extends Resource
{
    /**
     * نموذج Eloquent المرتبط بهذا المورد.
     */
    protected static ?string $model = Client::class;

    /**
     * أيقونة التنقل للمورد.
     */
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    /**
     * مجموعة التنقل التي ينتمي إليها المورد.
     */
    protected static ?string $navigationGroup = 'CRM';

    /**
     * اسم المورد في قائمة التنقل.
     */
    protected static ?string $navigationLabel = 'العملاء';

    /**
     * اسم النموذج بصيغة الجمع.
     */
    protected static ?string $pluralModelLabel = 'العملاء';

    /**
     * الرابط الثابت (slug) للمورد.
     */
    protected static ?string $slug = 'clients';

    /**
     * السمة المستخدمة كعنوان للسجل.
     */
    protected static ?string $recordTitleAttribute = 'company';

    protected static ?string $modelLabel = 'العميل';

    /**
     * يقوم بتعريف حقول النموذج (Form) لإنشاء وتعديل العملاء.
     *
     * @param  \Filament\Forms\Form  $form  نموذج Filament.
     * @return \Filament\Forms\Form النموذج المعرف.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('البيانات الأساسية')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Section::make('البيانات الأساسية')
                                    ->schema([
                                        Forms\Components\TextInput::make('company')
                                            ->label('اسم الشركة')
                                            ->required(),
                                        Section::make('')
                                            ->schema([
                                                Forms\Components\TextInput::make('client_name')
                                                    ->label('اسم المالك او الشخص المتواصل معاه')
                                                    ->nullable(),
                                                Forms\Components\TextInput::make('address')
                                                    ->label('العنوان / المحافظة')
                                                    ->nullable(),
                                                Forms\Components\TextInput::make('contact_job')
                                                    ->label('وظيفته لدى الشركة')
                                                    ->nullable(),
                                                Forms\Components\TextInput::make('contact_number')
                                                    ->label('رقم الاتصال')
                                                    ->tel()
                                                    ->numeric()
                                                    ->maxLength(9)
                                                    ->rule('regex:/^[0-9]{9}$/')
                                                    ->mask('999999999')
                                                    ->suffix('+967')
                                                    ->nullable(),
                                            ])->columns(2),
                                        FileUpload::make('logo_path')
                                            ->label('شعار العميل')
                                            ->image()
                                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                                            ->directory('clients/logos')
                                            ->maxSize(config('filesystems.max_file_size', 10240))
                                            ->columnSpanFull(),
                                        Forms\Components\Textarea::make('design_data')
                                            ->label('بيانات العميل في التصميم')
                                            ->placeholder('النص الذي يظهر في التصميم')
                                            ->rows(3)
                                            ->nullable()
                                            ->columnSpanFull(),
                                    ]),
                                /*
                                Section::make('الباقة والاشتراك')
                                    ->schema([
                                        Forms\Components\TextInput::make('marketing_amount')
                                            ->label('المبلغ التسويقي')
                                            ->numeric()
                                            ->nullable(),
                                        Forms\Components\Toggle::make('is_pay_per_design')
                                            ->label('نظام الدفع بالحبة (بدون اشتراك)')
                                            ->onColor('success')
                                            ->offColor('gray')
                                            ->default(false),
                                    ])->columns(2),
                                */
                            ]),
                        Tabs\Tab::make('المحتوى')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('التصنيف والمحتوى')
                                    ->schema([
                                        Forms\Components\Select::make('location_id')
                                            ->label('الموقع')
                                            ->relationship('location', 'name')
                                            ->preload()
                                            ->searchable()
                                            ->live()
                                            ->required(),
                                        Forms\Components\Select::make('category_id')
                                            ->label('التصنيف')
                                            ->relationship('category', 'name')
                                            ->preload()
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                // Reset tag group and tags when category changes
                                                $set('tag_group_filter', []);
                                                $set('tags', []);

                                                $currentNeeds = $get('clientNeeds') ?? [];
                                                if (empty($state)) {
                                                    $set('clientNeeds', []);

                                                    return;
                                                }
                                                $filteredNeeds = array_filter($currentNeeds, function ($needId) use ($state) {
                                                    $need = \App\Models\ClientNeed::with('categories')->find($needId);

                                                    return $need && $need->categories->pluck('id')->contains($state);
                                                });
                                                $set('clientNeeds', array_values($filteredNeeds));
                                            })
                                            ->required(),
                                        Forms\Components\Select::make('clientNeeds')
                                            ->label('أنواع العميل')
                                            ->multiple()
                                            ->preload()
                                            ->relationship('clientNeeds', 'name')
                                            ->options(function ($get) {
                                                $categoryId = $get('category_id');
                                                if (! $categoryId) {
                                                    return collect();
                                                }

                                                return \App\Models\ClientNeed::whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId))->pluck('name', 'id');
                                            })
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                if (empty($state)) {
                                                    $set('importance_weights', null);

                                                    return;
                                                }

                                                $levels = \App\Models\ClientNeed::whereIn('id', $state)
                                                    ->pluck('importance_level')
                                                    ->filter()
                                                    ->flatMap(fn ($item) => is_array($item) ? $item : json_decode($item, true) ?? [])
                                                    ->unique()
                                                    ->reject(fn ($level) => $level === 'very_high')
                                                    ->values()
                                                    ->toArray();

                                                $suggested = self::suggestWeights($levels);
                                                $set('importance_weights', $suggested ?: null);
                                            })
                                            ->nullable(),
                                    ])->columns(2),
                                Section::make('تعيين الوسوم')
                                    ->icon('heroicon-o-tag')
                                    ->description('اختر مجموعة الوسوم ثم حدد الوسوم التي تريد تعيينها لهذا العميل.')
                                    ->schema([
                                        Forms\Components\Select::make('tag_group_filter')
                                            ->label('مجموعات الوسوم')
                                            ->relationship('tagGroups', 'name')
                                            ->multiple()
                                            ->options(function (Forms\Get $get) {
                                                $categoryId = $get('category_id');
                                                $query = \App\Models\TagGroup::query();

                                                if ($categoryId) {
                                                    $query->where(function ($q) use ($categoryId) {
                                                        $q->whereHas('categories', fn ($catQ) => $catQ->where('categories.id', $categoryId))
                                                            ->orWhere('assign_all_categories', true);
                                                    });
                                                }

                                                return $query->pluck('name', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->prefixIcon('heroicon-m-rectangle-group')
                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                                                $currentTags = $get('tags') ?? [];
                                                if (empty($currentTags)) {
                                                    return;
                                                }

                                                if (! empty($state)) {
                                                    $validTags = \App\Models\Tag::whereIn('id', $currentTags)
                                                        ->whereIn('tag_group_id', (array) $state)
                                                        ->pluck('id')
                                                        ->toArray();
                                                    $set('tags', $validTags);
                                                }
                                            }),
                                        Forms\Components\Select::make('tags')
                                            ->label('الوسوم')
                                            ->relationship('tags', 'name')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->prefixIcon('heroicon-m-tag')
                                            ->options(function (Forms\Get $get) {
                                                $categoryId = $get('category_id');
                                                $locationId = $get('location_id');
                                                $tagGroupIds = $get('tag_group_filter');

                                                $query = \App\Models\Tag::query()->where('is_active', true);

                                                if ($categoryId) {
                                                    $query->where(function ($q) use ($categoryId) {
                                                        $q->whereHas('categories', fn ($catQ) => $catQ->where('categories.id', $categoryId))
                                                            ->orWhere('assign_all_categories', true);
                                                    });
                                                }

                                                if ($locationId) {
                                                    $query->where(function ($q) use ($locationId) {
                                                        $q->whereHas('locations', fn ($locQ) => $locQ->where('locations.id', $locationId))
                                                            ->orWhere('assign_all_locations', true);
                                                    });
                                                }

                                                if (! empty($tagGroupIds)) {
                                                    $query->whereIn('tag_group_id', (array) $tagGroupIds);
                                                }

                                                return $query->pluck('name', 'id');
                                            }),
                                    ])->columns(2),
                                Section::make('ميزة الجنريت وأوزان التوزيع')
                                    ->icon('heroicon-o-sparkles')
                                    ->description('تفعيل ميزة الجنريت للعميل وتحديد الأنواع المتاحة ونسب التوزيع.')
                                    ->schema([
                                        Forms\Components\Hidden::make('generate_types')
                                            ->default(['very_high', 'high', 'medium', 'low', 'ideas']),
                                        Forms\Components\Toggle::make('has_generate_feature')
                                            ->label('تفعيل ميزة الجنريت')
                                            ->default(true)
                                            ->onColor('success')
                                            ->offColor('gray')
                                            ->live()
                                            ->columnSpanFull(),
                                        Forms\Components\Grid::make()
                                            ->schema([
                                                Forms\Components\Checkbox::make('enable_very_high')
                                                    ->label('عالية جداً (Very High)')
                                                    ->default(true)
                                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                                        $types = $get('generate_types') ?? [];
                                                        if ($state && ! in_array('very_high', $types)) {
                                                            $types[] = 'very_high';
                                                        } elseif (! $state) {
                                                            $types = array_values(array_filter($types, fn ($t) => $t !== 'very_high'));
                                                        }
                                                        $set('generate_types', $types);
                                                    })
                                                    ->live()
                                                    ->columnSpanFull(),
                                                Forms\Components\Fieldset::make('أوزان الأهمية')
                                                    ->columns(3)
                                                    ->schema([
                                                        Forms\Components\Checkbox::make('__gen_high')
                                                            ->label('عالية (High)')
                                                            ->dehydrated(false)
                                                            ->formatStateUsing(fn ($state, $record) => $record ? in_array('high', $record->generate_types ?? []) : true)
                                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                                                $types = $get('generate_types') ?? [];
                                                                if ($state && ! in_array('high', $types)) {
                                                                    $types[] = 'high';
                                                                } else {
                                                                    $types = array_values(array_filter($types, fn ($t) => $t !== 'high'));
                                                                }
                                                                $set('generate_types', $types);
                                                            })
                                                            ->live(),
                                                        Forms\Components\Checkbox::make('__gen_medium')
                                                            ->label('متوسطة (Medium)')
                                                            ->dehydrated(false)
                                                            ->formatStateUsing(fn ($state, $record) => $record ? in_array('medium', $record->generate_types ?? []) : true)
                                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                                                $types = $get('generate_types') ?? [];
                                                                if ($state && ! in_array('medium', $types)) {
                                                                    $types[] = 'medium';
                                                                } else {
                                                                    $types = array_values(array_filter($types, fn ($t) => $t !== 'medium'));
                                                                }
                                                                $set('generate_types', $types);
                                                            })
                                                            ->live(),
                                                        Forms\Components\Checkbox::make('__gen_low')
                                                            ->label('منخفضة (Low)')
                                                            ->dehydrated(false)
                                                            ->formatStateUsing(fn ($state, $record) => $record ? in_array('low', $record->generate_types ?? []) : true)
                                                            ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                                                $types = $get('generate_types') ?? [];
                                                                if ($state && ! in_array('low', $types)) {
                                                                    $types[] = 'low';
                                                                } else {
                                                                    $types = array_values(array_filter($types, fn ($t) => $t !== 'low'));
                                                                }
                                                                $set('generate_types', $types);
                                                            })
                                                            ->live(),
                                                        Forms\Components\TextInput::make('importance_weights.high')
                                                            ->label('')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->maxValue(100)
                                                            ->default(20)
                                                            ->suffix('%')
                                                            ->live(onBlur: true),
                                                        Forms\Components\TextInput::make('importance_weights.medium')
                                                            ->label('')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->maxValue(100)
                                                            ->default(60)
                                                            ->suffix('%')
                                                            ->live(onBlur: true),
                                                        Forms\Components\TextInput::make('importance_weights.low')
                                                            ->label('')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->maxValue(100)
                                                            ->default(20)
                                                            ->suffix('%')
                                                            ->live(onBlur: true),
                                                    ]),
                                                Forms\Components\Checkbox::make('__gen_ideas')
                                                    ->label('أفكار (Ideas)')
                                                    ->dehydrated(false)
                                                    ->formatStateUsing(fn ($state, $record) => $record ? in_array('ideas', $record->generate_types ?? []) : true)
                                                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get): void {
                                                        $types = $get('generate_types') ?? [];
                                                        if ($state && ! in_array('ideas', $types)) {
                                                            $types[] = 'ideas';
                                                        } else {
                                                            $types = array_values(array_filter($types, fn ($t) => $t !== 'ideas'));
                                                        }
                                                        $set('generate_types', $types);
                                                    })
                                                    ->live()
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2)
                                            ->visible(fn (Forms\Get $get): bool => (bool) $get('has_generate_feature')),
                                        Forms\Components\Placeholder::make('weights_sum_display')
                                            ->label('مجموع النسب')
                                            ->content(function (Forms\Get $get): string {
                                                $weights = $get('importance_weights') ?? [];
                                                $high = (int) ($weights['high'] ?? 0);
                                                $medium = (int) ($weights['medium'] ?? 0);
                                                $low = (int) ($weights['low'] ?? 0);
                                                $sum = $high + $medium + $low;

                                                if ($sum === 0 && ! $high && ! $medium) {
                                                    return '—';
                                                }

                                                if ($sum === 100) {
                                                    return "✅ {$sum}%";
                                                }

                                                return "⚠️ {$sum}% (يجب أن يساوي 100%)";
                                            })
                                            ->visible(fn (Forms\Get $get): bool => (bool) $get('has_generate_feature'))
                                            ->columnSpanFull(),
                                    ]),
                                Forms\Components\RichEditor::make('notes')
                                    ->label('الملاحظات')
                                    ->disableToolbarButtons([
                                        'attachFiles',
                                        'link',
                                    ])
                                    ->nullable()
                                    ->columnSpanFull(),
                            ]),
                        Tabs\Tab::make('قسم المالية')
                            ->icon('heroicon-o-banknotes')
                            ->hidden(
                                fn (string $operation): bool => $operation === 'create' || ! Auth::user()?->can('view_client_financial')
                            )
                            ->schema([
                                Section::make('بيانات التعاقد')
                                    ->description('إدارة بيانات الاشتراك والاشتراك الحالية للعميل.')
                                    ->schema([
                                        Forms\Components\Toggle::make('contract_status')
                                            ->label('حالة الاشتراك (نشط / موقف)')
                                            ->dehydrated(false)
                                            ->onColor('success')
                                            ->offColor('danger')
                                            ->default(true),
                                        Forms\Components\Select::make('contract_payment_type')
                                            ->label('نوع الدفع')
                                            ->options([
                                                'advance' => 'مقدم',
                                                'deferred' => 'مؤخر',
                                            ])
                                            ->dehydrated(false)
                                            ->required(),
                                        Forms\Components\Select::make('contract_billing_cycle')
                                            ->label('دورة الفوترة')
                                            ->options([
                                                'weekly' => 'أسبوعي',
                                                'monthly' => 'شهري',
                                                'yearly' => 'سنوي',
                                            ])
                                            ->dehydrated(false)
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function ($state, $set, $get) {
                                                $startDate = $get('contract_start_date');
                                                if ($startDate) {
                                                    $date = \Illuminate\Support\Carbon::parse($startDate);
                                                    $set('contract_end_date', \App\Models\Contract::calculateEndDate($date, $state)->format('Y-m-d'));
                                                }
                                            }),
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\DatePicker::make('contract_start_date')
                                                    ->label('تاريخ بدء الاشتراك')
                                                    ->dehydrated(false)
                                                    ->required()
                                                    ->default(now())
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, $set, $get) {
                                                        if ($state) {
                                                            $billingCycle = $get('contract_billing_cycle') ?? 'monthly';
                                                            $date = \Illuminate\Support\Carbon::parse($state);
                                                            $set('contract_end_date', \App\Models\Contract::calculateEndDate($date, $billingCycle)->format('Y-m-d'));
                                                        }
                                                    })
                                                    ->disabled(
                                                        fn (?Client $record): bool => $record && $record->invoices()->where('status', 'posted')->exists()
                                                    )
                                                    ->helperText(
                                                        fn (?Client $record): string => $record && $record->invoices()->where('status', 'posted')->exists()
                                                            ? '⚠️ لا يمكن تعديل تاريخ البداية بعد إصدار فواتير'
                                                            : ''
                                                    ),
                                                Forms\Components\DatePicker::make('contract_end_date')
                                                    ->label('تاريخ انتهاء الاشتراك')
                                                    ->dehydrated(false)
                                                    ->required()
                                                    ->disabled(
                                                        fn (?Client $record): bool => $record && $record->invoices()->where('status', 'posted')->exists()
                                                    )
                                                    ->helperText(
                                                        fn (?Client $record): string => $record && $record->invoices()->where('status', 'posted')->exists()
                                                            ? '⚠️ لا يمكن تعديل تاريخ النهاية بعد إصدار فواتير'
                                                            : ''
                                                    ),
                                            ]),
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('contract_weekly_designs_count')
                                                    ->label('عدد التصاميم الأسبوعية')
                                                    ->dehydrated(false)
                                                    ->minValue(0)
                                                    ->maxValue(15)
                                                    ->numeric()
                                                    ->default(1)
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, $set, $get) {
                                                        if ($get('contract_billing_cycle') != 'weekly') {
                                                            $set('contract_monthly_designs_count', \App\Models\Contract::calculateMonthlyDesignsCount((int) $state));
                                                        }
                                                    }),
                                                Forms\Components\TextInput::make('contract_monthly_designs_count')
                                                    ->label('عدد التصاميم الشهرية')
                                                    ->dehydrated(false)
                                                    ->numeric()
                                                    ->readonly()
                                                    ->helperText('يتم حسابه تلقائياً بناءً على الأسبوعي'),
                                            ]),
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\TextInput::make('contract_total_amount')
                                                    ->label('المبلغ الإجمالي')
                                                    ->dehydrated(false)
                                                    ->numeric()
                                                    ->required()
                                                    ->disabled(
                                                        fn (?Client $record): bool => $record && $record->invoices()->where('status', 'posted')->exists()
                                                    )
                                                    ->helperText(
                                                        fn (?Client $record): string => $record && $record->invoices()->where('status', 'posted')->exists()
                                                            ? '⚠️ لا يمكن تعديل المبلغ بعد إصدار فواتير. استخدم زر "اشتراك جديد" أدناه'
                                                            : ''
                                                    ),
                                                Forms\Components\Select::make('contract_currency_id')
                                                    ->label('العملة')
                                                    ->options(fn () => \App\Models\Currency::pluck('currency_name', 'id'))
                                                    ->dehydrated(false)
                                                    ->required()
                                                    ->default(fn () => \App\Models\Currency::first()?->id),
                                                Forms\Components\TextInput::make('contract_marketing_amount')
                                                    ->label('مبلغ التسويق')
                                                    ->dehydrated(false)
                                                    ->numeric()
                                                    ->disabled(
                                                        fn (?Client $record): bool => $record && $record->invoices()->where('status', 'posted')->exists()
                                                    )
                                                    ->helperText(
                                                        fn (?Client $record): string => $record && $record->invoices()->where('status', 'posted')->exists()
                                                            ? '⚠️ لا يمكن تعديل المبلغ بعد إصدار فواتير'
                                                            : ''
                                                    ),
                                            ]),
                                        Forms\Components\Toggle::make('contract_auto_renewal')
                                            ->label('تجديد تلقائي')
                                            ->dehydrated(false)
                                            ->default(false),
                                        Forms\Components\Toggle::make('contract_additional_designs_enabled')
                                            ->label('حساب التصاميم الإضافية')
                                            ->dehydrated(false)
                                            ->default(false)
                                            ->live(),
                                        Forms\Components\TextInput::make('contract_additional_design_price')
                                            ->label('سعر التصميم الواحد')
                                            ->dehydrated(false)
                                            ->numeric()
                                            ->visible(fn ($get) => (bool) $get('contract_additional_designs_enabled')),
                                        Forms\Components\TextInput::make('contract_additional_designs_count')
                                            ->label('عدد التصاميم الإضافية المستحقة')
                                            ->dehydrated(false)
                                            ->disabled()
                                            ->visible(fn ($get) => (bool) $get('contract_additional_designs_enabled')),
                                        Forms\Components\Toggle::make('contract_simple_requests_enabled')
                                            ->label('حساب الطلبات البسيطة')
                                            ->dehydrated(false)
                                            ->default(false)
                                            ->live(),
                                        Forms\Components\TextInput::make('contract_simple_request_price')
                                            ->label('سعر الطلب الواحد')
                                            ->dehydrated(false)
                                            ->numeric()
                                            ->visible(fn ($get) => (bool) $get('contract_simple_requests_enabled')),
                                        Forms\Components\TextInput::make('contract_simple_requests_count')
                                            ->label('عدد الطلبات المستحقة')
                                            ->dehydrated(false)
                                            ->disabled(),
                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('newContractAfterInvoices')
                                                ->label('إنهاء الاشتراك الحالي وإنشاء اشتراك جديد')
                                                ->icon('heroicon-o-document-plus')
                                                ->color('warning')
                                                ->visible(
                                                    fn (?Client $record): bool => $record
                                                        && $record->invoices()->where('status', 'posted')->exists()
                                                        && (auth()->user()?->can('update', $record) ?? false)
                                                )
                                                ->modalHeading('إنشاء اشتراك جديد')
                                                ->modalDescription('سيتم إنهاء الاشتراك الحالي تلقائياً وإنشاء اشتراك جديد بقيمة مختلفة. سيتم إصدار فاتورة أولى للاشتراك الجديد.')
                                                ->modalWidth('2xl')
                                                ->modalSubmitActionLabel('إنشاء الاشتراك')
                                                ->form(function () {
                                                    return [
                                                        Forms\Components\Select::make('payment_type')
                                                            ->label('نوع الدفع')
                                                            ->options(['advance' => 'مقدم', 'deferred' => 'مؤخر'])
                                                            ->required(),
                                                        Forms\Components\Select::make('billing_cycle')
                                                            ->label('دورة الفوترة')
                                                            ->options(['weekly' => 'أسبوعي', 'monthly' => 'شهري', 'yearly' => 'سنوي'])
                                                            ->required()
                                                            ->live(),
                                                        Forms\Components\DatePicker::make('start_date')
                                                            ->label('تاريخ البدء')
                                                            ->default(now())
                                                            ->required(),
                                                        Forms\Components\Grid::make(2)
                                                            ->schema([
                                                                Forms\Components\TextInput::make('weekly_designs_count')
                                                                    ->label('التصاميم الأسبوعية')
                                                                    ->numeric()
                                                                    ->default(1)
                                                                    ->live()
                                                                    ->afterStateUpdated(function ($state, $set, $get) {
                                                                        if ($get('billing_cycle') === 'monthly') {
                                                                            $set('monthly_designs_count', \App\Models\Contract::calculateMonthlyDesignsCount((int) $state));
                                                                        }
                                                                    }),
                                                                Forms\Components\TextInput::make('monthly_designs_count')
                                                                    ->label('التصاميم الشهرية')
                                                                    ->numeric()
                                                                    ->readonly(),
                                                            ]),
                                                        Forms\Components\Grid::make(2)
                                                            ->schema([
                                                                Forms\Components\TextInput::make('total_amount')
                                                                    ->label('المبلغ الإجمالي')
                                                                    ->numeric()
                                                                    ->required(),
                                                                Forms\Components\Select::make('currency_id')
                                                                    ->label('العملة')
                                                                    ->options(fn () => \App\Models\Currency::pluck('currency_name', 'id'))
                                                                    ->default(fn () => \App\Models\Currency::first()?->id)
                                                                    ->required(),
                                                            ]),
                                                        Forms\Components\TextInput::make('marketing_amount')
                                                            ->label('مبلغ التسويق')
                                                            ->numeric()
                                                            ->default(0),
                                                        Forms\Components\Toggle::make('additional_designs_enabled')
                                                            ->label('حساب التصاميم الإضافية')
                                                            ->default(false)
                                                            ->live(),
                                                        Forms\Components\TextInput::make('additional_design_price')
                                                            ->label('سعر التصميم الواحد')
                                                            ->numeric()
                                                            ->visible(fn ($get) => (bool) $get('additional_designs_enabled')),
                                                        Forms\Components\Toggle::make('simple_requests_enabled')
                                                            ->label('حساب الطلبات البسيطة')
                                                            ->default(false)
                                                            ->live(),
                                                        Forms\Components\TextInput::make('simple_request_price')
                                                            ->label('سعر الطلب الواحد')
                                                            ->numeric()
                                                            ->visible(fn ($get) => (bool) $get('simple_requests_enabled')),
                                                    ];
                                                })
                                                ->action(function (array $data, $record) {
                                                    $current = $record->currentContract;
                                                    if ($current) {
                                                        $current->update([
                                                            'status' => 'suspended',
                                                            'end_date' => now(),
                                                            'updated_by_user' => Auth::id(),
                                                        ]);
                                                    }

                                                    $startDate = \Illuminate\Support\Carbon::parse($data['start_date']);
                                                    $endDate = \App\Models\Contract::calculateEndDate($startDate, $data['billing_cycle']);

                                                    $contract = $record->contracts()->create([
                                                        'status' => 'active',
                                                        'payment_type' => $data['payment_type'],
                                                        'billing_cycle' => $data['billing_cycle'],
                                                        'start_date' => $data['start_date'],
                                                        'end_date' => $endDate,
                                                        'weekly_designs_count' => (int) ($data['weekly_designs_count'] ?? 1),
                                                        'monthly_designs_count' => (int) ($data['monthly_designs_count'] ?? \App\Models\Contract::calculateMonthlyDesignsCount((int) ($data['weekly_designs_count'] ?? 1))),
                                                        'total_amount' => $data['total_amount'],
                                                        'currency_id' => $data['currency_id'],
                                                        'marketing_amount' => $data['marketing_amount'] ?? 0,
                                                        'auto_renewal' => true,
                                                        'additional_designs_enabled' => (bool) ($data['additional_designs_enabled'] ?? false),
                                                        'additional_design_price' => (float) ($data['additional_design_price'] ?? 0) ?: null,
                                                        'additional_designs_count' => 0,
                                                        'simple_requests_enabled' => (bool) ($data['simple_requests_enabled'] ?? false),
                                                        'simple_request_price' => (float) ($data['simple_request_price'] ?? 0) ?: null,
                                                        'simple_requests_count' => 0,
                                                        'created_by_user' => Auth::id(),
                                                        'updated_by_user' => Auth::id(),
                                                    ]);

                                                    // Reactivate client if manually suspended and unfreeze tasks
                                                    app(\App\Services\ClientLifecycleService::class)->resume($record);

                                                    $totalAmount = (float) $data['total_amount'];
                                                    $marketingAmount = (float) ($data['marketing_amount'] ?? 0);
                                                    $dueDate = $data['payment_type'] === 'advance'
                                                        ? now()->addDays(7)
                                                        : $endDate;

                                                    $invoice = \App\Models\Invoice::create([
                                                        'client_id' => $record->id,
                                                        'contract_id' => $contract->id,
                                                        'issue_date' => $data['start_date'],
                                                        'due_date' => $dueDate,
                                                        'total_amount' => $totalAmount + $marketingAmount,
                                                        'status' => 'posted',
                                                        'notes' => 'فاتورة أولى للاشتراك الجديد',
                                                        'created_by_user' => Auth::id(),
                                                        'updated_by_user' => Auth::id(),
                                                    ]);

                                                    if ($totalAmount > 0) {
                                                        $invoice->items()->create([
                                                            'description' => 'خدمات التصميم - '.number_format($contract->monthly_designs_count).' تصاميم شهرياً',
                                                            'quantity' => 1,
                                                            'unit_amount' => $totalAmount,
                                                            'total' => $totalAmount,
                                                        ]);
                                                    }

                                                    if ($marketingAmount > 0) {
                                                        $invoice->items()->create([
                                                            'description' => 'خدمات التسويق',
                                                            'quantity' => 1,
                                                            'unit_amount' => $marketingAmount,
                                                            'total' => $marketingAmount,
                                                        ]);
                                                    }

                                                    \Filament\Notifications\Notification::make()
                                                        ->title('✅ تم إنشاء الاشتراك الجديد')
                                                        ->body("الاشتراك الجديد نشط والفاتورة الأولى رقم {$invoice->invoice_number}")
                                                        ->success()
                                                        ->send();
                                                }),
                                        ])->columnSpanFull(),
                                    ])->columns(2),

                                Section::make('التحصيل والمتابعة')
                                    ->icon('heroicon-o-bell')
                                    ->description('إعدادات التحصيل ومتابعة السداد.')
                                    ->schema([
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\Toggle::make('is_credit_allowed')
                                                    ->label('السقف الائتماني مسموح')
                                                    ->default(false)
                                                    ->live()
                                                    ->reactive()
                                                    ->afterStateUpdated(function ($state, callable $set) {
                                                        if ($state) {
                                                            $set('contract_grace_period_days', null);
                                                        }
                                                    }),
                                                Forms\Components\TextInput::make('contract_grace_period_days')
                                                    ->label('مهلة السداد (أيام)')
                                                    ->dehydrated(false)
                                                    ->numeric()
                                                    ->helperText('عدد الأيام المسموحة بعد الإشعار قبل الإيقاف'),
                                            ]),
                                    ]),

                                Section::make('ملاحظات المقاضاة')
                                    ->schema([
                                        /*
                                        Forms\Components\Toggle::make('contract_is_under_lawsuit')
                                            ->label('العميل قيد المقاضاة')
                                            ->dehydrated(false)
                                            ->onColor('danger')
                                            ->offColor('gray')
                                            ->default(false),
                                        */
                                        Forms\Components\Textarea::make('contract_legal_notes')
                                            ->label('كيفية المقاضاة كتابية')
                                            ->dehydrated(false)
                                            ->rows(3),
                                    ]),
                            ]),
                        Tabs\Tab::make('التقييم والكليشة')
                            ->icon('heroicon-o-star')
                            ->schema([
                                Forms\Components\TextInput::make('customer_rating_value')
                                    ->label('قيمة التقييم')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->nullable(),
                                Forms\Components\TextInput::make('change_cliche_threshold')
                                    ->label('كم عدد التصاميم لتغيير الكليشة')
                                    ->numeric()
                                    ->nullable(),
                                /*
                                Forms\Components\TextInput::make('cliche_counter')
                                    ->label('العداد الحالي للتصاميم')
                                    ->numeric()
                                    ->default(0),
                                */
                            ]),
                    ])->columns(2),
            ]);
    }

    /**
     * يقوم بتعريف أعمدة الجدول (Table) لعرض العملاء.
     *
     * @param  \Filament\Tables\Table  $table  جدول Filament.
     * @return \Filament\Tables\Table الجدول المعرف.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company')->label('الشركة')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('client_name')->visibleFrom('lg'),
                // Tables\Columns\TextColumn::make('client_name')->label('اسم العميل')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('location.name')->label('الموقع'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('التصنيف')
                    ->badge(),
                Tables\Columns\TextColumn::make('customer_rating_value')->label('تقييم العميل')->sortable(),
                Tables\Columns\TextColumn::make('activity_status')
                    ->label('حالة النشاط')
                    ->badge()
                    ->state(fn (Client $record): string => $record->activity_status)
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success', // الأخضر
                        'pending_arrears' => 'warning', // الأصفر
                        'auto_suspended' => 'danger', // الأحمر
                        'manually_suspended', 'suspended' => 'gray', // الأسود
                        'expired' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'نشط',
                        'pending_arrears' => 'نشط (متأخرات)',
                        'auto_suspended' => 'موقف تلقائياً',
                        'manually_suspended', 'suspended' => 'موقف يدوياً',
                        'expired' => 'منتهي',
                        default => $state,
                    }),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\Action::make('custom_client_import')
                    ->label('استيراد متقدم')
                    ->icon('heroicon-o-arrow-up-on-square-stack')
                    ->color('primary')
                    ->url(\App\Filament\Pages\CustomClientImport::getUrl()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->slideOver()
                    ->before(function (Client $record) {
                        $record->captureOriginalRelations();
                    })
                    ->after(function (Client $record) {
                        $record->logRelationshipChanges();
                    }),
                Tables\Actions\RestoreAction::make()
                    ->label('استعادة العميل')
                    ->modalHeading('استعادة العميل')
                    ->modalDescription('سيتم استعادة العميل وجميع بياناته. هل أنت متأكد؟')
                    ->successNotificationTitle('تم استعادة العميل بنجاح'),
                Tables\Actions\ForceDeleteAction::make()
                    ->label('حذف نهائي')
                    ->modalHeading('حذف العميل نهائياً')
                    ->modalDescription('لا يمكن التراجع عن هذا الإجراء. سيتم حذف العميل وجميع بياناته المرتبطة بشكل نهائي.')
                    ->successNotificationTitle('تم حذف العميل نهائياً'),
                Tables\Actions\Action::make('resetClicheCounter')
                    ->label('تصفير عداد الكليشة')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('تصفير عداد الكليشة')
                    ->modalDescription('سيتم إعادة عداد التصاميم إلى 0. هل أنت متأكد؟')
                    ->action(fn ($record) => $record->update(['cliche_counter' => 0]))
                    ->visible(fn ($record) => $record->cliche_counter > 0 && (auth()->user()?->can('update', $record) ?? false)),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make()
                        ->label('استعادة المحددين')
                        ->modalHeading('استعادة العملاء المحددين')
                        ->successNotificationTitle('تم استعادة العملاء بنجاح'),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->label('حذف نهائي للمحددين')
                        ->modalHeading('حذف العملاء المحددين نهائياً')
                        ->modalDescription('لا يمكن التراجع عن هذا الإجراء.')
                        ->successNotificationTitle('تم حذف العملاء نهائياً'),
                ]),
            ]);
    }

    /**
     * يقوم بتعريف مكونات قائمة المعلومات (Infolist) لعرض تفاصيل العميل.
     *
     * @param  \Filament\Infolists\Infolist  $infolist  قائمة معلومات Filament.
     * @return \Filament\Infolists\Infolist قائمة المعلومات المعرفة.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Tabs::make('ClientDetails')
                    ->columnSpanFull()
                    ->tabs([
                        \Filament\Infolists\Components\Tabs\Tab::make('ملف العميل')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Split::make([
                                    InfolistSection::make('معلومات الشركة')
                                        ->icon('heroicon-o-building-office')
                                        ->schema([
                                            TextEntry::make('company')
                                                ->label('اسم الشركة')
                                                ->size(TextEntry\TextEntrySize::Large)
                                                ->weight(FontWeight::Bold)
                                                ->color('primary'),
                                            KeyValueEntry::make('importance_weights')
                                                ->label('أوزان الأهمية')
                                                ->keyLabel('الاهمية'),

                                            TextEntry::make('category.name')
                                                ->label('التصنيف')
                                                ->badge()
                                                ->color('success'),
                                            TextEntry::make('location.name')
                                                ->label('الموقع')
                                                ->icon('heroicon-o-map-pin'),
                                        ])->grow(true),

                                    InfolistSection::make('حالة العميل')
                                        ->icon('heroicon-o-signal')
                                        ->schema([
                                            TextEntry::make('status')
                                                ->label('الحالة')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state ? 'نشط' : 'موقف')
                                                ->color(fn ($state) => $state ? 'success' : 'danger'),
                                            TextEntry::make('customer_rating_value')
                                                ->label('تقييم العميل')
                                                ->badge()
                                                ->color('warning')
                                                ->icon('heroicon-o-star'),
                                            TextEntry::make('contracts_count')
                                                ->label('عدد الاشتراكات')
                                                ->state(fn ($record) => $record->contracts()->count())
                                                ->badge()
                                                ->color('info'),
                                            /*
                                            TextEntry::make('is_pay_per_design')
                                                ->label('نظام الدفع')
                                                ->badge()
                                                ->formatStateUsing(fn ($state) => $state ? 'بالحبة' : 'اشتراك دوري')
                                                ->color(fn ($state) => $state ? 'warning' : 'gray'),
                                            */
                                            TextEntry::make('additional_designs_balance')
                                                ->label('رصيد التصاميم الإضافية')
                                                ->badge()
                                                ->color('success')
                                                ->visible(fn ($record) => $record->is_pay_per_design || $record->additional_designs_balance > 0),
                                            TextEntry::make('wallet_balance')
                                                ->label('رصيد المحفظة')
                                                ->money('YER')
                                                ->weight(FontWeight::Bold)
                                                ->color('success'),
                                        ])->grow(true),
                                ])->from('sm')->columnSpanFull()->grow(true),

                                InfolistSection::make('معلومات الاتصال')
                                    ->icon('heroicon-o-user-circle')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('client_name')
                                            ->label('اسم المسؤول')
                                            ->icon('heroicon-o-user'),
                                        TextEntry::make('contact_job')
                                            ->label('المنصب')
                                            ->icon('heroicon-o-briefcase'),
                                        TextEntry::make('contact_number')
                                            ->label('رقم الاتصال')
                                            ->icon('heroicon-o-phone')
                                            ->formatStateUsing(fn ($state) => $state ? '+967 '.$state : '-'),
                                        TextEntry::make('address')
                                            ->label('العنوان')
                                            ->icon('heroicon-o-map')
                                            ->columnSpanFull(),
                                    ]),

                                InfolistSection::make('أنواع العميل والتفضيلات')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('clientNeeds.name')
                                            ->label('أنواع العميل')
                                            ->badge()
                                            ->separator(',')
                                            ->color('info'),
                                        /* TextEntry::make('marketing_amount')
                                            ->label('المبلغ التسويقي')
                                            ->money('YER')
                                            ->icon('heroicon-o-banknotes'),
                                        */
                                        TextEntry::make('change_cliche_threshold')
                                            ->label('كم عدد التصاميم لتغيير الكليشة')
                                            ->suffix(' تصميم'),
                                        TextEntry::make('cliche_counter')
                                            ->label('عدد التصاميم المسلمة حالياً')
                                            ->suffix(' تصميم')
                                            ->weight(FontWeight::Bold)
                                            ->color(fn ($record) => ($record->change_cliche_threshold > 0 && $record->cliche_counter >= $record->change_cliche_threshold) ? 'danger' : 'success')
                                            ->hint(fn ($record) => ($record->change_cliche_threshold > 0 && $record->cliche_counter >= $record->change_cliche_threshold) ? 'يجب تغيير الكليشة!' : null)
                                            ->hintColor('danger')
                                            ->hintIcon('heroicon-m-exclamation-triangle'),
                                    ]),

                                InfolistSection::make('الملاحظات')
                                    ->icon('heroicon-o-pencil-square')
                                    ->hidden(fn ($record) => blank($record->notes))
                                    ->schema([
                                        TextEntry::make('notes')
                                            ->label('')
                                            ->html()
                                            ->prose()
                                            ->columnSpanFull(),
                                        // ->extraAttributes([
                                        //     'class' => 'rounded-xl border border-purple-500/30 bg-purple-500/10 px-4 py-3',
                                        // ]),
                                    ]),

                                InfolistSection::make('الإعدادات المالية')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('is_credit_allowed')
                                            ->label('السقف الائتماني')
                                            ->badge()
                                            ->formatStateUsing(fn ($state) => $state ? 'مسموح' : 'غير مسموح')
                                            ->color(fn ($state) => $state ? 'success' : 'danger'),
                                        TextEntry::make('suspension_days')
                                            ->label('مدة التوقيف')
                                            ->suffix(' يوم')
                                            ->placeholder('غير محدد'),
                                        TextEntry::make('suspended_at')
                                            ->label('تاريخ التوقيف')
                                            ->dateTime()
                                            ->placeholder('لم يتم التوقيف'),
                                    ]),

                                InfolistSection::make('معلومات إضافية')
                                    ->icon('heroicon-o-information-circle')
                                    ->columns(3)
                                    ->collapsible()
                                    ->schema([
                                        TextEntry::make('createdBy.name')
                                            ->label('أضيف بواسطة')
                                            ->icon('heroicon-o-user-plus'),
                                        TextEntry::make('created_at')
                                            ->label('تاريخ الإضافة')
                                            ->dateTime()
                                            ->icon('heroicon-o-clock'),
                                        TextEntry::make('updated_at')
                                            ->label('آخر تحديث')
                                            ->dateTime()
                                            ->since(),
                                    ]),
                            ]),
                        \Filament\Infolists\Components\Tabs\Tab::make('المالية والاشتراكات')
                            ->icon('heroicon-o-banknotes')
                            ->visible(fn () => Auth::user()?->can('view_client_financial'))
                            ->schema([
                                Split::make([
                                    InfolistSection::make('الملخص المالي')
                                        ->icon('heroicon-o-banknotes')
                                        ->schema([
                                            TextEntry::make('balance')
                                                ->label('الرصيد الحالي')
                                                ->state(function ($record) {
                                                    $balance = $record->outstanding_balance;

                                                    return number_format($balance, 2).' YER';
                                                })
                                                ->color(fn ($record) => $record->outstanding_balance > 0 ? 'danger' : 'success')
                                                ->weight(FontWeight::Bold)
                                                ->size(TextEntry\TextEntrySize::Large),

                                            TextEntry::make('total_debits')
                                                ->label('إجمالي الفواتير (المُفوتر)')
                                                ->money('YER')
                                                ->state(fn ($record) => $record->total_invoiced)
                                                ->color('info'),

                                            TextEntry::make('total_credits')
                                                ->label('إجمالي المسدد')
                                                ->money('YER')
                                                ->state(fn ($record) => $record->total_paid)
                                                ->color('success'),
                                        ])->columns(3),
                                ])->columnSpanFull(),

                                Split::make([
                                    InfolistSection::make('تفاصيل الاشتراك الحالي')
                                        ->icon('heroicon-o-calendar')
                                        ->schema([
                                            TextEntry::make('currentContract.start_date')
                                                ->label('تاريخ بدء الاشتراك')
                                                ->date(format: 'Y-m-d')
                                                ->weight(FontWeight::Bold),

                                            TextEntry::make('currentContract.billing_cycle')
                                                ->label('دورة الفوترة')
                                                ->formatStateUsing(fn (?string $state): string => match ($state) {
                                                    'weekly' => 'أسبوعي',
                                                    'yearly' => 'سنوي',
                                                    default => 'شهري',
                                                }),

                                            TextEntry::make('currentContract.weekly_designs_count')
                                                ->label('عدد التصاميم الأسبوعية'),

                                            TextEntry::make('currentContract.total_amount')
                                                ->label('قيمة الاشتراك')
                                                ->money('YER')
                                                ->weight(FontWeight::Bold)
                                                ->color('secondary'),

                                            TextEntry::make('currentContract.status')
                                                ->label('حالة الاشتراك')
                                                ->badge()
                                                ->color(fn (?string $state): string => match ($state) {
                                                    'expired' => 'danger',
                                                    'suspended' => 'warning',
                                                    'active' => 'success',
                                                    default => 'gray',
                                                })
                                                ->formatStateUsing(fn (?string $state): string => match ($state) {
                                                    'expired' => 'منتهي',
                                                    'suspended' => 'موقف',
                                                    'active' => 'نشط',
                                                    default => 'لا يوجد',
                                                }),
                                        ])->columns(3),
                                ])->columnSpanFull(),
                            ]),
                        \Filament\Infolists\Components\Tabs\Tab::make('سجل التغييرات')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                UserTrackingSection::make(),
                                \Filament\Infolists\Components\RepeatableEntry::make('activities')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('causer.name')
                                            ->label('المستخدم')
                                            ->icon('heroicon-o-user'),
                                        TextEntry::make('description')
                                            ->label('الحدث')
                                            ->badge()
                                            ->color(fn (?string $state): string => match ($state) {
                                                'created' => 'success',
                                                'updated' => 'warning',
                                                'deleted' => 'danger',
                                                default => 'gray',
                                            })
                                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                                'created' => 'إنشاء',
                                                'updated' => 'تعديل',
                                                'deleted' => 'حذف',
                                                default => $state ?? '—',
                                            }),
                                        TextEntry::make('created_at')
                                            ->label('التاريخ')
                                            ->dateTime(),
                                    ])
                                    ->columns(3),
                            ]),
                    ]),
            ]);
    }

    /**
     * يقوم بإرجاع مديري العلاقات (Relation Managers) لهذا المورد.
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * يقوم بإرجاع صفحات (Pages) هذا المورد.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
            'view' => Pages\ViewClient::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            // Pages\ViewClient::class,
            // Pages\EditClient::class,
        ]);
    }

    /**
     * يقترح نسب الأهمية تلقائياً بناءً على المستويات المتاحة.
     *
     * @param  array<string>  $levels  المستويات المتاحة (بدون very_high)
     * @return array<string, int>|null
     */
    public static function suggestWeights(array $levels): ?array
    {
        $levels = array_values(array_unique(array_intersect($levels, ['high', 'medium', 'low'])));
        sort($levels);

        $templates = [
            'high' => ['high' => 100],
            'medium' => ['medium' => 100],
            'low' => ['low' => 100],
            'high,medium' => ['high' => 70, 'medium' => 30],
            'high,low' => ['high' => 70, 'low' => 30],
            'low,medium' => ['medium' => 60, 'low' => 40],
            'high,low,medium' => ['high' => 50, 'medium' => 25, 'low' => 25],
        ];

        $key = implode(',', $levels);

        return $templates[$key] ?? null;
    }
}
