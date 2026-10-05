<?php

namespace App\Filament\Pages;

use App\Models\ClientTagDistribution;
use App\Models\SocialMedia;
use App\Models\SocialMediaPost;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * واجهة العمل المخصصة لموظف السوشيال ميديا لنشر التصاميم على المنصات.
 */
class SocialMediaPublishing extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'واجهة السوشيال ميديا';

    protected static ?string $title = 'واجهة نشر السوشيال ميديا';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.social-media-publishing';

    public ?string $activeTab = 'due_now';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['social_media', 'admin'])
            || $user->can('view_social_media_publishing');
    }

    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    /**
     * تجميع إحصائيات النشر في استعلام موحد عالي الكفاءة.
     */
    public function getPublishingStats(): array
    {
        $baseQuery = ClientTagDistribution::query()
            ->whereIn('status', ['sending', 'completed'])
            ->whereHas('clientDesigner.client.contracts', function ($q) {
                $q->where('status', 'active');
            })
            ->whereHas('clientDesigner.client.socialMedia');

        $pendingQuery = (clone $baseQuery)->whereNotFullyPublished();

        $nowStr = now()->toDateTimeString();
        $todayStr = today()->toDateString();
        $startOfDayStr = now()->startOfDay()->toDateTimeString();

        $stats = (clone $pendingQuery)
            ->selectRaw('
                COUNT(CASE WHEN scheduled_sending_at <= ? THEN 1 END) as due_now_count,
                COUNT(CASE WHEN DATE(scheduled_sending_at) = ? THEN 1 END) as today_count,
                COUNT(CASE WHEN scheduled_sending_at < ? THEN 1 END) as overdue_count,
                COUNT(CASE WHEN scheduled_sending_at > ? THEN 1 END) as upcoming_count
            ', [
                $nowStr,
                $todayStr,
                $startOfDayStr,
                $nowStr,
            ])
            ->first();

        $allCount = (clone $baseQuery)->count();
        $completedCount = (clone $baseQuery)->whereFullyPublished()->count();
        $publishedTodayCount = SocialMediaPost::whereDate('published_at', $todayStr)->count();

        return [
            'all_count' => (int) $allCount,
            'completed_count' => (int) $completedCount,
            'due_now_count' => (int) ($stats->due_now_count ?? 0),
            'today_count' => (int) ($stats->today_count ?? 0),
            'overdue_count' => (int) ($stats->overdue_count ?? 0),
            'upcoming_count' => (int) ($stats->upcoming_count ?? 0),
            'published_today_count' => (int) $publishedTodayCount,
        ];
    }

    public function getTabs(): array
    {
        $stats = $this->getPublishingStats();

        return [
            'due_now' => [
                'label' => 'المستحقة الآن / المتأخرة',
                'badge' => $stats['due_now_count'],
                'badgeColor' => $stats['due_now_count'] > 0 ? 'danger' : 'gray',
                'icon' => 'heroicon-o-fire',
            ],
            'today' => [
                'label' => 'مواعيد اليوم',
                'badge' => $stats['today_count'],
                'badgeColor' => 'primary',
                'icon' => 'heroicon-o-calendar',
            ],
            'overdue' => [
                'label' => 'المتأخرة فقط',
                'badge' => $stats['overdue_count'],
                'badgeColor' => $stats['overdue_count'] > 0 ? 'danger' : 'gray',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            'upcoming' => [
                'label' => 'القادمة لاحقاً',
                'badge' => $stats['upcoming_count'],
                'badgeColor' => 'info',
                'icon' => 'heroicon-o-clock',
            ],
            'completed' => [
                'label' => 'المكتملة والمنشورة',
                'badge' => $stats['completed_count'],
                'badgeColor' => 'success',
                'icon' => 'heroicon-o-check-circle',
            ],
            'all' => [
                'label' => 'جميع المواعيد',
                'badge' => $stats['all_count'],
                'badgeColor' => 'gray',
                'icon' => 'heroicon-o-list-bullet',
            ],
        ];
    }

    protected function getViewData(): array
    {
        $stats = $this->getPublishingStats();

        return [
            'readyToPublishCount' => $stats['due_now_count'],
            'overdueCount' => $stats['overdue_count'],
            'publishedTodayCount' => $stats['published_today_count'],
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            '/admin' => 'الرئيسية',
            static::getUrl() => 'واجهة السوشيال ميديا',
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = ClientTagDistribution::query()
                    ->whereIn('status', ['sending', 'completed'])
                    ->whereHas('clientDesigner.client.contracts', function ($q) {
                        $q->where('status', 'active');
                    })
                    ->whereHas('clientDesigner.client.socialMedia')
                    ->with([
                        'clientDesigner.client.socialMedia',
                        'clientDesigner.designer',
                        'tag',
                        'idea',
                        'socialMediaPosts.socialMedia',
                    ])
                    ->orderBy('scheduled_sending_at', 'asc');

                if ($this->activeTab === 'due_now') {
                    $query->whereNotFullyPublished()->where('scheduled_sending_at', '<=', now());
                } elseif ($this->activeTab === 'today') {
                    $query->whereNotFullyPublished()->whereDate('scheduled_sending_at', \Carbon\Carbon::today());
                } elseif ($this->activeTab === 'overdue') {
                    $query->whereNotFullyPublished()->where('scheduled_sending_at', '<', \Carbon\Carbon::now()->startOfDay());
                } elseif ($this->activeTab === 'upcoming') {
                    $query->whereNotFullyPublished()->where('scheduled_sending_at', '>', now());
                } elseif ($this->activeTab === 'completed') {
                    $query->whereFullyPublished();
                }

                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('clientDesigner.client.company')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->color('primary')
                    ->description(fn ($record) => $record->clientDesigner?->client?->client_name ?: 'اشتراك نشط'),

                Tables\Columns\TextColumn::make('tag.name')
                    ->label('التاق والمحتوى')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->description(fn ($record) => $record->idea?->name ?? 'محتوى مخصص'),

                Tables\Columns\ImageColumn::make('attachment_path')
                    ->label('التصميم')
                    ->disk('public')
                    ->square()
                    ->width(64)
                    ->height(64)
                    ->extraImgAttributes([
                        'class' => 'rounded-xl object-cover ring-1 ring-gray-200 dark:ring-gray-700 shadow-sm transition hover:scale-105 cursor-pointer',
                        'loading' => 'lazy',
                    ])
                    ->action(
                        Tables\Actions\Action::make('view_image')
                            ->modalHeading('معاينة التصميم النهائي')
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false)
                            ->modalWidth('3xl')
                            ->modalContent(fn ($record) => new HtmlString(
                                '<div class="flex flex-col items-center gap-3 p-2">
                                    <img src="'.asset('storage/'.$record->attachment_path).'" class="max-w-full max-h-[70vh] object-contain rounded-xl shadow-lg ring-1 ring-gray-200 dark:ring-gray-800" alt="التصميم النهائي" />
                                    <div class="flex items-center gap-3 mt-2">
                                        <a href="'.asset('storage/'.$record->attachment_path).'" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300 hover:bg-primary-100 transition">
                                            فتح بالحجم الكامل ↗
                                        </a>
                                    </div>
                                </div>'
                            ))
                    ),

                Tables\Columns\TextColumn::make('scheduled_sending_at')
                    ->label('موعد الإرسال والنشر')
                    ->dateTime('l, d M - h:i A')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state < now() ? 'danger' : 'success')
                    ->icon(fn ($state) => $state < now() ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-calendar-days')
                    ->description(fn ($record) => $record->scheduled_sending_at?->diffForHumans()),

                Tables\Columns\TextColumn::make('platforms_list')
                    ->label('منصات العميل والنشر')
                    ->html()
                    ->getStateUsing(function (ClientTagDistribution $record) {
                        $clientMedia = $record->clientDesigner?->client?->socialMedia ?? collect();
                        $publishedMediaIds = $record->socialMediaPosts->pluck('social_media_id')->toArray();

                        if ($clientMedia->isEmpty()) {
                            $publishedCount = count($publishedMediaIds);
                            if ($publishedCount > 0) {
                                return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">✓ نُشر على '.$publishedCount.' منصة عامة</span>';
                            }

                            return '<span class="text-xs text-gray-400">لم تحدد منصات للعميل</span>';
                        }

                        $publishedCount = 0;
                        $badgesHtml = '';

                        foreach ($clientMedia as $media) {
                            $isPublished = in_array($media->id, $publishedMediaIds);
                            $url = $media->pivot->account_url ?? null;
                            $targetLink = $url ? (str_starts_with($url, 'http') ? $url : 'https://'.$url) : null;

                            if ($isPublished) {
                                $publishedCount++;
                                if ($targetLink) {
                                    $badgesHtml .= '<a href="'.e($targetLink).'" target="_blank" title="فتح الحساب" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 hover:bg-emerald-200 transition">✓ '.e($media->name).' ↗</a>';
                                } else {
                                    $badgesHtml .= '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">✓ '.e($media->name).'</span>';
                                }
                            } else {
                                if ($targetLink) {
                                    $badgesHtml .= '<a href="'.e($targetLink).'" target="_blank" title="فتح الحساب للنشر" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 hover:bg-amber-200 transition">⏳ '.e($media->name).' ↗</a>';
                                } else {
                                    $badgesHtml .= '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">⏳ '.e($media->name).'</span>';
                                }
                            }
                        }

                        $totalCount = $clientMedia->count();
                        $statusBadge = $publishedCount === $totalCount
                            ? '<span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">مكتمل ('.$publishedCount.'/'.$totalCount.')</span>'
                            : ($publishedCount > 0
                                ? '<span class="text-[11px] font-bold text-amber-600 dark:text-amber-400">جزئي ('.$publishedCount.'/'.$totalCount.')</span>'
                                : '<span class="text-[11px] font-medium text-gray-400 dark:text-gray-500">بانتظار النشر (0/'.$totalCount.')</span>');

                        return '<div class="space-y-1"><div>'.$statusBadge.'</div><div class="flex flex-wrap gap-1">'.$badgesHtml.'</div></div>';
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->label('المنصة المستهدفة')
                    ->options(fn () => SocialMedia::pluck('name', 'id')->toArray())
                    ->query(function (Builder $query, array $data) {
                        if (! empty($data['value'])) {
                            $query->whereHas('clientDesigner.client.socialMedia', function ($q) use ($data) {
                                $q->where('social_media.id', $data['value']);
                            });
                        }
                    }),

                Tables\Filters\SelectFilter::make('publishing_status')
                    ->label('حالة النشر')
                    ->options([
                        'pending' => 'بانتظار النشر بالكامل',
                        'partial' => 'تم النشر جزئياً',
                        'completed' => 'مكتمل النشر على كافة المنصات',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $val = $data['value'] ?? null;
                        if ($val === 'pending') {
                            $query->wherePendingPublishing();
                        } elseif ($val === 'partial') {
                            $query->wherePartiallyPublished();
                        } elseif ($val === 'completed') {
                            $query->whereFullyPublished();
                        }
                    }),

                Tables\Filters\SelectFilter::make('client')
                    ->label('تصفية بالعميل')
                    ->relationship('clientDesigner.client', 'company')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('date_range')
                    ->label('فترة تاريخ مخصصة')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')->label('من تاريخ'),
                        Forms\Components\DatePicker::make('to_date')->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from_date'] ?? null, fn ($q, $date) => $q->whereDate('scheduled_sending_at', '>=', $date))
                            ->when($data['to_date'] ?? null, fn ($q, $date) => $q->whereDate('scheduled_sending_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('publishToSocialMedia')
                    ->label('تم النشر على المنصة')
                    ->icon('heroicon-m-share')
                    ->color('success')
                    ->button()
                    ->modalHeading(fn (ClientTagDistribution $record) => 'تسجيل النشر — '.($record->clientDesigner?->client?->company ?? 'العميل'))
                    ->modalWidth('2xl')
                    ->form(function (ClientTagDistribution $record) {
                        $client = $record->clientDesigner?->client;
                        $clientPlatforms = $client?->socialMedia ?? collect();

                        if ($clientPlatforms->isEmpty()) {
                            $platformOptions = SocialMedia::all()->pluck('name', 'id')->toArray();
                            $helperText = 'تنبيه: لم يتم ربط منصات تواصل محددة بهذا العميل سابقاً. يمكنك اختيار المنصة من القائمة العامة.';
                        } else {
                            $platformOptions = $clientPlatforms->pluck('name', 'id')->toArray();
                            $helperText = 'المنصات المحددة للعميل: '.$client->company;
                        }

                        // Pre-select platforms that have not been published yet
                        $alreadyPublishedIds = $record->socialMediaPosts->pluck('social_media_id')->toArray();
                        $defaultSelection = array_diff(array_keys($platformOptions), $alreadyPublishedIds);

                        return [
                            Forms\Components\Placeholder::make('client_details_preview')
                                ->label('')
                                ->content(function () use ($client, $clientPlatforms) {
                                    $notesHtml = '';
                                    if (! empty($client?->notes)) {
                                        $parsedMarkdown = Str::markdown($client->notes);
                                        $escapedNotes = htmlspecialchars($client->notes, ENT_QUOTES, 'UTF-8');
                                        $notesHtml = <<<HTML
                                        <div x-data="{ copied: false }" class="p-3.5 mb-3 rounded-xl border border-primary-200 dark:border-primary-800/40 bg-primary-50/50 dark:bg-primary-950/20">
                                            <textarea x-ref="notesContent" class="sr-only" readonly>{$escapedNotes}</textarea>
                                            <div class="flex items-center justify-between mb-2 pb-1 border-b border-primary-200/60 dark:border-primary-800/30">
                                                <div class="flex items-center gap-1.5 font-bold text-xs text-primary-700 dark:text-primary-300">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                                                    </svg>
                                                    <span>توجيهات وهاشتاقات النشر</span>
                                                </div>
                                                <button 
                                                    type="button" 
                                                    @click="navigator.clipboard.writeText(\$refs.notesContent.value); copied = true; setTimeout(() => copied = false, 2000)" 
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-md bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition cursor-pointer"
                                                >
                                                    <span x-show="!copied" class="flex items-center gap-1">
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                        نسخ التوجيهات
                                                    </span>
                                                    <span x-show="copied" class="text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1" style="display: none;">
                                                        ✓ تم النسخ
                                                    </span>
                                                </button>
                                            </div>
                                            <div class="prose prose-xs dark:prose-invert max-w-none text-gray-700 dark:text-gray-200">
                                                {$parsedMarkdown}
                                            </div>
                                        </div>
                                        HTML;
                                    }

                                    $linksHtml = '';
                                    if ($clientPlatforms->isNotEmpty()) {
                                        $linksHtml .= '<div class="p-3 mb-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-900/50">';
                                        $linksHtml .= '<div class="text-xs font-bold text-gray-600 dark:text-gray-400 mb-2">روابط حسابات العميل المباشرة:</div>';
                                        $linksHtml .= '<div class="flex flex-wrap gap-1.5">';
                                        foreach ($clientPlatforms as $p) {
                                            $url = $p->pivot->account_url ?? null;
                                            $pNotes = $p->pivot->notes ? ' ('.$p->pivot->notes.')' : '';
                                            if ($url) {
                                                $fullUrl = str_starts_with($url, 'http') ? $url : 'https://'.$url;
                                                $linksHtml .= '<a href="'.e($fullUrl).'" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-lg bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-300 hover:bg-primary-200 transition">';
                                                $linksHtml .= e($p->name).$pNotes.' <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>';
                                                $linksHtml .= '</a>';
                                            } else {
                                                $linksHtml .= '<span class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-lg bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-300">'.e($p->name).$pNotes.'</span>';
                                            }
                                        }
                                        $linksHtml .= '</div></div>';
                                    }

                                    if (empty($notesHtml) && empty($linksHtml)) {
                                        return new HtmlString('<div class="text-xs text-gray-400 mb-2 p-2 rounded-lg bg-gray-50 dark:bg-gray-800/40">لا توجد ملاحظات أو روابط خاصة مسجلة لهذا العميل.</div>');
                                    }

                                    return new HtmlString($notesHtml.$linksHtml);
                                })
                                ->columnSpanFull(),

                            Forms\Components\CheckboxList::make('social_media_ids')
                                ->label('اختر المنصات التي تم النشر عليها')
                                ->options($platformOptions)
                                ->default(array_values($defaultSelection))
                                ->required()
                                ->helperText($helperText),

                            Forms\Components\Textarea::make('notes')
                                ->label('ملاحظات النشر (اختياري)')
                                ->placeholder('أضف أي تفاصيل أو تعليق بخصوص النشر...')
                                ->rows(2),
                        ];
                    })
                    ->action(function (ClientTagDistribution $record, array $data) {
                        $socialMediaIds = $data['social_media_ids'] ?? [];
                        $notes = $data['notes'] ?? null;
                        $now = now();
                        $userId = auth()->id();

                        $createdCount = 0;
                        foreach ($socialMediaIds as $mediaId) {
                            SocialMediaPost::create([
                                'client_tag_distribution_id' => $record->id,
                                'social_media_id' => $mediaId,
                                'user_id' => $userId,
                                'published_at' => $now,
                                'notes' => $notes,
                            ]);
                            $createdCount++;
                        }

                        Notification::make()
                            ->title('تم تسجيل النشر بنجاح')
                            ->body("تم تسجيل النشر على ({$createdCount}) منصة في تمام الساعة ".$now->format('h:i A'))
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),

                Tables\Actions\Action::make('skipPublishing')
                    ->label('تخطي النشر')
                    ->tooltip('استبعاد التصميم من النشر على السوشيال ميديا مع بقائه في واجهة الإرسال')
                    ->icon('heroicon-m-eye-slash')
                    ->color('warning')
                    ->visible(fn (ClientTagDistribution $record) => $record->social_media_publishing_status !== 'completed')
                    ->requiresConfirmation()
                    ->modalHeading('تخطي النشر على السوشيال ميديا')
                    ->modalDescription('هل أنت متأكد من تخطي نشر هذا التصميم على منصات التواصل؟ سيبقى التصميم متاحاً في واجهة الإرسال ولن يتأثر تسليمه للعميل.')
                    ->modalSubmitActionLabel('نعم، تخطي النشر')
                    ->action(function (ClientTagDistribution $record) {
                        $client = $record->clientDesigner?->client;
                        $clientPlatforms = $client?->socialMedia ?? collect();
                        $now = now();
                        $userId = auth()->id();

                        if ($clientPlatforms->isEmpty()) {
                            $defaultPlatform = SocialMedia::first();
                            if ($defaultPlatform) {
                                SocialMediaPost::updateOrCreate(
                                    [
                                        'client_tag_distribution_id' => $record->id,
                                        'social_media_id' => $defaultPlatform->id,
                                    ],
                                    [
                                        'user_id' => $userId,
                                        'published_at' => $now,
                                        'notes' => 'تم التخطي / مستبعد من النشر',
                                    ]
                                );
                            }
                        } else {
                            foreach ($clientPlatforms as $platform) {
                                SocialMediaPost::updateOrCreate(
                                    [
                                        'client_tag_distribution_id' => $record->id,
                                        'social_media_id' => $platform->id,
                                    ],
                                    [
                                        'user_id' => $userId,
                                        'published_at' => $now,
                                        'notes' => 'تم التخطي / مستبعد من النشر',
                                    ]
                                );
                            }
                        }

                        Notification::make()
                            ->title('تم تخطي النشر على السوشيال ميديا')
                            ->body('تم استبعاد التصميم من قائمة النشر المستحقة مع بقائه في واجهة الإرسال.')
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),

                Tables\Actions\Action::make('resetPublishing')
                    ->label('إعادة للنشر')
                    ->tooltip('إلغاء التخطي أو النشر وإعادة المهمة لقائمة المستحقة للنشر')
                    ->icon('heroicon-m-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('إعادة التصميم لقائمة النشر')
                    ->modalDescription('هل ترغب في إلغاء التخطي/النشر وإعادة هذا التصميم إلى قائمة المهام المستحقة للنشر؟')
                    ->modalSubmitActionLabel('نعم، إعادة للنشر')
                    ->visible(fn (ClientTagDistribution $record) => $record->socialMediaPosts()->exists())
                    ->action(function (ClientTagDistribution $record) {
                        $record->socialMediaPosts()->delete();

                        Notification::make()
                            ->title('تمت إعادة التصميم لقائمة النشر')
                            ->body('أصبح التصميم الآن بانتظار النشر مجدداً.')
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),

                Tables\Actions\Action::make('download')
                    ->label('')
                    ->tooltip('تحميل التصميم')
                    ->icon('heroicon-m-cloud-arrow-down')
                    ->color('gray')
                    ->iconButton()
                    ->action(function (ClientTagDistribution $record) {
                        if (! $record->attachment_path) {
                            Notification::make()
                                ->title('لا يوجد ملف للتحميل')
                                ->danger()
                                ->send();

                            return;
                        }

                        $filePath = \Illuminate\Support\Facades\Storage::disk('public')->path($record->attachment_path);

                        if (! file_exists($filePath)) {
                            Notification::make()
                                ->title('الملف غير موجود في الخادم')
                                ->danger()
                                ->send();

                            return;
                        }

                        return response()->download($filePath);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulkPublish')
                    ->label('تسجيل النشر للمحددين')
                    ->icon('heroicon-m-share')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('social_media_id')
                            ->label('المنصة')
                            ->options(fn () => SocialMedia::pluck('name', 'id')->toArray())
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('ملاحظات النشر (اختياري)')
                            ->rows(2),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $now = now();
                        $userId = auth()->id();
                        $mediaId = $data['social_media_id'];
                        $notes = $data['notes'] ?? null;

                        $count = 0;
                        foreach ($records as $record) {
                            SocialMediaPost::updateOrCreate(
                                [
                                    'client_tag_distribution_id' => $record->id,
                                    'social_media_id' => $mediaId,
                                ],
                                [
                                    'user_id' => $userId,
                                    'published_at' => $now,
                                    'notes' => $notes,
                                ]
                            );
                            $count++;
                        }

                        Notification::make()
                            ->title('تم تسجيل النشر الجماعي بنجاح')
                            ->body("تم تسجيل النشر لـ ({$count}) تصميم بنجاح.")
                            ->success()
                            ->send();

                        $this->resetTable();
                    })
                    ->deselectRecordsAfterCompletion(),

                Tables\Actions\BulkAction::make('bulkSkipPublishing')
                    ->label('تخطي النشر للمحددين')
                    ->icon('heroicon-m-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('تخطي النشر للتصاميم المحددة')
                    ->modalDescription('هل أنت متأكد من تخطي النشر على السوشيال ميديا للتصاميم المحددة؟ ستبقى متاحة في واجهة الإرسال ولن يتأثر تسليمها.')
                    ->modalSubmitActionLabel('نعم، تخطي المحدد')
                    ->action(function (Collection $records) {
                        $now = now();
                        $userId = auth()->id();
                        $count = 0;

                        foreach ($records as $record) {
                            $client = $record->clientDesigner?->client;
                            $clientPlatforms = $client?->socialMedia ?? collect();

                            if ($clientPlatforms->isEmpty()) {
                                $defaultPlatform = SocialMedia::first();
                                if ($defaultPlatform) {
                                    SocialMediaPost::updateOrCreate(
                                        [
                                            'client_tag_distribution_id' => $record->id,
                                            'social_media_id' => $defaultPlatform->id,
                                        ],
                                        [
                                            'user_id' => $userId,
                                            'published_at' => $now,
                                            'notes' => 'تم التخطي / مستبعد من النشر',
                                        ]
                                    );
                                }
                            } else {
                                foreach ($clientPlatforms as $platform) {
                                    SocialMediaPost::updateOrCreate(
                                        [
                                            'client_tag_distribution_id' => $record->id,
                                            'social_media_id' => $platform->id,
                                        ],
                                        [
                                            'user_id' => $userId,
                                            'published_at' => $now,
                                            'notes' => 'تم التخطي / مستبعد من النشر',
                                        ]
                                    );
                                }
                            }
                            $count++;
                        }

                        Notification::make()
                            ->title('تم تخطي النشر بنجاح')
                            ->body("تم تخطي واستبعاد ({$count}) تصميم من النشر على السوشيال ميديا.")
                            ->success()
                            ->send();

                        $this->resetTable();
                    })
                    ->deselectRecordsAfterCompletion(),

                Tables\Actions\BulkAction::make('bulkResetPublishing')
                    ->label('إلغاء التخطي وإعادة المحدد للنشر')
                    ->icon('heroicon-m-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('إعادة التصاميم المحددة لقائمة النشر')
                    ->modalDescription('سيتم إلغاء سجلات التخطي/النشر للتصاميم المحددة وإعادتها كـ "بانتظار النشر".')
                    ->action(function (Collection $records) {
                        $count = 0;
                        foreach ($records as $record) {
                            $record->socialMediaPosts()->delete();
                            $count++;
                        }

                        Notification::make()
                            ->title('تمت إعادة التصاميم بنجاح')
                            ->body("تمت إعادة ({$count}) تصميم إلى قائمة النشر.")
                            ->success()
                            ->send();

                        $this->resetTable();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->striped()
            ->poll('30s');
    }
}
