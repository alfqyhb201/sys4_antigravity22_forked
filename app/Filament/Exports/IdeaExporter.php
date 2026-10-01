<?php

namespace App\Filament\Exports;

use App\Models\Idea;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class IdeaExporter extends Exporter
{
    protected static ?string $model = Idea::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name'),
            ExportColumn::make('content'),
            ExportColumn::make('description'),
            ExportColumn::make('repeat_for_clients'),
            ExportColumn::make('scheduled_at')
                ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('Y-m-d H:i:s') : null),
            ExportColumn::make('idea_file'),
            ExportColumn::make('is_visible_in_generator'),
            ExportColumn::make('clients')
                ->label('Clients')
                ->formatStateUsing(fn ($state, $record) => $record->clients->pluck('company')->join(', ')),

            ExportColumn::make('tags')
                ->label('Tags')
                ->formatStateUsing(fn ($state, $record) => $record->tags->pluck('name')->join(', ')),

            ExportColumn::make('locations')
                ->label('Locations')
                ->formatStateUsing(fn ($state, $record) => $record->locations->pluck('name')->join(', ')),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
            ExportColumn::make('added_by_user'),
            ExportColumn::make('updated_by_user'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your idea export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
