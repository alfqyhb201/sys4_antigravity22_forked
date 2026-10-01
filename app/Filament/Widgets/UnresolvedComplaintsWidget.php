<?php

namespace App\Filament\Widgets;

use App\Filament\Enums\ComplaintStatus;
use App\Filament\Resources\ClientResource;
use App\Models\Complaint;
use Filament\Notifications\Notification;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * ودجة تعرض الشكاوي غير المحلولة في لوحة تحكم الأدمن.
 *
 * جدول مصغر (نصف العرض) يعرض أحدث الشكاوي ذات الحالة "new"
 * مع إمكانية حل الشكوى مباشرة دون الدخول إلى صفحة التعديل.
 */
class UnresolvedComplaintsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = '📋 الشكاوي غير المحلولة';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = '1/2';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_complaint') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->striped()
            ->query(
                Complaint::query()
                    ->where('status', ComplaintStatus::New->value)
                    ->with('client')
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('client.company')
                    ->label('العميل')
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-m-building-office')
                    ->url(fn (Complaint $record): ?string => $record->client ? ClientResource::getUrl('view', ['record' => $record->client]) : null),

                Tables\Columns\TextColumn::make('description')
                    ->label('الشكوى')
                    ->html()
                    ->limit(20)
                    ->tooltip(fn (Complaint $record): string => strip_tags($record->description))
                    ->wrap(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable()
                    ->icon('heroicon-m-calendar'),
            ])
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->label(' ')
                    ->tooltip('حل الشكوى')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->size(ActionSize::Large)
                    ->visible(fn (Complaint $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد حل الشكوى')
                    ->modalDescription('هل أنت متأكد من أن هذه الشكوى تم حلها؟')
                    ->action(function (Complaint $record): void {
                        $record->update([
                            'status' => ComplaintStatus::Resolved,
                            'updated_by_user' => auth()->id(),
                            'resolved_by_user' => auth()->id(),
                        ]);

                        Notification::make()
                            ->title('تم حل الشكوى بنجاح')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateIcon('heroicon-o-check-badge')
            ->emptyStateHeading('لا توجد شكاوي غير محلولة')
            ->emptyStateDescription('جميع الشكاوي تم حلها ✓')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->defaultSort('created_at', 'desc');
    }
}
