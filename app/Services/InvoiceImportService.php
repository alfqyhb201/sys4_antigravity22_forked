<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * خدمة مخصصة لاستيراد الفواتير من ملفات CSV و Excel.
 */
class InvoiceImportService
{
    /**
     * صيغ الملفات المدعومة.
     */
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

                if ($rowIndex === 1) {
                    $headers = array_map('trim', $values);

                    continue;
                }

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
     * قراءة ملف Excel.
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
            if (! $sheet->isActive()) {
                continue;
            }

            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $values = $row->toArray();

                if ($rowIndex === 1) {
                    $headers = array_map('trim', $values);

                    continue;
                }

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $rows[] = $this->mapRowToHeaders($headers, $values);
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * الكشف عن أسماء الأعمدة في الملف.
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
     * @param  array<string, string>  $columnMap  [حقل => اسم العمود في الملف]
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
     * @param  array<string, string>  $columnMap  [حقل => اسم العمود في الملف]
     */
    public function import(string $filePath, array $columnMap, ?int $authUserId = null): ImportResult
    {
        $allRows = $this->readFile($filePath);
        $result = new ImportResult(total: count($allRows));

        try {
            DB::transaction(function () use ($allRows, $columnMap, $authUserId, $result) {
                foreach ($allRows as $rowIndex => $row) {
                    $rowNumber = $rowIndex + 2;

                    try {
                        $mapped = $this->applyColumnMap($row, $columnMap);
                        $mapped = $this->normalizeRow($mapped);

                        $this->validateRow($mapped, $rowNumber, $result);

                        if (isset($result->errors[$rowNumber])) {
                            continue;
                        }

                        $this->importSingleRow($mapped, $authUserId, $rowNumber, $result);
                    } catch (\Throwable $e) {
                        $result->addError($rowNumber, 'خطأ غير متوقع: '.$e->getMessage());
                    }
                }

                if ($result->failed > 0 || ! empty($result->errors)) {
                    throw new \RuntimeException('إلغاء عملية الاستيراد بالكامل بسبب وجود أخطاء في الملف.');
                }
            });
        } catch (\Throwable) {
            $result->successful = 0;
        }

        return $result;
    }

    /**
     * تطبيع القيم لتقبل العربية والإنجليزية.
     */
    private function normalizeRow(array $mapped): array
    {
        if (isset($mapped['status'])) {
            $mapped['status'] = $this->normalizeStatus($mapped['status']);
        }

        return $mapped;
    }

    private function normalizeStatus(mixed $value): string
    {
        $v = mb_strtolower(trim((string) $value));

        return match ($v) {
            'مسودة', 'draft' => 'draft',
            'مستحقة', 'مستحق', 'posted' => 'posted',
            'مدفوعة', 'مدفوع', 'paid' => 'paid',
            'ملغاة', 'ملغى', 'cancelled', 'canceled' => 'cancelled',
            default => empty($v) ? 'posted' : $v,
        };
    }

    private function applyColumnMap(array $row, array $columnMap): array
    {
        $mapped = [];

        foreach ($columnMap as $field => $fileColumn) {
            $mapped[$field] = $row[$fileColumn] ?? '';
        }

        return $mapped;
    }

    /**
     * التحقق من صحة صف واحد من صفوف الفاتورة.
     */
    private function validateRow(array $mapped, int $rowNumber, ImportResult $result): void
    {
        $errors = [];

        // العميل
        $clientSearch = trim($mapped['client'] ?? '');
        if (empty($clientSearch)) {
            $errors[] = 'حقل "العميل" (اسم الشركة أو رقم العميل) مطلوب';
        } else {
            $client = Client::where('company', $clientSearch)
                ->orWhere('id', $clientSearch)
                ->first();
            if (! $client) {
                $errors[] = "العميل \"{$clientSearch}\" غير موجود في النظام";
            }
        }

        // الوصف
        $description = trim($mapped['description'] ?? '');
        if (empty($description)) {
            $errors[] = 'حقل "وصف البند" مطلوب';
        }

        // سعر الوحدة
        $unitAmount = $mapped['unit_amount'] ?? '';
        if ($unitAmount === '' || $unitAmount === null) {
            $errors[] = 'حقل "سعر الوحدة" مطلوب';
        } elseif (! is_numeric($unitAmount) || (float) $unitAmount < 0) {
            $errors[] = 'سعر الوحدة يجب أن يكون رقماً موجباً أو صفراً';
        }

        // الكمية
        $quantity = $mapped['quantity'] ?? '';
        if ($quantity !== '' && $quantity !== null && (! is_numeric($quantity) || (float) $quantity <= 0)) {
            $errors[] = 'الكمية يجب أن تكون رقماً أكبر من صفر';
        }

        // العملة
        $currencySearch = trim($mapped['currency'] ?? '');
        if (! empty($currencySearch)) {
            $currency = Currency::where('currency_name', $currencySearch)
                ->orWhere('currency', $currencySearch)
                ->orWhere('symbol', $currencySearch)
                ->orWhere('id', $currencySearch)
                ->first();
            if (! $currency) {
                $errors[] = "العملة \"{$currencySearch}\" غير موجودة في النظام";
            }
        }

        // الاشتراك (إن وجد)
        $contractId = trim($mapped['contract'] ?? '');
        if (! empty($contractId) && ! Contract::where('id', $contractId)->exists()) {
            $errors[] = "الاشتراك رقم \"{$contractId}\" غير موجود في النظام";
        }

        // الحالة
        $status = trim($mapped['status'] ?? '');
        if (! empty($status) && ! in_array($status, ['draft', 'posted', 'paid', 'cancelled'])) {
            $errors[] = 'قيمة "الحالة" غير صالحة. القيم المسموحة: draft, posted, paid, cancelled';
        }

        // التواريخ
        foreach (['issue_date', 'due_date'] as $dateField) {
            $date = $mapped[$dateField] ?? '';
            if (! empty($date)) {
                try {
                    Carbon::parse($date);
                } catch (\Throwable) {
                    $errors[] = "قيمة \"{$dateField}\" غير صالحة. استخدم صيغة تاريخ صحيحة (مثال: 2026-01-15)";
                }
            }
        }

        if (! empty($errors)) {
            $result->addError($rowNumber, implode(' ; ', $errors));
        }
    }

    /**
     * استيراد صف واحد لإنشاء الفاتورة وبندها.
     */
    private function importSingleRow(array $mapped, ?int $authUserId, int $rowNumber, ImportResult $result): void
    {
        DB::transaction(function () use ($mapped, $authUserId, $result) {
            $clientSearch = trim($mapped['client'] ?? '');
            $client = Client::where('company', $clientSearch)
                ->orWhere('id', $clientSearch)
                ->firstOrFail();

            $currencySearch = trim($mapped['currency'] ?? '');
            $currencyId = null;
            if (! empty($currencySearch)) {
                $currency = Currency::where('currency_name', $currencySearch)
                    ->orWhere('currency', $currencySearch)
                    ->orWhere('symbol', $currencySearch)
                    ->orWhere('id', $currencySearch)
                    ->first();
                $currencyId = $currency?->id;
            }

            $contractId = $this->sanitizeValue($mapped['contract'] ?? null);

            $issueDate = ! empty($mapped['issue_date']) ? Carbon::parse($mapped['issue_date']) : now();
            // تاريخ الاستحقاق الافتراضي: في يوم الاستيراد/الإصدار نفسه بناءً على طلب المستخدم
            $dueDate = ! empty($mapped['due_date']) ? Carbon::parse($mapped['due_date']) : $issueDate->copy();

            $status = $this->sanitizeValue($mapped['status'] ?? 'posted') ?: 'posted';
            $notes = $this->sanitizeValue($mapped['notes'] ?? null);

            $quantity = (float) ($this->sanitizeValue($mapped['quantity'] ?? '1') ?: 1);
            $unitAmount = (float) ($mapped['unit_amount'] ?? 0);
            $itemTotal = round($quantity * $unitAmount, 2);

            $invoice = Invoice::create([
                'client_id' => $client->id,
                'contract_id' => $contractId,
                'currency_id' => $currencyId,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'total_amount' => $itemTotal,
                'status' => $status,
                'notes' => $notes,
                'created_by_user' => $authUserId,
                'updated_by_user' => $authUserId,
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => trim($mapped['description'] ?? ''),
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'total' => $itemTotal,
            ]);

            $result->addSuccess();
        });
    }

    /**
     * توليد وتحميل نموذج Excel جاهز للتعبئة.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'invoices_template').'.xlsx';

        $writer = SimpleExcelWriter::create($path);
        $writer->addHeader($this->getTemplateHeaders());
        $writer->addRow($this->getTemplateExampleRow());
        $writer->close();

        return response()->download($path, 'invoices_import_template.xlsx')
            ->deleteFileAfterSend();
    }

    /**
     * عناوين أعمدة نموذج الاستيراد.
     *
     * @return array<int, string>
     */
    public function getTemplateHeaders(): array
    {
        return [
            'العميل',
            'وصف البند',
            'الكمية',
            'سعر الوحدة',
            'العملة',
            'الاشتراك',
            'تاريخ الإصدار',
            'تاريخ الاستحقاق',
            'الحالة',
            'الملاحظات',
        ];
    }

    /**
     * مثال لصف بيانات في نموذج الاستيراد.
     *
     * @return array<int, string>
     */
    public function getTemplateExampleRow(): array
    {
        return [
            'شركة الأمل للتجارة',
            'تصميم منشورات شبكات التواصل الاجتماعي',
            '1',
            '15000',
            'ريال يمني',
            '',
            now()->format('Y-m-d'),
            now()->format('Y-m-d'),
            'مستحقة',
            'فاتورة استيراد شهرية',
        ];
    }

    private function mapRowToHeaders(array $headers, array $values): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $row[$header] = (string) ($values[$index] ?? '');
        }

        return $row;
    }

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

    private function sanitizeValue(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        return trim((string) $value);
    }
}
