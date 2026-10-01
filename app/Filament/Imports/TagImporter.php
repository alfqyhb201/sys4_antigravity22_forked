<?php

namespace App\Filament\Imports;

use App\Models\Category;
use App\Models\Client;
use App\Models\Location;
use App\Models\Tag;
use App\Models\TagGroup;
use Carbon\Carbon;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class TagImporter extends Importer
{
    protected static ?string $model = Tag::class;

    /**
     * الحصول على القيمة من بيانات الصف باستخدام أسماء أعمدة متعددة (عربي/إنجليزي).
     * يبحث في originalData أولاً (يحتوي جميع أعمدة CSV)، ثم في data (الأعمدة المعرفة).
     */
    private function getRowValue(string $english, string $arabic, mixed $default = ''): mixed
    {
        return $this->originalData[$english]
            ?? $this->originalData[$arabic]
            ?? $this->data[$english]
            ?? $this->data[$arabic]
            ?? $default;
    }

    public static function getColumns(): array
    {
        return [
            // ========== الأعمدة الأساسية ==========
            ImportColumn::make('name')
                ->label('اسم الوسم')
                ->rules(['required', 'max:100']),

            ImportColumn::make('importance')
                ->label('درجة الأهمية')
                ->rules(['nullable', 'in:veryhigh,high,medium,low']),

            // مجموعة الوسوم — تُحل في beforeSave
            ImportColumn::make('tag_group')
                ->label('مجموعة الوسوم')
                ->fillRecordUsing(fn () => null),

            // ========== حقول التكرار ==========
            ImportColumn::make('is_repetition')
                ->boolean()
                ->rules(['boolean'])
                ->label('تمكين التكرار'),

            ImportColumn::make('repetition')
                ->rules(['nullable', 'in:weekly,yearly'])
                ->label('نوع التكرار'),

            ImportColumn::make('weekly_times')
                ->rules(['nullable', 'integer', 'min:0'])
                ->label('مرات التكرار الأسبوعي'),

            ImportColumn::make('monthly_times')
                ->rules(['nullable', 'integer', 'min:0'])
                ->label('مرات التكرار الشهري'),

            ImportColumn::make('yearly_times')
                ->rules(['nullable', 'integer', 'min:0'])
                ->label('مرات التكرار السنوي'),

            // ========== حقول الجدولة ==========
            ImportColumn::make('is_there_date_for_sending')
                ->boolean()
                ->rules(['boolean'])
                ->label('جدولة الإرسال'),

            ImportColumn::make('date_for_sending_yearly')
                ->rules(['nullable', 'date'])
                ->castStateUsing(function ($state) {
                    if (empty($state)) {
                        return null;
                    }

                    return Carbon::parse($state)->format('Y-m-d');
                })
                ->label('التاريخ السنوي'),

            ImportColumn::make('weekly_day')
                ->label('أيام الأسبوع')
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('weekly_time')
                ->rules(['nullable'])
                ->label('وقت الإرسال'),

            ImportColumn::make('weekly_time_sm')
                ->rules(['nullable'])
                ->label('وقت السوشيال ميديا'),

            // ========== الحالة ==========
            ImportColumn::make('is_active')
                ->boolean()
                ->rules(['boolean'])
                ->label('نشط'),

            ImportColumn::make('is_auto_assigned')
                ->boolean()
                ->rules(['boolean'])
                ->label('تعيين تلقائي'),

            // ========== أعمدة العلاقات M:N ==========
            ImportColumn::make('categories')
                ->label('التصنيفات')
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('locations')
                ->label('المواقع')
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('clients')
                ->label('العملاء')
                ->fillRecordUsing(fn () => null),
        ];
    }

    public function resolveRecord(): ?Tag
    {
        return Tag::firstOrNew([
            'name' => $this->getRowValue('name', 'الاسم'),
        ]);
    }

    protected function beforeSave(): void
    {
        $options = $this->getOptions();

        if (isset($options['authUserId'])) {
            $this->record->added_by_user = $options['authUserId'];
            $this->record->updated_by_user = $options['authUserId'];
        }

        // حل tag_group_id قبل الحفظ — يدعم العربية والإنجليزية
        $tagGroupName = $this->getRowValue('tag_group', 'مجموعة الوسوم');
        if (! empty($tagGroupName)) {
            $tagGroup = TagGroup::firstOrCreate([
                'name' => trim($tagGroupName),
            ]);

            if (isset($options['authUserId'])) {
                $tagGroup->added_by_user = $options['authUserId'];
                $tagGroup->updated_by_user = $options['authUserId'];
                $tagGroup->save();
            }

            $this->record->tag_group_id = $tagGroup->id;
        }
    }

    protected function afterSave(): void
    {
        $model = $this->record;

        // معالجة weekly_day — تحويل CSV إلى array
        $weeklyDayRow = $this->getRowValue('weekly_day', 'أيام الأسبوع');
        if (! empty($weeklyDayRow)) {
            $days = array_map('trim', explode(',', $weeklyDayRow));
            $model->weekly_day = $days;
            $model->save();
        }

        // معالجة categories
        $categoryRow = $this->getRowValue('categories', 'التصنيفات');
        $categoryNames = array_filter(array_map('trim', explode(',', $categoryRow)));
        $categoryIds = [];
        foreach ($categoryNames as $name) {
            $category = Category::firstOrCreate(['name' => $name]);
            $categoryIds[] = $category->id;
        }
        $model->categories()->sync($categoryIds);

        // معالجة locations
        $locationRow = $this->getRowValue('locations', 'المواقع');
        $locationNames = array_filter(array_map('trim', explode(',', $locationRow)));
        $locationIds = [];
        foreach ($locationNames as $name) {
            $location = Location::firstOrCreate(['name' => $name]);
            $locationIds[] = $location->id;
        }
        $model->locations()->sync($locationIds);

        // معالجة clients — نبحث عن العملاء الموجودين فقط
        $clientRow = $this->getRowValue('clients', 'العملاء');
        $clientCompanies = array_filter(array_map('trim', explode(',', $clientRow)));
        $clientIds = [];
        foreach ($clientCompanies as $company) {
            $client = Client::firstWhere(['company' => $company]);
            if ($client) {
                $clientIds[] = $client->id;
            }
        }
        $model->clients()->sync($clientIds);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'تم استيراد الوسوم بنجاح. عدد '.number_format($import->successful_rows).' صفاً تم استيرادها.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' صفاً فشل في الاستيراد.';
        }

        return $body;
    }
}
