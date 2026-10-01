<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Events\OrderAssigned;
use App\Events\OrderRevisionRequested;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Designer;
use App\Models\Order;
use App\Services\RoundRobinService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Renderless;

class CreateOrder extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationLabel = 'الطلبات';

    protected static ?string $title = 'الطلبات';

    protected static string $view = 'filament.pages.create-order';

    public ?string $company = null;

    public ?string $description = null;

    public ?int $designer_id = null;

    public string $creationMode = 'single';

    public ?string $bulk_companies = null;

    public ?int $bulk_designer_id = null;

    public ?string $bulk_description = null;

    public string $activeTab = 'active';

    public array $selectedDesigners = [];

    public function mount(): void
    {
        $this->selectedDesigners = session('selected_designers', []);
        $this->tableGroupingDirection = 'desc';
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('view_create_order');
    }

    public function getDesigners()
    {
        return Designer::with('user')->get();
    }

    public function getDesignerIds(): array
    {
        return Designer::pluck('id')->toArray();
    }

    #[Renderless]
    public function saveSelectedDesigners(): void
    {
        session(['selected_designers' => $this->selectedDesigners]);

        Notification::make()
            ->title('تم حفظ المصممين المحددين')
            ->success()
            ->send();
    }

    #[Renderless]
    public function toggleDesigner(int $designerId): void
    {
        if (in_array($designerId, $this->selectedDesigners)) {
            $this->selectedDesigners = array_values(array_diff($this->selectedDesigners, [$designerId]));
        } else {
            $this->selectedDesigners[] = $designerId;
        }

        session(['selected_designers' => $this->selectedDesigners]);
    }

    #[Renderless]
    public function selectAllDesigners(): void
    {
        $this->selectedDesigners = Designer::pluck('id')->toArray();
        session(['selected_designers' => $this->selectedDesigners]);
    }

    #[Renderless]
    public function deselectAllDesigners(): void
    {
        $this->selectedDesigners = [];
        session(['selected_designers' => []]);
    }

    public function getClientContextProperty(): ?array
    {
        if (blank($this->company)) {
            return null;
        }

        $client = Client::where('company', $this->company)->first();

        if (! $client) {
            return [
                'found' => false,
                'company' => $this->company,
                'status_label' => 'عميل غير مسجل',
            ];
        }

        $contract = Contract::where('client_id', $client->id)->latest('id')->first();
        $contractStatus = $contract?->status;

        return [
            'found' => true,
            'company' => $client->company,
            'is_active' => $contractStatus === 'active',
            'is_suspended' => in_array($contractStatus, ['suspended', 'موقف يدوياً']),
            'is_expired' => $contractStatus === 'expired',
            'has_contract' => (bool) $contract,
            'status_label' => match ($contractStatus) {
                'active' => 'نشط',
                'suspended', 'موقف يدوياً', 'موقوف' => 'موقف',
                'expired' => 'منتهي',
                'renewed' => 'مجدد',
                default => $contractStatus ?? 'بدون اشتراك',
            },
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('company')
                ->label('اسم العميل')
                ->required()
                ->live(debounce: 400)
                ->datalist(
                    Client::query()
                        ->whereNotNull('company')
                        ->where('company', '!=', '')
                        ->distinct()
                        ->pluck('company')
                        ->toArray()
                )
                ->placeholder('ابحث عن اسم العميل...')
                ->helperText(function () {
                    $context = $this->clientContext;
                    if (! $context || ! $context['found'] || $context['is_active']) {
                        return null;
                    }

                    return new HtmlString('
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 mt-1">
                            <svg class="w-3.5 h-3.5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            <span>'.e($context['status_label']).'</span>
                        </span>
                    ');
                }),

            Select::make('designer_id')
                ->label('تكليف مصمم محدد (اختياري)')
                // ->placeholder('توزيع تلقائي (Round Robin)')
                ->options(fn () => Designer::with('user')->get()->pluck('user.name', 'id'))
                ->searchable()
                ->nullable(),

            Textarea::make('description')
                ->label('الوصف')
                ->rows(1)
                ->placeholder('وصف الطلب (اختياري)')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function create(): void
    {
        $this->validate();

        if ($this->designer_id) {
            $designer = Designer::with('user')->find($this->designer_id);
        } else {
            $selectedDesigners = session('selected_designers', []);

            $designer = app(RoundRobinService::class)->assignNextDesigner(
                ! empty($selectedDesigners) ? $selectedDesigners : null
            );
        }

        if (! $designer) {
            Notification::make()
                ->title('لا يوجد مصممون متاحون')
                ->body('يرجى إضافة أو اختيار مصمم أولاً')
                ->danger()
                ->send();

            return;
        }

        $clientId = Client::where('company', $this->company)->value('id');

        $order = Order::create([
            'client_name' => $this->company,
            'description' => $this->description,
            'designer_id' => $designer->id,
            'assigner_id' => auth()->id(),
            'status' => 'pending',
            'client_id' => $clientId,
        ]);

        OrderAssigned::dispatch($order);

        Notification::make()
            ->title('تم إنشاء الطلب')
            ->body("أسند إلى: {$designer->user->name}")
            ->success()
            ->send();

        $this->company = null;
        $this->description = null;
        $this->designer_id = null;
        $this->resetTable();
    }

    /**
     * استخراج وتجهيز قائمة أسماء العملاء من النص الملصق (كل سطر عميل).
     *
     * @return array<int, string>
     */
    public function getParsedBulkCompanies(): array
    {
        if (blank($this->bulk_companies)) {
            return [];
        }

        $lines = preg_split('/[\r\n]+/', (string) $this->bulk_companies);
        $companies = [];

        foreach ($lines as $line) {
            // إزالة الترقيم والنقاط والشرطات من بداية كل سطر (مثل: "1. ", "1- ", "• ")
            $cleaned = trim(preg_replace('/^[\d\.\-\*\•\(\)\[\]\:\s]+/u', '', $line));
            $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));

            if (! empty($cleaned)) {
                $companies[] = $cleaned;
            }
        }

        return $companies;
    }

    /**
     * إنشاء طلبات متعددة دفعة واحدة من قائمة العملاء الملصقة.
     */
    public function createBulk(): void
    {
        $companies = $this->getParsedBulkCompanies();

        if (empty($companies)) {
            Notification::make()
                ->title('يرجى لصق أو كتابة أسماء العملاء')
                ->body('لم يتم العثور على أي أسماء في النص المدخل.')
                ->danger()
                ->send();

            return;
        }

        $assignedDesigner = null;
        if ($this->bulk_designer_id) {
            $assignedDesigner = Designer::with('user')->find($this->bulk_designer_id);
        }

        $selectedDesigners = session('selected_designers', []);
        $roundRobinService = app(RoundRobinService::class);
        $createdCount = 0;
        $assignedDesignersSummary = [];

        foreach ($companies as $companyName) {
            $designer = $assignedDesigner ?: $roundRobinService->assignNextDesigner(
                ! empty($selectedDesigners) ? $selectedDesigners : null
            );

            if (! $designer) {
                continue;
            }

            $clientId = Client::where('company', $companyName)->value('id');

            $order = Order::create([
                'client_name' => $companyName,
                'description' => $this->bulk_description,
                'designer_id' => $designer->id,
                'assigner_id' => auth()->id(),
                'status' => 'pending',
                'client_id' => $clientId,
            ]);

            OrderAssigned::dispatch($order);

            $designerName = $designer->user->name ?? 'مصمم';
            $assignedDesignersSummary[$designerName] = ($assignedDesignersSummary[$designerName] ?? 0) + 1;
            $createdCount++;
        }

        if ($createdCount === 0) {
            Notification::make()
                ->title('تعذر إنشاء الطلبات')
                ->body('لا يوجد مصممون متاحون للتوزيع.')
                ->danger()
                ->send();

            return;
        }

        $summaryParts = [];
        foreach ($assignedDesignersSummary as $name => $count) {
            $summaryParts[] = "{$name} ({$count})";
        }
        $summaryText = implode('، ', $summaryParts);

        Notification::make()
            ->title("تم إنشاء {$createdCount} طلب بنجاح ✅")
            ->body("المصممون المكلفون: {$summaryText}")
            ->success()
            ->send();

        $this->bulk_companies = null;
        $this->bulk_designer_id = null;
        $this->bulk_description = null;
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        Table::$defaultDateDisplayFormat = 'l Y-m-d';

        return $table
            ->poll('20s')
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('client_name')
                    ->label('اسم العميل')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('designer.user.name')
                    ->label('المصمم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assigner.name')
                    ->label('المرسل')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Pending => 'warning',
                        OrderStatus::InProgress => 'info',
                        OrderStatus::InReview => 'primary',
                        OrderStatus::Completed => 'success',
                        OrderStatus::Cancelled => 'danger',
                    })
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->getLabel()),

                TextColumn::make('created_at')
                    ->label('وقت الإرسال')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultGroup(
                Group::make('created_at')
                    ->label('التاريخ')
                    ->date()
                    ->collapsible(),
            )
            ->groupingSettingsHidden()
            ->paginated([10, 25, 50])
            ->actions([
                Action::make('approveOrder')
                    ->label('مكتمل')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Order $record): void {
                        if ($record->status === OrderStatus::Completed) {
                            return;
                        }

                        $record->update([
                            'status' => OrderStatus::Completed,
                        ]);

                        Notification::make()
                            ->title('تم اعتماد الطلب')
                            ->body('تم تغيير حالة الطلب إلى مكتمل بنجاح.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Order $record): bool => $record->status !== OrderStatus::Completed),

                Action::make('requestRevision')
                    ->label('طلب تعديل')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->modalHeading('طلب تعديل على الطلب')
                    ->modalDescription('يرجى كتابة ملاحظات وتوجيهات التعديل ليتم إرسالها للمصمم.')
                    ->modalSubmitActionLabel('إرسال التعديل')
                    ->form([
                        Textarea::make('revision_notes')
                            ->label('ملاحظات التعديل')
                            ->placeholder('اكتب تفاصيل التعديل المطلوب بدقة...')
                            ->rows(3),
                    ])
                    ->action(function (Order $record, array $data): void {
                        $notes = trim($data['revision_notes'] ?? '');
                        $cleanDescription = trim(preg_replace('/\s*🔄\s*\[ملاحظة تعديل[^\]]*\]:?[\s\S]*$/u', '', $record->description ?? ''));

                        if (! empty($notes)) {
                            $timestamp = now()->format('Y-m-d H:i');
                            $newDescription = ! empty($cleanDescription)
                                ? $cleanDescription."\n\n🔄 [ملاحظة تعديل - {$timestamp}]:\n".$notes
                                : "🔄 [ملاحظة تعديل - {$timestamp}]:\n".$notes;
                        } else {
                            $newDescription = $cleanDescription;
                        }

                        $record->update([
                            'status' => OrderStatus::Pending,
                            'description' => $newDescription,
                        ]);

                        OrderRevisionRequested::dispatch($record);

                        Notification::make()
                            ->title('تم طلب التعديل')
                            ->body('تم إرجاع الطلب للمصمم للتعديل.')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::InReview),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkComplete')
                        ->label('تعيين كمكتمل')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            $records->each(function (Order $record): void {
                                if ($record->status !== OrderStatus::Completed) {
                                    $record->update([
                                        'status' => OrderStatus::Completed,
                                    ]);
                                }
                            });

                            Notification::make()
                                ->title('تم تحديث الحالة')
                                ->body('تم تغيير حالة الطلبات المحددة إلى مكتمل.')
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole(['admin', 'super_admin']);

        $baseQuery = Order::query();

        if (! $isAdmin) {
            $baseQuery->where('assigner_id', auth()->id());
        }

        $inReviewCount = (clone $baseQuery)->where('status', OrderStatus::InReview)->count();
        $activeCount = (clone $baseQuery)->whereIn('status', [OrderStatus::Pending, OrderStatus::InProgress])->count();
        $completedCount = (clone $baseQuery)->where('status', OrderStatus::Completed)->count();
        $allCount = (clone $baseQuery)->count();

        return [
            'in_review' => [
                'label' => 'بانتظار المراجعة',
                'badge' => $inReviewCount,
            ],
            'active' => [
                'label' => 'الطلبات النشطة',
                'badge' => $activeCount,
            ],
            'completed' => [
                'label' => 'الطلبات المكتملة',
                'badge' => $completedCount,
            ],
            'all' => [
                'label' => 'الكل',
                'badge' => $allCount,
            ],
        ];
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();
        $isAdmin = $user && $user->hasRole(['admin', 'super_admin']);

        $query = Order::query()
            ->with(['designer.user', 'assigner']);

        if (! $isAdmin) {
            $query->where('assigner_id', auth()->id());
        }

        if ($this->activeTab === 'in_review') {
            $query->where('status', OrderStatus::InReview);
        } elseif ($this->activeTab === 'active') {
            $query->whereIn('status', [OrderStatus::Pending, OrderStatus::InProgress]);
        } elseif ($this->activeTab === 'completed') {
            $query->where('status', OrderStatus::Completed);
        }

        return $query->orderBy('created_at', 'desc');
    }
}
