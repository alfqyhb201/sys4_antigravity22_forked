<?php

namespace App\Filament\Exports;

use App\Models\Category;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Contracts\Queue\ShouldQueue;

class CategoryExporter extends Exporter implements ShouldQueue
{
    protected static ?string $model = Category::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name')
                ->label('اسم التصنيف'),
            ExportColumn::make('created_at')
                ->label('تاريخ الإضافة'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'تم تصدير التصنيفات بنجاح. عدد '.number_format($export->successful_rows).' صفاً تم تصديرها.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' صفاً فشل في التصدير.';
        }

        return $body;
    }
}
