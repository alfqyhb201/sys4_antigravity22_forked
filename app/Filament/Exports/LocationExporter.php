<?php

namespace App\Filament\Exports;

use App\Models\Location;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Contracts\Queue\ShouldQueue;

class LocationExporter extends Exporter implements ShouldQueue
{
    protected static ?string $model = Location::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name')
                ->label('اسم الموقع'),
            ExportColumn::make('created_at')
                ->label('تاريخ الإضافة'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'تم تصدير المواقع بنجاح. عدد '.number_format($export->successful_rows).' صفاً تم تصديرها.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' صفاً فشل في التصدير.';
        }

        return $body;
    }
}
