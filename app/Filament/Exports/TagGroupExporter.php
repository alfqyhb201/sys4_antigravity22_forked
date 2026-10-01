<?php

namespace App\Filament\Exports;

use App\Models\TagGroup;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Contracts\Queue\ShouldQueue;

class TagGroupExporter extends Exporter implements ShouldQueue
{
    protected static ?string $model = TagGroup::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name')
                ->label('اسم المجموعة'),
            ExportColumn::make('categories')
                ->label('التصنيفات')
                ->formatStateUsing(fn ($state, $record) => $record->categories->pluck('name')->join(', ')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'تم تصدير مجموعات الوسوم بنجاح. عدد '.number_format($export->successful_rows).' صفاً تم تصديرها.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' صفاً فشل في التصدير.';
        }

        return $body;
    }
}
