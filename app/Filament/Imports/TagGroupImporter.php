<?php

namespace App\Filament\Imports;

use App\Models\TagGroup;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Checkbox;

class TagGroupImporter extends Importer
{
    protected static ?string $model = TagGroup::class;

    // public static function getOptionsFormComponents(): array
    // {
    //     return [
    //         Checkbox::make('authUserId')
    //             ->label('Update existing records'),
    //     ];
    // }

    // protected function beforeCreate(): void
    // {
    //     $options = $this->getOptions();

    //     if (isset($options['authUserId'])) {
    //         $this->data['added_by_user'] = $options['authUserId'];
    //     }
    // }

    protected function beforeSave(): void
    {
        $options = $this->getOptions();

        if (isset($options['authUserId'])) {
            $this->record->added_by_user = $options['authUserId'];
            $this->record->updated_by_user = $options['authUserId'];
        }
    }

    protected function afterSave(): void
    {
        $model = $this->record;

        // معالجة categories — الفصل بفاصلة , في الـ CSV
        $categoryRow = trim($this->data['categories'] ?? '');

        // إذا كانت القيمة "all" يتم ربط جميع التصنيفات الموجودة
        if (strtolower($categoryRow) === 'all') {
            $model->categories()->sync(\App\Models\Category::pluck('id'));

            return;
        }

        $categoryNames = array_filter(array_map('trim', explode(',', $categoryRow)));
        $categoryIds = [];
        foreach ($categoryNames as $name) {
            $category = \App\Models\Category::firstOrCreate(['name' => $name]);
            $categoryIds[] = $category->id;
        }
        $model->categories()->sync($categoryIds);
    }

    public function resolveRecord(): ?TagGroup
    {
        return TagGroup::firstOrNew([
            'name' => $this->data['name'],
        ]);
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('اسم المجموعة')
                ->requiredMapping()
                ->rules(['required', 'max:100']),
            ImportColumn::make('categories')
                ->label('التصنيفات')
                ->fillRecordUsing(fn () => null),
        ];
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'تم استيراد مجموعات الوسوم بنجاح. عدد '.
            number_format($import->successful_rows).' صفاً تم استيرادها.';

        if ($failed = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failed).' صفاً فشل في الاستيراد.';
        }

        return $body;
    }
}
