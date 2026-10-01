<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * خدمة مخصصة لاستيراد التصنيفات من ملفات CSV و Excel.
 *
 * مبنية بنفس نمط LocationImportService وClientImportService.
 */
class CategoryImportService
{
    public const SUPPORTED_FORMATS = ['csv', 'xlsx', 'xls'];

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
     * كشف أعمدة الملف (الصف الأول/الهيدر).
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
     * قراءة صفوف المعاينة مع تعيين الأعمدة.
     *
     * @param  array<string, string>  $columnMap
     * @return array<int, array<string, string>>
     */
    public function previewRows(string $filePath, array $columnMap, int $limit = 10): array
    {
        $allRows = $this->readFile($filePath);
        $preview = array_slice($allRows, 0, $limit);

        $result = [];
        foreach ($preview as $row) {
            $mapped = [];
            foreach ($columnMap as $field => $column) {
                $mapped[$field] = $row[$column] ?? '';
            }
            $result[] = $mapped;
        }

        return $result;
    }

    /**
     * كشف أعمدة ملف CSV.
     *
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
            foreach ($sheet->getRowIterator() as $row) {
                $headers = array_map(fn ($cell) => trim((string) $cell->getValue()), $row->getCells());
                break;
            }
            break;
        }

        $reader->close();

        return array_values(array_filter($headers, fn ($h) => $h !== ''));
    }

    /**
     * كشف أعمدة ملف XLSX/XLS.
     *
     * @return array<int, string>
     */
    private function detectXlsxColumns(string $filePath): array
    {
        $options = new XlsxOptions;
        $reader = new XlsxReader($options);
        $reader->open($filePath);

        $headers = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $headers = array_map(fn ($cell) => trim((string) $cell->getValue()), $row->getCells());
                break;
            }
            break;
        }

        $reader->close();

        return array_values(array_filter($headers, fn ($h) => $h !== ''));
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
                $cells = array_map(fn ($cell) => trim((string) $cell->getValue()), $row->getCells());

                if ($rowIndex === 1) {
                    $headers = $cells;

                    continue;
                }

                if ($this->isEmptyRow($cells)) {
                    continue;
                }

                $rowData = [];
                foreach ($headers as $index => $header) {
                    $rowData[$header] = $cells[$index] ?? '';
                }
                $rows[] = $rowData;
            }
        }

        $reader->close();

        return $rows;
    }

    /**
     * قراءة ملف XLSX/XLS.
     *
     * @return array<int, array<string, string>>
     */
    private function readXlsx(string $filePath): array
    {
        $options = new XlsxOptions;
        $reader = new XlsxReader($options);
        $reader->open($filePath);

        $rows = [];
        $headers = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $cells = array_map(fn ($cell) => trim((string) $cell->getValue()), $row->getCells());

                if ($rowIndex === 1) {
                    $headers = $cells;

                    continue;
                }

                if ($this->isEmptyRow($cells)) {
                    continue;
                }

                $rowData = [];
                foreach ($headers as $index => $header) {
                    $rowData[$header] = $cells[$index] ?? '';
                }
                $rows[] = $rowData;
            }
        }

        $reader->close();

        return $rows;
    }

    /**
     * التحقق مما إذا كان الصف فارغاً بالكامل.
     */
    private function isEmptyRow(array $cells): bool
    {
        return empty(array_filter($cells, fn ($value) => $value !== ''));
    }

    /**
     * استخراج أسماء الأعمدة من ملف.
     *
     * @return array<int, string>
     */
    public function getFileColumns(string $filePath): array
    {
        return $this->detectColumns($filePath);
    }

    /**
     * استيراد دفعة من الصفوف بناءً على تعيين الأعمدة.
     *
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, string>  $columnMap  [model_field => file_column]
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    public function import(array $rows, array $columnMap): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $userId = Auth::id();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 بسبب الهيدر والمؤشر 0-based

            $nameColumn = $columnMap['name'] ?? '';
            $name = trim($row[$nameColumn] ?? '');

            if (empty($name)) {
                $errors[] = "الصف {$rowNumber}: اسم التصنيف مطلوب ولا يمكن أن يكون فارغاً.";
                $skipped++;

                continue;
            }

            try {
                Category::firstOrCreate(
                    ['name' => $name],
                    [
                        'added_by_user' => $userId,
                        'updated_by_user' => $userId,
                    ]
                );
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "الصف {$rowNumber}: خطأ أثناء حفظ التصنيف '{$name}' — {$e->getMessage()}";
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * إنشاء وتحميل قالب Excel للاستيراد.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        $tempPath = storage_path('app/temp/category_import_template.xlsx');

        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $writer = SimpleExcelWriter::create($tempPath);

        $writer->addRow([
            'اسم التصنيف' => 'تصميم الجرافيك',
        ]);

        $writer->addRow([
            'اسم التصنيف' => 'التسويق الرقمي',
        ]);

        $writer->close();

        return response()->download($tempPath, 'نموذج_استيراد_التصنيفات.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * إرجاع حقول الموديل المتاحة للربط.
     *
     * @return array<int, array{field: string, label: string, required: bool, group: string}>
     */
    public function getModelFields(): array
    {
        return [
            [
                'field' => 'name',
                'label' => 'اسم التصنيف',
                'required' => true,
                'group' => 'بيانات التصنيف',
            ],
        ];
    }
}
