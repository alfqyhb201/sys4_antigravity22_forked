<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Client;
use App\Models\Location;
use App\Models\Tag;
use App\Models\TagGroup;
use Carbon\Carbon;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * خدمة مخصصة لاستيراد الوسوم (Tags) من ملفات CSV و Excel.
 *
 * هذه الخدمة مبنية من الصفر بدون استخدام
 * Filament\Actions\Imports\Importer، وتعتمد على
 * OpenSpout لقراءة الملفات.
 */
class TagImportService
{
    /**
     * صيغ الملفات المدعومة.
     */
    public const SUPPORTED_FORMATS = ['csv', 'xlsx', 'xls'];

    /**
     * قواعد التحقق من صحة حقول الوسم.
     */
    public const VALIDATION_RULES = [
        'name' => ['required', 'string', 'max:100'],
        'importance' => ['nullable', 'string', 'in:veryhigh,high,medium,low'],
        'tag_group' => ['nullable', 'string', 'max:100'],
        'is_repetition' => ['nullable', 'boolean'],
        'repetition' => ['nullable', 'string', 'in:weekly,monthly,yearly'],
        'weekly_times' => ['nullable', 'integer', 'min:0'],
        'monthly_times' => ['nullable', 'integer', 'min:0'],
        'yearly_times' => ['nullable', 'integer', 'min:0'],
        'is_there_date_for_sending' => ['nullable', 'boolean'],
        'date_for_sending_yearly' => ['nullable', 'date'],
        'weekly_day' => ['nullable', 'string'],
        'weekly_time' => ['nullable', 'string'],
        'weekly_time_sm' => ['nullable', 'string'],
        'is_active' => ['nullable', 'boolean'],
        'is_auto_assigned' => ['nullable', 'boolean'],
        'categories' => ['nullable', 'string'],
        'locations' => ['nullable', 'string'],
        'clients' => ['nullable', 'string'],
    ];

    /**
     * الحقول المنطقية التي قد تأتي كنصوص في الملف.
     */
    private const BOOLEAN_TRUE_VALUES = ['1', 'true', 'yes', 'نعم', 'صح'];

    private const BOOLEAN_FALSE_VALUES = ['0', 'false', 'no', 'لا', 'خطأ'];

    /**
     * قراءة ملف وإرجاع جميع الصفوف كمصفوفات.
     *
     * @return array<int, array<string, string>>
     */
    public function readFile(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->readCsv($filePath),
            'xlsx', 'xls' => $this->readXlsx($filePath),
            default => throw new \InvalidArgumentException("صيغة الملف غير مدعومة: {$extension}"),
        };
    }

    /**
     * قراءة ملف CSV.
     *
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $filePath): array
    {
        $options = new CsvOptions;
        $options->FIELD_DELIMITER = ',';

        $reader = new CsvReader($options);
        $reader->open($filePath);

        $rows = [];
        $headers = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $values = $row->toArray();

                // الصف الأول هو العنوان
                if ($rowIndex === 1) {
                    $headers = array_map('trim', $values);

                    continue;
                }

                // تجاهل الصفوف الفارغة
                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $rows[] = $this->mapRowToHeaders($headers, $values);
            }
        }

        $reader->close();

        return $rows;
    }

    /**
     * قراءة ملف Excel (XLSX).
     *
     * @return array<int, array<string, string>>
     */
    private function readXlsx(string $filePath): array
    {
        $options = new XlsxOptions;
        $options->SHOULD_FORMAT_DATES = true;

        $reader = new XlsxReader($options);
        $reader->open($filePath);

        $rows = [];
        $headers = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            // نقرأ فقط أول شيت (الورقة النشطة)
            if (! $sheet->isActive()) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $values = $row->toArray();

                // الصف الأول هو العنوان
                if ($rowIndex === 1) {
                    $headers = array_map('trim', $values);

                    continue;
                }

                // تجاهل الصفوف الفارغة
                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $rows[] = $this->mapRowToHeaders($headers, $values);
            }

            // نقرأ فقط أول شيت
            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * الكشف عن أسماء الأعمدة في الملف (الصف الأول).
     *
     * @return array<int, string>
     */
    public function detectColumns(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->detectCsvColumns($filePath),
            'xlsx', 'xls' => $this->detectXlsxColumns($filePath),
            default => throw new \InvalidArgumentException("صيغة الملف غير مدعومة: {$extension}"),
        };
    }

    /**
     * @return array<int, string>
     */
    private function detectCsvColumns(string $filePath): array
    {
        $options = new CsvOptions;
        $options->FIELD_DELIMITER = ',';

        $reader = new CsvReader($options);
        $reader->open($filePath);

        $headers = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                if ($rowIndex === 1) {
                    $headers = array_map('trim', $row->toArray());
                }

                break;
            }
        }

        $reader->close();

        return $headers;
    }

    /**
     * @return array<int, string>
     */
    private function detectXlsxColumns(string $filePath): array
    {
        $options = new XlsxOptions;

        $reader = new XlsxReader($options);
        $reader->open($filePath);

        $headers = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                if ($rowIndex === 1) {
                    $headers = array_map('trim', $row->toArray());
                }

                break;
            }

            break;
        }

        $reader->close();

        return $headers;
    }

    /**
     * معاينة أول N صف من الملف باستخدام column map.
     *
     * @param  array<string, string>  $columnMap  [حقل في Tag => اسم العمود في الملف]
     * @return array<int, array<string, mixed>>
     */
    public function previewRows(string $filePath, array $columnMap, int $limit = 10): array
    {
        $allRows = $this->readFile($filePath);

        $preview = [];
        $count = 0;

        foreach ($allRows as $row) {
            if ($count >= $limit) {
                break;
            }

            $mapped = $this->applyColumnMap($row, $columnMap);
            $preview[] = $mapped;
            $count++;
        }

        return $preview;
    }

    /**
     * استيراد جميع الصفوف من الملف.
     *
     * @param  array<string, string>  $columnMap  [حقل في Tag => اسم العمود في الملف]
     */
    public function import(string $filePath, array $columnMap, ?int $authUserId = null): ImportResult
    {
        $allRows = $this->readFile($filePath);
        $result = new ImportResult(total: count($allRows));

        foreach ($allRows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 2; // +2 لأن الصف الأول هو العنوان والصفوف تبدأ من 1

            try {
                $mapped = $this->applyColumnMap($row, $columnMap);

                $this->validateRow($mapped, $rowNumber, $result);

                // إذا فشل التحقق، ننتقل للصف التالي
                if (isset($result->errors[$rowNumber])) {
                    continue;
                }

                $this->importSingleRow($mapped, $authUserId, $rowNumber, $result);
            } catch (\Throwable $e) {
                $result->addError($rowNumber, 'خطأ غير متوقع: '.$e->getMessage());
            }
        }

        return $result;
    }

    /**
     * تطبيق column map على صف من البيانات.
     *
     * @param  array<string, string>  $row  بيانات الصف الخام من الملف
     * @param  array<string, string>  $columnMap  [حقل في Tag => اسم العمود في الملف]
     * @return array<string, mixed>
     */
    private function applyColumnMap(array $row, array $columnMap): array
    {
        $mapped = [];

        foreach ($columnMap as $tagField => $fileColumn) {
            $mapped[$tagField] = $row[$fileColumn] ?? '';
        }

        return $mapped;
    }

    /**
     * التحقق من صحة صف واحد.
     */
    private function validateRow(array $mapped, int $rowNumber, ImportResult $result): void
    {
        $errors = [];

        // name إلزامي
        if (empty(trim($mapped['name'] ?? ''))) {
            $errors[] = 'حقل "الاسم" مطلوب';
        }

        // max length
        if (mb_strlen($mapped['name'] ?? '') > 100) {
            $errors[] = 'حقل "الاسم" يجب ألا يتجاوز 100 حرف';
        }

        // importance
        $importance = $mapped['importance'] ?? '';
        if (! empty($importance) && ! in_array($importance, ['veryhigh', 'high', 'medium', 'low'])) {
            $errors[] = 'قيمة "الأهمية" غير صالحة. القيم المسموحة: veryhigh, high, medium, low';
        }

        // repetition
        $repetition = $mapped['repetition'] ?? '';
        if (! empty($repetition) && ! in_array($repetition, ['weekly', 'monthly', 'yearly'])) {
            $errors[] = 'قيمة "نوع التكرار" غير صالحة. القيم المسموحة: weekly, monthly, yearly';
        }

        // boolean fields
        foreach (['is_repetition', 'is_there_date_for_sending', 'is_active', 'is_auto_assigned'] as $boolField) {
            if (isset($mapped[$boolField]) && $mapped[$boolField] !== '' && ! $this->isBooleanValue($mapped[$boolField])) {
                $errors[] = "قيمة حقل \"{$boolField}\" غير صالحة. استخدم: 1, 0, true, false, yes, no";
            }
        }

        // date validation
        $date = $mapped['date_for_sending_yearly'] ?? '';
        if (! empty($date)) {
            try {
                Carbon::parse($date);
            } catch (\Throwable) {
                $errors[] = 'قيمة "التاريخ السنوي" غير صالحة. استخدم صيغة تاريخ صحيحة (مثال: 2026-01-15)';
            }
        }

        // numeric fields
        foreach (['weekly_times', 'monthly_times', 'yearly_times'] as $numericField) {
            $val = $mapped[$numericField] ?? '';
            if (! empty($val) && ! is_numeric($val)) {
                $errors[] = "حقل \"{$numericField}\" يجب أن يكون رقماً";
            }
        }

        if (! empty($errors)) {
            $result->addError($rowNumber, implode(' ; ', $errors));
        }
    }

    /**
     * استيراد صف واحد (إنشاء أو تحديث وسم).
     */
    private function importSingleRow(array $mapped, ?int $authUserId, int $rowNumber, ImportResult $result): void
    {
        $name = trim($mapped['name'] ?? '');

        // البحث عن وسم موجود أو إنشاء جديد
        $tag = Tag::firstOrNew(['name' => $name]);

        // تعيين القيم الأساسية
        $tag->importance = $this->sanitizeValue($mapped['importance'] ?? 'medium');

        // الحقول المنطقية
        $tag->is_repetition = $this->parseBoolean($mapped['is_repetition'] ?? false);
        $tag->repetition = $this->sanitizeValue($mapped['repetition'] ?? null);
        $tag->weekly_times = $this->parseNullableInt($mapped['weekly_times'] ?? null);
        $tag->monthly_times = $this->parseNullableInt($mapped['monthly_times'] ?? null);
        $tag->yearly_times = $this->parseNullableInt($mapped['yearly_times'] ?? null);
        $tag->is_there_date_for_sending = $this->parseBoolean($mapped['is_there_date_for_sending'] ?? false);
        $tag->is_active = $this->parseBoolean($mapped['is_active'] ?? true);
        $tag->is_auto_assigned = $this->parseBoolean($mapped['is_auto_assigned'] ?? true);

        // التاريخ السنوي
        $dateRaw = $mapped['date_for_sending_yearly'] ?? '';
        $tag->date_for_sending_yearly = ! empty($dateRaw) ? Carbon::parse($dateRaw)->format('Y-m-d') : null;

        // أيام الأسبوع
        $weeklyDayRaw = $mapped['weekly_day'] ?? '';
        if (! empty($weeklyDayRaw)) {
            $tag->weekly_day = array_map('trim', explode(',', $weeklyDayRaw));
        } elseif ($tag->exists && $tag->weekly_day) {
            // لا نمسح القيمة القديمة إذا لم تكن موجودة في الاستيراد
        }

        // أوقات الإرسال
        $tag->weekly_time = $this->sanitizeValue($mapped['weekly_time'] ?? null);
        $tag->weekly_time_sm = $this->sanitizeValue($mapped['weekly_time_sm'] ?? null);

        // تتبع المستخدمين
        if ($authUserId) {
            $tag->added_by_user = $tag->added_by_user ?: $authUserId;
            $tag->updated_by_user = $authUserId;
        }

        // ========== معالجة العلاقات ==========

        // Tag Group — استخدام مجموعة افتراضية إن لم يتم تحديد واحدة
        $tagGroupName = $this->sanitizeValue($mapped['tag_group'] ?? null);
        if (! empty($tagGroupName)) {
            $tagGroup = TagGroup::firstOrCreate(['name' => $tagGroupName]);
        } else {
            $tagGroup = TagGroup::firstOrCreate(['name' => 'عام']);
        }
        $tag->tag_group_id = $tagGroup->id;

        // تحليل التصنيفات والمواقع قبل الحفظ لضبط الأعلام
        $categoryNames = $this->parseCommaSeparated($mapped['categories'] ?? null);
        $hasAllCategories = false;
        if ($categoryNames !== null) {
            $normalized = array_map(fn ($n) => mb_strtolower(trim($n)), $categoryNames);
            $hasAllCategories = in_array('all', $normalized, true);
            $tag->assign_all_categories = $hasAllCategories;
        }

        $locationNames = $this->parseCommaSeparated($mapped['locations'] ?? null);
        $hasAllLocations = false;
        if ($locationNames !== null) {
            $normalized = array_map(fn ($n) => mb_strtolower(trim($n)), $locationNames);
            $hasAllLocations = in_array('all', $normalized, true);
            $tag->assign_all_locations = $hasAllLocations;
        }

        $tag->save();

        // ========== العلاقات M:N ==========

        // Categories
        if ($categoryNames !== null) {
            if ($hasAllCategories) {
                // booted() في الموديل تولى المزامنة، فقط نضمن عدم الانفصال
                $tag->categories()->sync(Category::pluck('id')->toArray());
            } else {
                $categoryIds = [];
                foreach ($categoryNames as $catName) {
                    $category = Category::firstOrCreate(['name' => $catName]);
                    $categoryIds[] = $category->id;
                }
                $tag->categories()->sync($categoryIds);
            }
        }

        // Locations
        if ($locationNames !== null) {
            if ($hasAllLocations) {
                $tag->locations()->sync(Location::pluck('id')->toArray());
            } else {
                $locationIds = [];
                foreach ($locationNames as $locName) {
                    $location = Location::firstOrCreate(['name' => $locName]);
                    $locationIds[] = $location->id;
                }
                $tag->locations()->sync($locationIds);
            }
        }

        // Clients — البحث فقط، بدون إنشاء
        $clientCompanies = $this->parseCommaSeparated($mapped['clients'] ?? null);
        if ($clientCompanies !== null) {
            $clientIds = [];
            foreach ($clientCompanies as $company) {
                $client = Client::firstWhere(['company' => $company]);
                if ($client) {
                    $clientIds[] = $client->id;
                } else {
                    $result->addWarning($rowNumber, "العميل \"{$company}\" غير موجود وتم تجاوزه");
                }
            }
            $tag->clients()->sync($clientIds);
        }

        $result->addSuccess();
    }

    /**
     * تعيين قيم الصف إلى رؤوس الأعمدة.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $values
     * @return array<string, string>
     */
    private function mapRowToHeaders(array $headers, array $values): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $row[$header] = (string) ($values[$index] ?? '');
        }

        return $row;
    }

    /**
     * التحقق مما إذا كان الصف فارغاً.
     *
     * @param  array<int, mixed>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return false;
            }
            if (is_numeric($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * تحويل قيمة إلى null إذا كانت فارغة.
     */
    private function sanitizeValue(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * تحليل قيمة منطقية قد تأتي كنص.
     */
    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $strVal = strtolower(trim((string) $value));

        if (in_array($strVal, self::BOOLEAN_TRUE_VALUES)) {
            return true;
        }

        if (in_array($strVal, self::BOOLEAN_FALSE_VALUES)) {
            return false;
        }

        return (bool) $value;
    }

    /**
     * التحقق مما إذا كانت القيمة قيمة منطقية صالحة.
     */
    private function isBooleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        $strVal = strtolower(trim((string) $value));

        return in_array($strVal, [...self::BOOLEAN_TRUE_VALUES, ...self::BOOLEAN_FALSE_VALUES]);
    }

    /**
     * تحليل قيمة إلى integer أو null.
     */
    private function parseNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return (int) $value;
    }

    /**
     * تحليل نص مفصول بفواصل إلى مصفوفة.
     *
     * @return string[]|null
     */
    private function parseCommaSeparated(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $items = array_map('trim', explode(',', $value));
        $items = array_filter($items, fn (string $item) => $item !== '');

        return ! empty($items) ? $items : null;
    }
}
