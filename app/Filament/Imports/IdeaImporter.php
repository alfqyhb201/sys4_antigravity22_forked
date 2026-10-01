<?php

namespace App\Filament\Imports;

use App\Models\Client;
use App\Models\Idea;
use App\Models\Location;
use App\Models\Tag;
use Carbon\Carbon;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class IdeaImporter extends Importer
{
    protected static ?string $model = Idea::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->example('فكرة إعلانية مميزة'),
            ImportColumn::make('content'),
            ImportColumn::make('description'),
            ImportColumn::make('repeat_for_clients')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('scheduled_at')
                ->rules(['nullable', 'date'])
                ->castStateUsing(function ($state) {
                    return Carbon::parse($state)->format('Y-m-d H:i:s');
                }),
            ImportColumn::make('idea_file'),
            ImportColumn::make('is_visible_in_generator')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),

            // أعمدة العلاقات — تمنع تعيينها على Model مباشرة
            ImportColumn::make('clients')->label('Clients')
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('tags')->label('Tags')
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('locations')->label('Locations')
                ->fillRecordUsing(fn () => null),
        ];
    }

    /**
     * ربط العلاقات بعد حفظ النموذج
     */
    protected function afterSave(): void
    {
        $model = $this->record;
        $row = $this->originalData;

        // معالجة clients
        $clientCompanies = array_filter(array_map('trim', explode(',', $row['clients'] ?? '')));
        $clientIds = [];
        foreach ($clientCompanies as $company) {
            $client = Client::firstOrCreate(['company' => $company]);
            $clientIds[] = $client->id;
        }
        $model->clients()->sync($clientIds);

        // معالجة tags
        $tagNames = array_filter(array_map('trim', explode(',', $row['tags'] ?? '')));
        $tagIds = [];
        foreach ($tagNames as $tagName) {
            $tag = Tag::firstOrCreate(['name' => $tagName]);
            $tagIds[] = $tag->id;
        }
        $model->tags()->sync($tagIds);

        // معالجة locations
        $locationNames = array_filter(array_map('trim', explode(',', $row['locations'] ?? '')));
        $locationIds = [];
        foreach ($locationNames as $locationName) {
            $location = Location::firstOrCreate(['name' => $locationName]);
            $locationIds[] = $location->id;
        }
        $model->locations()->sync($locationIds);
    }

    /**
     * تحديد سجل موجود أو جديد لتحديث أو إنشاء
     */
    public function resolveRecord(): ?Idea
    {
        if (! empty($this->data['id'])) {
            return Idea::find($this->data['id']) ?? new Idea;
        }

        return new Idea;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your idea import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
