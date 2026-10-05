<?php

namespace App\Filament\Pages;

use App\Models\Session;
use App\Models\User;
use Filament\Actions\Action as HeaderAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ActiveSessionsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'نشاط المستخدمين';

    protected static ?string $title = 'مراقبة نشاط وتواجد المستخدمين';

    protected static ?string $slug = 'active-sessions';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationGroup = 'الإعدادات';

    protected static string $view = 'filament.pages.active-sessions';

    /**
     * فلتر التبويب النشط (الافتراضي: المتصلون الآن)
     */
    public string $timeFilter = '5_minutes';

    /**
     * التحقق من صلاحية الوصول للصفحة
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'admin'])
            || $user->can('view_active_sessions');
    }

    /**
     * تغيير الفلتر
     */
    public function setTimeFilter(string $filter): void
    {
        $this->timeFilter = $filter;
        $this->resetPage();
    }

    /**
     * خيارات التبويبات (المتصلون الآن / جميع المستخدمين)
     */
    public function getTimeFilterOptions(): array
    {
        $now = now()->timestamp;

        return [
            '5_minutes' => [
                'label' => 'المتصلون الآن',
                'count' => User::whereHas('sessions', fn (Builder $q) => $q->where('last_activity', '>=', $now - (5 * 60)))->count(),
            ],
            'all' => [
                'label' => 'جميع المستخدمين',
                'count' => User::count(),
            ],
        ];
    }

    /**
     * إحصائيات سريعة للنشاط
     */
    public function getSessionStats(): array
    {
        $now = now()->timestamp;
        $fiveMinutesAgo = $now - (5 * 60);

        $onlineNow = User::whereHas('sessions', fn (Builder $q) => $q->where('last_activity', '>=', $fiveMinutesAgo))->count();
        $totalUsers = User::count();
        $offlineCount = max(0, $totalUsers - $onlineNow);

        return [
            'online_now' => $onlineNow,
            'offline_count' => $offlineCount,
            'total_users' => $totalUsers,
            'current_ip' => request()->ip(),
        ];
    }

    /**
     * تكوين جدول المستخدمين مع تفاصيل نشاطهم
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $now = now()->timestamp;
                $query = User::query()
                    ->with(['roles', 'latestSession'])
                    ->addSelect([
                        'latest_activity' => Session::select('last_activity')
                            ->whereColumn('sessions.user_id', 'users.id')
                            ->orderByDesc('last_activity')
                            ->limit(1),
                    ])
                    ->orderByDesc('latest_activity');

                return match ($this->timeFilter) {
                    '5_minutes' => $query->whereHas('sessions', fn (Builder $q) => $q->where('last_activity', '>=', $now - (5 * 60))),
                    default => $query,
                };
            })
            ->columns([
                ImageColumn::make('profile_image')
                    ->label('الصورة')
                    ->circular()
                    ->defaultImageUrl(fn (User $record) => 'https://ui-avatars.com/api/?name='.urlencode($record->name).'&color=7F9CF5&background=EBF4FF'),

                TextColumn::make('name')
                    ->label('المستخدم')
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->description(fn (User $record) => $record->email ?: ($record->username ?: ''))
                    ->icon('heroicon-m-user'),

                TextColumn::make('roles.name')
                    ->label('الدور / الصلاحية')
                    ->badge()
                    ->color('primary')
                    ->default('مستخدم'),

                TextColumn::make('status_badge')
                    ->label('حالة الاتصال')
                    ->badge()
                    ->state(function (User $record) {
                        if ($record->id === auth()->id()) {
                            return 'أنت (الجلسة الحالية)';
                        }

                        $hasActiveSession = $record->sessions()
                            ->where('last_activity', '>=', now()->timestamp - 300)
                            ->exists();

                        if ($hasActiveSession) {
                            return 'متصل الآن';
                        }

                        $activityInfo = $record->getLastActivityInfo();
                        if (! empty($activityInfo['timestamp'])) {
                            $diffSeconds = now()->timestamp - $activityInfo['timestamp'];
                            if ($diffSeconds <= 3600) {
                                return 'نشط مؤخراً';
                            }
                        }

                        return 'غير متصل';
                    })
                    ->color(function (User $record) {
                        if ($record->id === auth()->id()) {
                            return 'warning';
                        }

                        $hasActiveSession = $record->sessions()
                            ->where('last_activity', '>=', now()->timestamp - 300)
                            ->exists();

                        if ($hasActiveSession) {
                            return 'success';
                        }

                        $activityInfo = $record->getLastActivityInfo();
                        if (! empty($activityInfo['timestamp'])) {
                            $diffSeconds = now()->timestamp - $activityInfo['timestamp'];
                            if ($diffSeconds <= 3600) {
                                return 'info';
                            }
                        }

                        return 'gray';
                    })
                    ->icon(function (User $record) {
                        if ($record->id === auth()->id()) {
                            return 'heroicon-m-check-badge';
                        }

                        $hasActiveSession = $record->sessions()
                            ->where('last_activity', '>=', now()->timestamp - 300)
                            ->exists();

                        if ($hasActiveSession) {
                            return 'heroicon-m-signal';
                        }

                        $activityInfo = $record->getLastActivityInfo();
                        if (! empty($activityInfo['timestamp'])) {
                            $diffSeconds = now()->timestamp - $activityInfo['timestamp'];
                            if ($diffSeconds <= 3600) {
                                return 'heroicon-m-clock';
                            }
                        }

                        return 'heroicon-m-moon';
                    }),

                TextColumn::make('latest_activity')
                    ->label('آخر نشاط')
                    ->state(function (User $record) {
                        $info = $record->getLastActivityInfo();

                        return $info['timestamp'] ?? null;
                    })
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return 'لم يسجل دخول بعد';
                        }

                        return Carbon::createFromTimestamp($state)->diffForHumans();
                    })
                    ->tooltip(function ($state) {
                        return $state ? Carbon::createFromTimestamp($state)->format('Y-m-d h:i:s A') : null;
                    })
                    ->sortable(),

                TextColumn::make('ip_address')
                    ->label('عنوان IP')
                    ->state(function (User $record) {
                        $info = $record->getLastActivityInfo();

                        return $info['ip_address'] ?? null;
                    })
                    ->default('-')
                    ->copyable()
                    ->copyMessage('تم نسخ عنوان IP')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-globe-alt'),

                TextColumn::make('user_agent')
                    ->label('الجهاز والمتصفح')
                    ->state(function (User $record) {
                        $info = $record->getLastActivityInfo();

                        return $info['user_agent'] ?? null;
                    })
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return '-';
                        }
                        $details = Session::parseUserAgent($state);

                        return "{$details['browser']} — {$details['platform']} ({$details['device_type']})";
                    })
                    ->icon(function ($state) {
                        if (! $state) {
                            return 'heroicon-o-device-phone-mobile';
                        }
                        $details = Session::parseUserAgent($state);

                        return $details['device_icon'];
                    })
                    ->wrap()
                    ->color('gray')
                    ->default('-'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('تصفية حسب الدور')
                    ->relationship('roles', 'name')
                    ->preload(),
            ])
            ->actions([
                Action::make('terminate_user_sessions')
                    ->label('إنهاء جلسات المستخدم')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد تسجيل خروج المستخدم')
                    ->modalDescription('هل أنت متأكد من رغبتك في إنهاء جميع جلسات هذا المستخدم وتسجيل خروجه فوراً؟')
                    ->modalSubmitActionLabel('نعم، تسجيل الخروج')
                    ->visible(fn (User $record) => $record->sessions()->exists())
                    ->action(function (User $record) {
                        $isCurrent = $record->id === auth()->id();
                        $record->sessions()->delete();

                        Notification::make()
                            ->title('تم إنهاء جلسات المستخدم بنجاح')
                            ->success()
                            ->send();

                        if ($isCurrent) {
                            auth()->logout();
                            redirect()->route('filament.admin.auth.login');
                        }
                    }),
            ])
            ->emptyStateHeading('لا يوجد مستخدمون متصلون')
            ->emptyStateDescription('لا يوجد أي مستخدم متصل حالياً في النظام.')
            ->emptyStateIcon('heroicon-o-user-minus');
        // ->poll('30s');
    }

    /**
     * الإجراءات العلوية في رأس الصفحة
     */
    protected function getHeaderActions(): array
    {
        return [
            HeaderAction::make('refresh')
                ->label('تحديث فوري')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->resetTable()),
        ];
    }
}
