<?php

namespace App\Filament\Imports;

use App\Models\Location;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class LocationImporter extends Importer
{
    protected static ?string $model = Location::class;

    protected function beforeSave(): void
    {
        $options = $this->getOptions();

        if (isset($options['authUserId'])) {
            $this->record->added_by_user = $options['authUserId'];
            $this->record->updated_by_user = $options['authUserId'];
        }
    }

    public function resolveRecord(): ?Location
    {
        return Location::firstOrNew([
            'name' => $this->data['name'],
        ]);
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('اسم الموقع')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'تم استيراد المواقع بنجاح. عدد '.
            number_format($import->successful_rows).' صفاً تم استيرادها.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' صفاً فشل في الاستيراد.';
        }

        return $body;
    }
}
