<?php

namespace App\Filament\Exports;

use App\Models\Tag;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class TagExporter extends Exporter
{
    protected static ?string $model = Tag::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name')
                ->label('اسم الوسم'),
            ExportColumn::make('importance')
                ->label('درجة الأهمية'),
            ExportColumn::make('tag_group')
                ->label('مجموعة الوسوم')
                ->formatStateUsing(fn ($state, $record) => $record->tagGroup?->name),
            ExportColumn::make('is_repetition')
                ->label('تمكين التكرار'),
            ExportColumn::make('repetition')
                ->label('نوع التكرار'),
            ExportColumn::make('weekly_times')
                ->label('مرات التكرار الأسبوعي'),
            ExportColumn::make('monthly_times')
                ->label('مرات التكرار الشهري'),
            ExportColumn::make('yearly_times')
                ->label('مرات التكرار السنوي'),
            ExportColumn::make('is_there_date_for_sending')
                ->label('جدولة الإرسال'),
            ExportColumn::make('date_for_sending_yearly')
                ->label('التاريخ السنوي'),
            ExportColumn::make('weekly_day')
                ->label('أيام الأسبوع')
                ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state),
            ExportColumn::make('weekly_time')
                ->label('وقت الإرسال'),
            ExportColumn::make('weekly_time_sm')
                ->label('وقت السوشيال ميديا'),
            ExportColumn::make('is_active')
                ->label('نشط'),
            ExportColumn::make('is_auto_assigned')
                ->label('تعيين تلقائي'),
            ExportColumn::make('categories')
                ->label('التصنيفات')
                ->formatStateUsing(fn ($state, $record) => $record->categories->pluck('name')->join(', ')),
            ExportColumn::make('locations')
                ->label('المواقع')
                ->formatStateUsing(fn ($state, $record) => $record->locations->pluck('name')->join(', ')),
            ExportColumn::make('clients')
                ->label('العملاء')
                ->formatStateUsing(fn ($state, $record) => $record->clients->pluck('company')->join(', ')),
            ExportColumn::make('created_at')
                ->label('تاريخ الإضافة'),
            ExportColumn::make('updated_at')
                ->label('آخر تحديث'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'تم تصدير الوسوم بنجاح. عدد '.number_format($export->successful_rows).' صفاً تم تصديرها.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' صفاً فشل في التصدير.';
        }

        return $body;
    }
}
