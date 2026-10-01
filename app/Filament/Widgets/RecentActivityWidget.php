<?php

namespace App\Filament\Widgets;

use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Spatie\Activitylog\Models\Activity;

class RecentActivityWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = '🕐 آخر النشاطات';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = '1/2';

    private function translateModel(string $subjectType): string
    {
        $map = [
            'App\Models\Client' => 'عميل',
            'App\Models\Contract' => 'اشتراك',
            'App\Models\Invoice' => 'فاتورة',
            'App\Models\Receipt' => 'سند قبض',
            'App\Models\Complaint' => 'شكوى',
            'App\Models\DesignTask' => 'مهمة تصميم',
            'App\Models\Designer' => 'مصمم',
            'App\Models\Order' => 'طلب',
            'App\Models\ClientTagDistribution' => 'توزيع تصميم',
            'App\Models\User' => 'مستخدم',
            'App\Models\Tag' => 'وسم',
            'App\Models\Idea' => 'فكرة',
            'App\Models\Category' => 'فئة',
            'App\Models\Currency' => 'عملة',
            'App\Models\CurrencySetting' => 'إعدادات العملة',
            'App\Models\SystemSetting' => 'الإعدادات العامة',
            'App\Models\ExchangeRate' => 'سعر صرف',
            'App\Models\Location' => 'موقع',
            'App\Models\SocialMedia' => 'تواصل اجتماعي',
            'App\Models\ClientNeed' => 'حاجة عميل',
            'App\Models\TagGroup' => 'مجموعة وسوم',
            'App\Models\Custody' => 'عهدة',
            'App\Models\ClientTemplate' => 'قالب عميل',
        ];

        return $map[$subjectType] ?? class_basename($subjectType);
    }

    private function translateEvent(string $description): string
    {
        return match ($description) {
            'created' => 'إنشاء',
            'updated' => 'تعديل',
            'deleted' => 'حذف',
            'restored' => 'استعادة',
            default => $description,
        };
    }

    private function eventColor(string $description): string
    {
        return match ($description) {
            'created' => 'success',
            'updated' => 'warning',
            'deleted' => 'danger',
            default => 'gray',
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()
                    ->with('causer')
                    ->latest()
                    ->limit(50)
            )
            ->columns([
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('المستخدم')
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-m-user')
                    ->placeholder('النظام'),

                Tables\Columns\TextColumn::make('description')
                    ->label('الحدث')
                    ->badge()
                    ->color(fn (string $state): string => $this->eventColor($state))
                    ->formatStateUsing(fn (string $state): string => $this->translateEvent($state)),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('العنصر')
                    ->formatStateUsing(fn (string $state): string => $this->translateModel($state))
                    ->icon('heroicon-m-document')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('الوقت')
                    ->since()
                    ->sortable()
                    ->icon('heroicon-m-clock'),
            ])
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateHeading('لا توجد نشاطات بعد')
            ->poll('60s')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->defaultSort('created_at', 'desc');
    }
}
