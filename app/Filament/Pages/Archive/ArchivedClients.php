<?php

namespace App\Filament\Pages\Archive;

use App\Models\Client;
use App\Models\ClientTagDistribution;
use App\Models\Designer;
use App\Models\Tag;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\WithPagination;
use ZipArchive;

class ArchivedClients extends Page implements HasTable
{
    use InteractsWithTable, WithPagination {
        InteractsWithTable::resetPage insteadof WithPagination;
        InteractsWithTable::setPage insteadof WithPagination;
        InteractsWithTable::previousPage insteadof WithPagination;
        InteractsWithTable::nextPage insteadof WithPagination;
        WithPagination::resetPage as resetLivewirePage;
        WithPagination::setPage as setLivewirePage;
    }

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'أرشيف العملاء';

    protected static ?string $title = 'أرشيف العملاء والتصاميم المكتملة';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationGroup = 'الأرشيف';

    protected static string $view = 'filament.pages.archive.archived-clients';

    /**
     * التبويب النشط: 'clients' (حسب العملاء) أو 'all_designs' (كافة التصاميم).
     */
    public string $activeTab = 'clients';

    /**
     * خصائص مستكشف التصاميم العام (Global Designs Explorer).
     */
    public string $explorerSearch = '';

    public string $explorerTag = 'all';

    public string $explorerDesigner = 'all';

    public string $explorerClient = 'all';

    public string $explorerDateRange = 'all';

    public string $explorerViewMode = 'grid';

    public int $explorerPerPage = 18;

    public array $selectedDesignIds = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasRole(['admin', 'super_admin', 'supervisor']) || $user->can('view_archive');
    }

    public function updatedExplorerSearch(): void
    {
        $this->resetPage('designsPage');
    }

    public function updatedExplorerTag(): void
    {
        $this->resetPage('designsPage');
    }

    public function updatedExplorerDesigner(): void
    {
        $this->resetPage('designsPage');
    }

    public function updatedExplorerClient(): void
    {
        $this->resetPage('designsPage');
    }

    public function updatedExplorerDateRange(): void
    {
        $this->resetPage('designsPage');
    }

    public function resetExplorerFilters(): void
    {
        $this->explorerSearch = '';
        $this->explorerTag = 'all';
        $this->explorerDesigner = 'all';
        $this->explorerClient = 'all';
        $this->explorerDateRange = 'all';
        $this->selectedDesignIds = [];
        $this->resetPage('designsPage');
    }

    public function toggleSelectAllOnPage(array $pageIds): void
    {
        if (count($pageIds) === 0) {
            return;
        }

        $allSelected = count(array_intersect($this->selectedDesignIds, $pageIds)) === count($pageIds);

        if ($allSelected) {
            $this->selectedDesignIds = array_values(array_diff($this->selectedDesignIds, $pageIds));
        } else {
            $this->selectedDesignIds = array_values(array_unique(array_merge($this->selectedDesignIds, $pageIds)));
        }
    }

    /**
     * استعلام مستكشف التصاميم العام مع تطبيق جميع الفلاتر.
     */
    public function getGlobalDesignsQuery(): Builder
    {
        $query = ClientTagDistribution::query()
            ->where('status', 'completed')
            ->with([
                'clientDesigner.client.category',
                'clientDesigner.client.location',
                'clientDesigner.designer.user',
                'tag',
                'idea',
                'sender',
            ]);

        if (! empty($this->explorerSearch)) {
            $search = trim($this->explorerSearch);
            $query->where(function (Builder $q) use ($search) {
                $q->whereHas('tag', fn (Builder $sub) => $sub->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('idea', fn (Builder $sub) => $sub->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('clientDesigner.client', fn (Builder $sub) => $sub->where('company', 'like', "%{$search}%"))
                    ->orWhereHas('clientDesigner.designer.user', fn (Builder $sub) => $sub->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('sender', fn (Builder $sub) => $sub->where('name', 'like', "%{$search}%"))
                    ->orWhere('custom_idea', 'like', "%{$search}%");
            });
        }

        if ($this->explorerTag !== 'all') {
            $query->where('tag_id', $this->explorerTag);
        }

        if ($this->explorerDesigner !== 'all') {
            $query->whereHas('clientDesigner', fn (Builder $q) => $q->where('designer_id', $this->explorerDesigner));
        }

        if ($this->explorerClient !== 'all') {
            $query->whereHas('clientDesigner', fn (Builder $q) => $q->where('client_id', $this->explorerClient));
        }

        if ($this->explorerDateRange !== 'all') {
            match ($this->explorerDateRange) {
                'today' => $query->whereDate('completed_at', today()),
                'last_7_days' => $query->where('completed_at', '>=', now()->subDays(7)),
                'last_30_days' => $query->where('completed_at', '>=', now()->subDays(30)),
                'this_month' => $query->whereMonth('completed_at', now()->month)->whereYear('completed_at', now()->year),
                'last_month' => $query->whereMonth('completed_at', now()->subMonth()->month)->whereYear('completed_at', now()->subMonth()->year),
                default => null,
            };
        }

        return $query->orderBy('completed_at', 'desc')->orderBy('updated_at', 'desc');
    }

    public function getGlobalDesignsProperty(): LengthAwarePaginator
    {
        return $this->getGlobalDesignsQuery()->paginate($this->explorerPerPage, ['*'], 'designsPage');
    }

    public function getArchiveStatsProperty(): array
    {
        $clientsCount = Client::whereHas('completedDistributions')->count();
        $totalDesignsCount = ClientTagDistribution::where('status', 'completed')->count();

        return [
            'clients_count' => $clientsCount,
            'total_designs_count' => $totalDesignsCount,
        ];
    }

    public function getExplorerTagsProperty(): Collection
    {
        return Tag::whereHas('clientTagDistributions', fn (Builder $q) => $q->where('status', 'completed'))
            ->withCount(['clientTagDistributions' => fn (Builder $q) => $q->where('status', 'completed')])
            ->orderBy('name')
            ->get();
    }

    public function getExplorerDesignersProperty(): Collection
    {
        return Designer::whereHas('clientDesigners.distributions', fn (Builder $q) => $q->where('status', 'completed'))
            ->with('user')
            ->get()
            ->sortBy('user.name')
            ->values();
    }

    public function getExplorerClientsProperty(): Collection
    {
        return Client::whereHas('completedDistributions')
            ->orderBy('company')
            ->get(['id', 'company', 'client_name']);
    }

    /**
     * تنزيل التصاميم المحددة في مستكشف التصاميم العام كملف ZIP.
     */
    public function downloadSelectedExplorerZip()
    {
        if (empty($this->selectedDesignIds)) {
            Notification::make()
                ->title('يرجى تحديد تصميم واحد على الأقل')
                ->warning()
                ->send();

            return null;
        }

        $distributions = ClientTagDistribution::query()
            ->whereIn('id', $this->selectedDesignIds)
            ->where('status', 'completed')
            ->with(['idea', 'tag', 'clientDesigner.client'])
            ->get();

        if ($distributions->isEmpty()) {
            Notification::make()
                ->title('لا توجد تصاميم صالحة للتحميل')
                ->warning()
                ->send();

            return null;
        }

        $zipFileName = 'selected-designs-'.now()->timestamp.'.zip';
        $zipPath = Storage::disk('public')->path($zipFileName);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $addedCount = 0;
            foreach ($distributions as $dist) {
                if ($dist->attachment_path && Storage::disk('public')->exists($dist->attachment_path)) {
                    $filePath = Storage::disk('public')->path($dist->attachment_path);
                    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                    $clientName = Str::slug($dist->clientDesigner?->client?->company ?? 'client', '_');
                    $ideaSlug = Str::slug(Str::limit($dist->idea?->name ?? $dist->tag?->name ?? 'design', 20), '_');
                    $fileNameInZip = "{$clientName}_{$ideaSlug}_{$dist->id}.{$extension}";

                    $zip->addFile($filePath, $fileNameInZip);
                    $addedCount++;
                }
            }
            $zip->close();

            if ($addedCount > 0 && file_exists($zipPath)) {
                $this->selectedDesignIds = [];

                return response()->download($zipPath)->deleteFileAfterSend();
            }

            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
        }

        Notification::make()
            ->title('الملفات غير متوفرة في مساحة التخزين')
            ->danger()
            ->send();

        return null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn () => Client::query()
                    ->whereHas('completedDistributions')
                    ->with(['category', 'location'])
                    ->withCount('completedDistributions')
            )
            ->columns([
                Tables\Columns\TextColumn::make('company')
                    ->label('اسم الشركة')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('primary')
                    ->description(fn (Client $record) => $record->client_name ? "المسؤول: {$record->client_name}" : null),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('التصنيف')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('location.name')
                    ->label('الموقع')
                    ->icon('heroicon-m-map-pin')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('completed_distributions_count')
                    ->label('عدد التصاميم المؤرشفة')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-photo')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('التصنيف')
                    ->relationship('category', 'name')
                    ->preload(),

                Tables\Filters\SelectFilter::make('location')
                    ->label('الموقع')
                    ->relationship('location', 'name')
                    ->preload(),
            ])
            ->recordAction('quickPeek')
            ->actions([
                Tables\Actions\Action::make('quickPeek')
                    ->label('معاينة سريعة')
                    ->tooltip('فتح المعاينة الجانبية السريعة لتصاميم العميل')
                    ->icon('heroicon-m-eye')
                    ->color('primary')
                    ->button()
                    ->slideOver()
                    ->modalWidth(MaxWidth::SevenExtraLarge)
                    ->modalHeading(fn (Client $record) => 'أرشيف تصاميم: '.$record->company)
                    ->modalDescription('معاينة سريعة لكافة تصاميم العميل المؤرشفة مع إمكانية التحميل وطلب التعديل المباشر.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(function (Client $record) {
                        $designs = ClientTagDistribution::query()
                            ->where('status', 'completed')
                            ->whereHas('clientDesigner', fn (Builder $q) => $q->where('client_id', $record->id))
                            ->with([
                                'clientDesigner.designer.user',
                                'tag',
                                'idea',
                                'sender',
                            ])
                            ->orderBy('completed_at', 'desc')
                            ->orderBy('updated_at', 'desc')
                            ->get();

                        return view('filament.modals.client-archive-quick-peek-drawer', [
                            'client' => $record,
                            'designs' => $designs,
                        ]);
                    }),

                Tables\Actions\Action::make('viewDesigns')
                    ->label('الصفحة الكاملة')
                    ->tooltip('فتح صفحة الأرشيف المنفصلة')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->iconButton()
                    ->url(fn (Client $record): string => ArchivedClientDesigns::getUrl(['client_id' => $record->id])),
            ])
            ->striped();
    }

    /**
     * طلب تعديل على تصميم مباشرة من المعاينة الجانبية أو مستكشف التصاميم.
     */
    public function submitRevision(int $distributionId, string $feedback): void
    {
        $distribution = ClientTagDistribution::with(['clientDesigner.client', 'clientDesigner.designer.user', 'tag'])->find($distributionId);

        if (! $distribution) {
            Notification::make()
                ->title('لم يتم العثور على التصميم')
                ->danger()
                ->send();

            return;
        }

        $feedback = trim($feedback);
        if (empty($feedback)) {
            Notification::make()
                ->title('يرجى كتابة ملاحظات التعديل')
                ->warning()
                ->send();

            return;
        }

        $distribution->update([
            'status' => 'changes_requested',
            'reviewer_feedback' => $feedback,
            'distribution_date' => now()->format('Y-m-d'),
        ]);

        // تنقيص عداد الكليشة إذا كان أكبر من صفر
        $client = $distribution->clientDesigner?->client;
        if ($client && $client->cliche_counter > 0) {
            $client->decrement('cliche_counter');
        }

        // إشعار المصمم
        $designerUser = $distribution->clientDesigner?->designer?->user;
        if ($designerUser) {
            $clientName = $client?->company ?: ($client?->client_name ?: 'العميل');
            $tagName = $distribution->tag?->name;
            $tagText = $tagName ? " (وسم: {$tagName})" : '';

            Notification::make()
                ->title('طلب تعديل على التصميم 📝')
                ->body("تم طلب تعديل على تصميم {$clientName}{$tagText} - ملاحظات: {$feedback}")
                ->icon('heroicon-o-arrow-path')
                ->iconColor('warning')
                ->warning()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('عرض لوحة المصمم')
                        ->url('/admin/designer-dashboard'),
                ])
                ->sendToDatabase($designerUser, isEventDispatched: true);
        }

        Notification::make()
            ->title('تم طلب التعديل بنجاح')
            ->body('تمت إعادة التصميم إلى لوحة المصمم لإجراء التعديلات.')
            ->success()
            ->send();
    }

    /**
     * تنزيل جميع تصاميم العميل المؤرشفة في ملف مضغوط ZIP.
     */
    public function downloadZip(int $clientId)
    {
        $client = Client::findOrFail($clientId);

        $distributions = ClientTagDistribution::query()
            ->where('status', 'completed')
            ->whereHas('clientDesigner', fn (Builder $q) => $q->where('client_id', $clientId))
            ->with(['idea', 'tag'])
            ->get();

        if ($distributions->isEmpty()) {
            Notification::make()
                ->title('لا توجد تصاميم للتحميل')
                ->warning()
                ->send();

            return null;
        }

        $zipFileName = 'client-'.$client->id.'-designs-'.now()->timestamp.'.zip';
        $zipPath = Storage::disk('public')->path($zipFileName);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $addedCount = 0;
            foreach ($distributions as $dist) {
                if ($dist->attachment_path && Storage::disk('public')->exists($dist->attachment_path)) {
                    $filePath = Storage::disk('public')->path($dist->attachment_path);
                    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                    $ideaSlug = Str::slug(Str::limit($dist->idea?->name ?? $dist->tag?->name ?? 'design', 25), '_');
                    $fileNameInZip = "{$ideaSlug}_{$dist->id}.{$extension}";

                    $zip->addFile($filePath, $fileNameInZip);
                    $addedCount++;
                }
            }
            $zip->close();

            if ($addedCount > 0 && file_exists($zipPath)) {
                return response()->download($zipPath)->deleteFileAfterSend();
            }

            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
        }

        Notification::make()
            ->title('الملفات غير متوفرة في مساحة التخزين')
            ->danger()
            ->send();

        return null;
    }
}
