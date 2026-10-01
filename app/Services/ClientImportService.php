<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * خدمة مخصصة لاستيراد العملاء مع بيانات عقودهم من ملفات CSV و Excel.
 *
 * مبنية بنفس نمط TagImportService وتعتمد على OpenSpout لقراءة الملفات،
 * مع إنشاء العميل ثم اشتراكه ثم الفاتورة الأولى (بنفس منطق CreateClient).
 */
class ClientImportService
{
    /**
     * صيغ الملفات المدعومة.
     */
    public const SUPPORTED_FORMATS = ['csv', 'xlsx', 'xls'];

    /**
     * قواعد التحقق من صحة حقول العميل والاشتراك.
     */
    public const VALIDATION_RULES = [
        // ===== العميل =====
        'company' => ['required', 'string', 'max:255'],
        'client_name' => ['nullable', 'string', 'max:255'],
        'contact_number' => ['nullable', 'regex:/^[0-9]{9}$/'],
        'address' => ['nullable', 'string'],
        'location' => ['nullable', 'string', 'max:255'],
        'category' => ['required', 'string'],
        'notes' => ['nullable', 'string'],
        'customer_rating_value' => ['nullable', 'integer', 'between:1,10'],
        'is_credit_allowed' => ['nullable', 'boolean'],
        'change_cliche_threshold' => ['nullable', 'integer', 'min:0'],
        // ===== الاشتراك =====
        'contract_status' => ['nullable', 'in:active,suspended'],
        'contract_payment_type' => ['nullable', 'in:advance,deferred'],
        'contract_billing_cycle' => ['nullable', 'in:weekly,monthly,yearly'],
        'contract_start_date' => ['nullable', 'date'],
        'contract_end_date' => ['nullable', 'date'],
        'contract_weekly_designs_count' => ['nullable', 'integer', 'min:0'],
        'contract_monthly_designs_count' => ['nullable', 'integer', 'min:0'],
        'contract_total_amount' => ['required', 'numeric', 'min:0'],
        'contract_currency_id' => ['nullable', 'string'],
        'contract_marketing_amount' => ['nullable', 'numeric', 'min:0'],
        'contract_auto_renewal' => ['nullable', 'boolean'],
        'contract_additional_designs_enabled' => ['nullable', 'boolean'],
        'contract_additional_design_price' => ['nullable', 'numeric', 'min:0'],
        'contract_simple_requests_enabled' => ['nullable', 'boolean'],
        'contract_simple_request_price' => ['nullable', 'numeric', 'min:0'],
        'contract_grace_period_days' => ['nullable', 'integer', 'min:0'],
        'contract_is_under_lawsuit' => ['nullable', 'boolean'],
        'contract_legal_notes' => ['nullable', 'string'],
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
     * قراءة ملف Excel (XLSX/XLS).
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
                    $rowNumber = $rowIndex + 2; // +2 لأن الصف الأول هو العنوان والصفوف تبدأ من 1

                    try {
                        $mapped = $this->applyColumnMap($row, $columnMap);
                        $mapped = $this->normalizeRow($mapped);

                        // تخطي المكرر (نفس اسم الشركة) قبل التحقق — مع تنبيه
                        $company = trim($mapped['company'] ?? '');
                        if (! empty($company) && Client::where('company', $company)->exists()) {
                            $result->addWarning($rowNumber, "عميل باسم \"{$company}\" موجود مسبقاً — تم التخطي");

                            continue;
                        }

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

                // إذا وُجد أي خطأ أو تحذير (مثل عميل مكرر أو فشل تحقق) في الملف، يلغى الاستيراد بالكامل
                if ($result->failed > 0 || ! empty($result->errors) || ! empty($result->warnings)) {
                    throw new \RuntimeException('إلغاء عملية الاستيراد بالكامل بسبب وجود أخطاء أو عملاء مكررين في الملف.');
                }
            });
        } catch (\Throwable) {
            $result->successful = 0;
        }

        return $result;
    }

    /**
     * تطبيع القيم (نوع الدفع، دورة الفوترة، حالة الاشتراك) لتقبل العربية والإنجليزية.
     */
    private function normalizeRow(array $mapped): array
    {
        if (isset($mapped['contract_payment_type'])) {
            $mapped['contract_payment_type'] = $this->normalizePaymentType($mapped['contract_payment_type']);
        }

        if (isset($mapped['contract_billing_cycle'])) {
            $mapped['contract_billing_cycle'] = $this->normalizeBillingCycle($mapped['contract_billing_cycle']);
        }

        if (isset($mapped['contract_status'])) {
            $mapped['contract_status'] = $this->normalizeContractStatus($mapped['contract_status']);
        }

        return $mapped;
    }

    private function normalizePaymentType(mixed $value): string
    {
        $v = mb_strtolower(trim((string) $value));

        return match ($v) {
            'مقدم', 'مقدّم', 'cash', 'prepaid' => 'advance',
            'مؤخر', 'مؤجّل', 'deferred', 'postpaid' => 'deferred',
            default => $v,
        };
    }

    private function normalizeBillingCycle(mixed $value): string
    {
        $v = mb_strtolower(trim((string) $value));

        return match ($v) {
            'أسبوعي', 'اسبوعي', 'weekly' => 'weekly',
            'سنوي', 'سنة', 'yearly', 'annual' => 'yearly',
            default => 'monthly',
        };
    }

    private function normalizeContractStatus(mixed $value): string
    {
        $v = mb_strtolower(trim((string) $value));

        return match ($v) {
            'موقف', 'موقف يدوياً', 'موقوف', 'suspended' => 'suspended',
            default => 'active',
        };
    }

    /**
     * تطبيق column map على صف من البيانات.
     *
     * @param  array<string, string>  $row  بيانات الصف الخام من الملف
     * @param  array<string, string>  $columnMap  [حقل => اسم العمود في الملف]
     * @return array<string, mixed>
     */
    private function applyColumnMap(array $row, array $columnMap): array
    {
        $mapped = [];

        foreach ($columnMap as $field => $fileColumn) {
            $mapped[$field] = $row[$fileColumn] ?? '';
        }

        return $mapped;
    }

    /**
     * التحقق من صحة صف واحد (العميل + الاشتراك).
     */
    private function validateRow(array $mapped, int $rowNumber, ImportResult $result): void
    {
        $errors = [];

        // ===== العميل =====
        $company = trim($mapped['company'] ?? '');
        if (empty($company)) {
            $errors[] = 'حقل "اسم الشركة" مطلوب';
        } elseif (mb_strlen($company) > 255) {
            $errors[] = 'حقل "اسم الشركة" يجب ألا يتجاوز 255 حرفاً';
        }

        $category = trim($mapped['category'] ?? '');
        if (empty($category)) {
            $errors[] = 'حقل "التصنيف" مطلوب';
        } elseif (! Category::where('name', $category)->exists()) {
            $errors[] = "التصنيف \"{$category}\" غير موجود في النظام";
        }

        $contactNumber = trim($mapped['contact_number'] ?? '');
        if (! empty($contactNumber) && ! preg_match('/^[0-9]{9}$/', $contactNumber)) {
            $errors[] = 'رقم الاتصال يجب أن يتكون من 9 أرقام بالضبط (بدون رمز الدولة)';
        }

        $rating = $mapped['customer_rating_value'] ?? '';
        if (! empty($rating) && (! is_numeric($rating) || (int) $rating < 1 || (int) $rating > 10)) {
            $errors[] = 'قيمة التقييم يجب أن تكون رقماً بين 1 و 10';
        }

        $cliche = $mapped['change_cliche_threshold'] ?? '';
        if (! empty($cliche) && (! is_numeric($cliche) || (int) $cliche < 0)) {
            $errors[] = 'حد تغيير الكليشة يجب أن يكون رقماً موجباً';
        }

        if (isset($mapped['is_credit_allowed']) && $mapped['is_credit_allowed'] !== '' && ! $this->isBooleanValue($mapped['is_credit_allowed'])) {
            $errors[] = 'قيمة حقل "السقف الائتماني" غير صالحة. استخدم: 1, 0, true, false, yes, no';
        }

        // ===== الاشتراك =====
        $totalAmount = $mapped['contract_total_amount'] ?? '';
        if (empty($totalAmount)) {
            $errors[] = 'حقل "المبلغ الإجمالي للاشتراك" مطلوب — الاشتراك إلزامي لكل عميل جديد';
        } elseif (! is_numeric($totalAmount) || (float) $totalAmount < 0) {
            $errors[] = 'المبلغ الإجمالي للاشتراك يجب أن يكون رقماً موجباً';
        }

        $paymentType = trim($mapped['contract_payment_type'] ?? '');
        if (! empty($paymentType) && ! in_array($paymentType, ['advance', 'deferred'])) {
            $errors[] = 'قيمة "نوع الدفع" غير صالحة. القيم المسموحة: advance, deferred';
        }

        $billingCycle = trim($mapped['contract_billing_cycle'] ?? '');
        if (! empty($billingCycle) && ! in_array($billingCycle, ['weekly', 'monthly', 'yearly'])) {
            $errors[] = 'قيمة "دورة الفوترة" غير صالحة. القيم المسموحة: weekly, monthly, yearly';
        }

        $contractStatus = trim($mapped['contract_status'] ?? '');
        if (! empty($contractStatus) && ! in_array($contractStatus, ['active', 'suspended'])) {
            $errors[] = 'قيمة "حالة الاشتراك" غير صالحة. القيم المسموحة: active, suspended';
        }

        foreach (['contract_start_date', 'contract_end_date'] as $dateField) {
            $date = $mapped[$dateField] ?? '';
            if (! empty($date)) {
                try {
                    Carbon::parse($date);
                } catch (\Throwable) {
                    $errors[] = "قيمة \"{$dateField}\" غير صالحة. استخدم صيغة تاريخ صحيحة (مثال: 2026-01-15)";
                }
            }
        }

        foreach (['contract_weekly_designs_count', 'contract_monthly_designs_count', 'contract_grace_period_days'] as $intField) {
            $val = $mapped[$intField] ?? '';
            if (! empty($val) && ! is_numeric($val)) {
                $errors[] = "حقل \"{$intField}\" يجب أن يكون رقماً";
            }
        }

        foreach (['contract_marketing_amount', 'contract_additional_design_price', 'contract_simple_request_price'] as $decimalField) {
            $val = $mapped[$decimalField] ?? '';
            if (! empty($val) && ! is_numeric($val)) {
                $errors[] = "حقل \"{$decimalField}\" يجب أن يكون رقماً";
            }
        }

        foreach (['contract_auto_renewal', 'contract_additional_designs_enabled', 'contract_simple_requests_enabled', 'contract_is_under_lawsuit'] as $boolField) {
            if (isset($mapped[$boolField]) && $mapped[$boolField] !== '' && ! $this->isBooleanValue($mapped[$boolField])) {
                $errors[] = "قيمة حقل \"{$boolField}\" غير صالحة. استخدم: 1, 0, true, false, yes, no";
            }
        }

        if (! empty($errors)) {
            $result->addError($rowNumber, implode(' ; ', $errors));
        }
    }

    /**
     * استيراد صف واحد: إنشاء العميل ثم الاشتراك ثم الفاتورة الأولى.
     */
    private function importSingleRow(array $mapped, ?int $authUserId, int $rowNumber, ImportResult $result): void
    {
        DB::transaction(function () use ($mapped, $authUserId, $rowNumber, $result) {
            // التصنيف (تم التحقق من وجوده في validateRow)
            $category = Category::where('name', trim($mapped['category'] ?? ''))->first();

            if (! $category) {
                $result->addError($rowNumber, 'التصنيف غير موجود');

                return;
            }

            $client = new Client;
            $client->company = trim($mapped['company'] ?? '');
            $client->client_name = $this->sanitizeValue($mapped['client_name'] ?? null);
            $client->contact_number = $this->sanitizeValue($mapped['contact_number'] ?? null);
            $client->address = $this->sanitizeValue($mapped['address'] ?? null);
            $client->notes = $this->sanitizeValue($mapped['notes'] ?? null);
            $client->customer_rating_value = $this->parseNullableInt($mapped['customer_rating_value'] ?? null);
            $client->is_credit_allowed = $this->parseBoolean($mapped['is_credit_allowed'] ?? false);
            $client->change_cliche_threshold = $this->parseNullableInt($mapped['change_cliche_threshold'] ?? null);
            $client->category_id = $category->id;

            $locationName = $this->sanitizeValue($mapped['location'] ?? null);
            if (! empty($locationName)) {
                $location = Location::firstOrCreate(['name' => $locationName]);
                $client->location_id = $location->id;
            }

            if ($authUserId) {
                $client->added_by_user = $authUserId;
                $client->updated_by_user = $authUserId;
            }

            $client->save();

            // ===== إنشاء الاشتراك =====
            $this->createContractForClient($client, $mapped, $authUserId);

            $result->addSuccess();
        });
    }

    /**
     * إنشاء الاشتراك للعميل الجديد (بنفس Mapping في CreateClient::afterCreate).
     */
    private function createContractForClient(Client $client, array $mapped, ?int $authUserId): void
    {
        $billingCycle = $this->sanitizeValue($mapped['contract_billing_cycle'] ?? 'monthly') ?: 'monthly';
        $paymentType = $this->sanitizeValue($mapped['contract_payment_type'] ?? 'advance') ?: 'advance';
        $status = $this->sanitizeValue($mapped['contract_status'] ?? 'active') ?: 'active';

        $startDate = $this->parseDate($mapped['contract_start_date'] ?? null) ?? now();
        $endDate = $this->parseDate($mapped['contract_end_date'] ?? null)
            ?? Contract::calculateEndDate($startDate, $billingCycle);

        $weeklyDesigns = $this->parseNullableInt($mapped['contract_weekly_designs_count'] ?? 1) ?? 1;
        $monthlyDesigns = $this->parseNullableInt($mapped['contract_monthly_designs_count'] ?? null)
            ?? Contract::calculateMonthlyDesignsCount($weeklyDesigns);

        $isCreditAllowed = $this->parseBoolean($mapped['is_credit_allowed'] ?? false);

        $contract = $client->currentContract()->create([
            'status' => $status === 'suspended' ? 'suspended' : 'active',
            'payment_type' => $paymentType,
            'billing_cycle' => $billingCycle,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'weekly_designs_count' => $weeklyDesigns,
            'monthly_designs_count' => $monthlyDesigns,
            'grace_period_days' => $isCreditAllowed ? null : ($this->parseNullableInt($mapped['contract_grace_period_days'] ?? 0) ?? 0),
            'total_amount' => (float) ($mapped['contract_total_amount'] ?? 0),
            'currency_id' => $this->resolveCurrencyId($mapped['contract_currency_id'] ?? null),
            'marketing_amount' => (float) ($mapped['contract_marketing_amount'] ?? 0),
            'auto_renewal' => $this->parseBoolean($mapped['contract_auto_renewal'] ?? false),
            'additional_designs_enabled' => $this->parseBoolean($mapped['contract_additional_designs_enabled'] ?? false),
            'additional_design_price' => $this->parseNullableFloat($mapped['contract_additional_design_price'] ?? null) ?: null,
            'additional_designs_count' => 0,
            'simple_requests_enabled' => $this->parseBoolean($mapped['contract_simple_requests_enabled'] ?? false),
            'simple_request_price' => $this->parseNullableFloat($mapped['contract_simple_request_price'] ?? null) ?: null,
            'simple_requests_count' => 0,
            'is_under_lawsuit' => $this->parseBoolean($mapped['contract_is_under_lawsuit'] ?? false),
            'legal_notes' => $this->sanitizeValue($mapped['contract_legal_notes'] ?? null),
            'created_by_user' => $authUserId,
            'updated_by_user' => $authUserId,
        ]);

        $this->createInvoiceForContract($client, $contract, $mapped, $authUserId);
    }

    /**
     * إنشاء الفاتورة الأولى للاشتراك (بنفس منطق createInvoiceForContract في CreateClient).
     */
    private function createInvoiceForContract(Client $client, Contract $contract, array $mapped, ?int $authUserId): void
    {
        $totalAmount = (float) ($mapped['contract_total_amount'] ?? 0);
        $marketingAmount = (float) ($mapped['contract_marketing_amount'] ?? 0);
        $paymentType = $contract->payment_type;

        $issueDate = now();
        $dueDate = $paymentType === 'advance'
            ? $contract->start_date
            : Carbon::parse($contract->end_date);

        $invoice = Invoice::create([
            'client_id' => $client->id,
            'contract_id' => $contract->id,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'total_amount' => $totalAmount + $marketingAmount,
            'status' => $paymentType === 'deferred' ? 'draft' : 'posted',
            'notes' => 'فاتورة أولى للاشتراك',
            'created_by_user' => $authUserId,
            'updated_by_user' => $authUserId,
        ]);

        $items = [];

        if ($totalAmount > 0) {
            $items[] = new InvoiceItem([
                'description' => 'خدمات التصميم - '.number_format($contract->monthly_designs_count).' تصاميم شهرياً',
                'quantity' => 1,
                'unit_amount' => $totalAmount,
                'total' => $totalAmount,
            ]);
        }

        if ($marketingAmount > 0) {
            $items[] = new InvoiceItem([
                'description' => 'خدمات التسويق',
                'quantity' => 1,
                'unit_amount' => $marketingAmount,
                'total' => $marketingAmount,
            ]);
        }

        if (! empty($items)) {
            $invoice->items()->saveMany($items);
        }
    }

    /**
     * توليد وتحميل نموذج Excel جاهز للتعبئة.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'clients_template').'.xlsx';

        $writer = SimpleExcelWriter::create($path);
        $writer->addHeader($this->getTemplateHeaders());
        $writer->addRow($this->getTemplateExampleRow());
        $writer->close();

        return response()->download($path, 'clients_import_template.xlsx')
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
            'اسم الشركة',
            'اسم المالك / الشخص المتواصل',
            'رقم الاتصال',
            'العنوان / المحافظة',
            'الموقع',
            'التصنيف',
            'الملاحظات',
            'قيمة التقييم (1-10)',
            'السقف الائتماني مسموح',
            'حد تغيير الكليشة',
            'حالة الاشتراك',
            'نوع الدفع',
            'دورة الفوترة',
            'تاريخ البدء',
            'تاريخ الانتهاء',
            'عدد التصاميم الأسبوعية',
            'عدد التصاميم الشهرية',
            'المبلغ الإجمالي للاشتراك',
            'العملة',
            'مبلغ التسويق',
            'تجديد تلقائي',
            'تمكين التصاميم الإضافية',
            'سعر التصميم الإضافي',
            'تمكين الطلبات البسيطة',
            'سعر الطلب البسيط',
            'مهلة السداد (أيام)',
            'قيد المقاضاة',
            'ملاحظات المقاضاة',
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
            'أحمد محمد',
            '777123456',
            'صنعاء',
            'صنعاء',
            'تصنيف أساسي',
            '',
            '8',
            'لا',
            '20',
            'نشط',
            'مقدم',
            'شهري',
            now()->format('Y-m-d'),
            now()->addMonth()->format('Y-m-d'),
            '1',
            '4',
            '5000',
            'ريال يمني',
            '0',
            'لا',
            'لا',
            '0',
            'لا',
            '0',
            '3',
            'لا',
            '',
        ];
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

        $clean = trim((string) $value);

        return $clean === '' ? null : (int) $clean;
    }

    /**
     * تحليل قيمة إلى float أو null.
     */
    private function parseNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        $clean = trim((string) $value);

        return $clean === '' ? null : (float) $clean;
    }

    /**
     * تحليل قيمة إلى تاريخ أو null.
     */
    private function parseDate(mixed $value): ?Carbon
    {
        $clean = $this->sanitizeValue($value);

        if (empty($clean)) {
            return null;
        }

        try {
            return Carbon::parse($clean);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * حل العملة من الاسم وإلا العملة الافتراضية الأولى.
     */
    private function resolveCurrencyId(mixed $value): ?int
    {
        $name = $this->sanitizeValue($value);

        if (! empty($name)) {
            $currency = Currency::where('currency_name', $name)
                ->orWhere('currency', $name)
                ->first();

            if ($currency) {
                return $currency->id;
            }
        }

        return Currency::first()?->id;
    }

    /**
     * تحليل نص مفصول بفواصل إلى مصفوفة.
     *
     * @return string[]|null
     */
    private function parseCommaSeparated(mixed $value): ?array
    {
        $clean = $this->sanitizeValue($value);

        if ($clean === null || $clean === '') {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $clean)), fn ($item) => $item !== ''));
    }
}
