<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Receipt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * خدمة مخصصة لاستيراد سندات القبض من ملفات CSV و Excel.
 */
class ReceiptImportService
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
     * تطبيع طريقة الدفع.
     */
    private function normalizeRow(array $mapped): array
    {
        if (isset($mapped['payment_method'])) {
            $mapped['payment_method'] = $this->normalizePaymentMethod($mapped['payment_method']);
        }

        return $mapped;
    }

    private function normalizePaymentMethod(mixed $value): string
    {
        $v = mb_strtolower(trim((string) $value));

        return match ($v) {
            'نقداً', 'نقدا', 'نقد', 'cash' => 'cash',
            'تحويل بنكي', 'تحويل', 'transfer', 'bank_transfer' => 'transfer',
            'شيك', 'check', 'cheque' => 'check',
            'جيب', 'jeeb' => 'jeeb',
            'الكريمي', 'كريمي', 'kuraimi' => 'kuraimi',
            'الشبكة الموحدة', 'شبكة موحدة', 'unified_network' => 'unified_network',
            'جوالى', 'جوالي', 'jawali' => 'jawali',
            'محفظة العميل', 'محفظة', 'wallet' => 'wallet',
            'خصم', 'discount' => 'discount',
            default => empty($v) ? 'cash' : $v,
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
     * التحقق من صحة صف واحد من صفوف سندات القبض.
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

        // المبلغ
        $amount = $mapped['amount'] ?? '';
        if ($amount === '' || $amount === null) {
            $errors[] = 'حقل "المبلغ" مطلوب';
        } elseif (! is_numeric($amount) || (float) $amount <= 0) {
            $errors[] = 'المبلغ يجب أن يكون رقماً أكبر من صفر';
        }

        // الفاتورة (إن وجدت)
        $invoiceSearch = trim($mapped['invoice'] ?? '');
        if (! empty($invoiceSearch)) {
            $invoice = Invoice::where('invoice_number', $invoiceSearch)
                ->orWhere('id', $invoiceSearch)
                ->first();
            if (! $invoice) {
                $errors[] = "الفاتورة \"{$invoiceSearch}\" غير موجودة في النظام";
            }
        }

        // عملة الدفع (إن وجدت)
        $currencySearch = trim($mapped['paid_currency'] ?? '');
        if (! empty($currencySearch)) {
            $currency = Currency::where('currency_name', $currencySearch)
                ->orWhere('currency', $currencySearch)
                ->orWhere('symbol', $currencySearch)
                ->orWhere('id', $currencySearch)
                ->first();
            if (! $currency) {
                $errors[] = "عملة الدفع \"{$currencySearch}\" غير موجودة في النظام";
            }
        }

        // طريقة الدفع
        $paymentMethod = trim($mapped['payment_method'] ?? '');
        $allowedMethods = ['cash', 'transfer', 'check', 'jeeb', 'kuraimi', 'unified_network', 'jawali', 'wallet', 'discount'];
        if (! empty($paymentMethod) && ! in_array($paymentMethod, $allowedMethods)) {
            $errors[] = 'قيمة "طريقة الدفع" غير صالحة';
        }

        // تاريخ السند
        $receiptDate = $mapped['receipt_date'] ?? '';
        if (! empty($receiptDate)) {
            try {
                Carbon::parse($receiptDate);
            } catch (\Throwable) {
                $errors[] = 'قيمة "تاريخ السند" غير صالحة. استخدم صيغة تاريخ صحيحة (مثال: 2026-01-15)';
            }
        }

        if (! empty($errors)) {
            $result->addError($rowNumber, implode(' ; ', $errors));
        }
    }

    /**
     * استيراد صف واحد لإنشاء سند القبض.
     */
    private function importSingleRow(array $mapped, ?int $authUserId, int $rowNumber, ImportResult $result): void
    {
        DB::transaction(function () use ($mapped, $authUserId, $result) {
            $clientSearch = trim($mapped['client'] ?? '');
            $client = Client::where('company', $clientSearch)
                ->orWhere('id', $clientSearch)
                ->firstOrFail();

            $invoiceId = null;
            $invoiceSearch = trim($mapped['invoice'] ?? '');
            if (! empty($invoiceSearch)) {
                $invoice = Invoice::where('invoice_number', $invoiceSearch)
                    ->orWhere('id', $invoiceSearch)
                    ->first();
                $invoiceId = $invoice?->id;
            }

            $paidCurrencyId = null;
            $currencySearch = trim($mapped['paid_currency'] ?? '');
            if (! empty($currencySearch)) {
                $currency = Currency::where('currency_name', $currencySearch)
                    ->orWhere('currency', $currencySearch)
                    ->orWhere('symbol', $currencySearch)
                    ->orWhere('id', $currencySearch)
                    ->first();
                $paidCurrencyId = $currency?->id;
            }

            $amount = (float) ($mapped['amount'] ?? 0);
            $paymentMethod = $this->sanitizeValue($mapped['payment_method'] ?? 'cash') ?: 'cash';
            $receiptDate = ! empty($mapped['receipt_date']) ? Carbon::parse($mapped['receipt_date']) : now();
            $referenceNumber = $this->sanitizeValue($mapped['reference_number'] ?? null);
            $notes = $this->sanitizeValue($mapped['notes'] ?? null);

            Receipt::create([
                'client_id' => $client->id,
                'invoice_id' => $invoiceId,
                'paid_currency_id' => $paidCurrencyId,
                'amount' => $amount,
                'original_amount' => $amount,
                'receipt_date' => $receiptDate,
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'created_by_user' => $authUserId,
                'updated_by_user' => $authUserId,
            ]);

            $result->addSuccess();
        });
    }

    /**
     * توليد وتحميل نموذج Excel جاهز للتعبئة.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'receipts_template').'.xlsx';

        $writer = SimpleExcelWriter::create($path);
        $writer->addHeader($this->getTemplateHeaders());
        $writer->addRow($this->getTemplateExampleRow());
        $writer->close();

        return response()->download($path, 'receipts_import_template.xlsx')
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
            'المبلغ',
            'الفاتورة',
            'عملة الدفع',
            'طريقة الدفع',
            'تاريخ السند',
            'رقم المرجع',
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
            '15000',
            '',
            'ريال يمني',
            'نقداً',
            now()->format('Y-m-d'),
            'REF-10023',
            'دفعة من رصيد العميل',
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
