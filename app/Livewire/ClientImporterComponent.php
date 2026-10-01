<?php

namespace App\Livewire;

use App\Services\ClientImportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class ClientImporterComponent extends Component
{
    use WithFileUploads;

    /**
     * الخطوة الحالية من معالج الاستيراد.
     */
    public int $step = 1;

    /**
     * الملف المرفوع (مؤقت).
     */
    public $uploadedFile = null;

    /**
     * مسار الملف بعد الحفظ في التخزين.
     */
    public ?string $filePath = null;

    /**
     * اسم الملف الأصلي.
     */
    public ?string $fileName = null;

    /**
     * الأعمدة المكتشفة في الملف.
     *
     * @var array<int, string>
     */
    public array $fileColumns = [];

    /**
     * حقول نموذج العميل المتاحة للتعيين.
     *
     * @var array<int, array{field: string, label: string, required: bool, group: string}>
     */
    public array $modelFields = [];

    /**
     * تعيين الأعمدة: [حقل => اسم العمود في الملف].
     *
     * @var array<string, string>
     */
    public array $columnMap = [];

    /**
     * صفوف المعاينة.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $previewRows = [];

    /**
     * نتيجة الاستيراد.
     */
    public ?array $importResultData = null;

    /**
     * حالة التحميل.
     */
    public bool $isImporting = false;

    /**
     * نسبة التقدم.
     */
    public int $progress = 0;

    /**
     * حالة رفع الملف.
     */
    public bool $isFileUploaded = false;

    /**
     * رسالة الخطأ العامة.
     */
    public ?string $errorMessage = null;

    /**
     * خدمة الاستيراد.
     */
    private ClientImportService $importService;

    public function boot(ClientImportService $importService): void
    {
        $this->importService = $importService;
    }

    public function mount(): void
    {
        $this->modelFields = $this->getModelFields();
    }

    /**
     * قائمة حقول العميل المتاحة للتعيين.
     *
     * @return array<int, array{field: string, label: string, required: bool, group: string}>
     */
    private function getModelFields(): array
    {
        return [
            // ===== بيانات العميل =====
            ['field' => 'company', 'label' => 'اسم الشركة', 'required' => true, 'group' => 'بيانات العميل'],
            ['field' => 'client_name', 'label' => 'اسم المالك / الشخص المتواصل', 'required' => false, 'group' => 'بيانات العميل'],
            ['field' => 'contact_number', 'label' => 'رقم الاتصال', 'required' => false, 'group' => 'بيانات العميل'],
            ['field' => 'address', 'label' => 'العنوان / المحافظة', 'required' => false, 'group' => 'بيانات العميل'],
            ['field' => 'location', 'label' => 'الموقع', 'required' => false, 'group' => 'بيانات العميل'],
            ['field' => 'category', 'label' => 'التصنيف', 'required' => true, 'group' => 'بيانات العميل'],
            ['field' => 'notes', 'label' => 'الملاحظات', 'required' => false, 'group' => 'بيانات العميل'],
            ['field' => 'customer_rating_value', 'label' => 'قيمة التقييم (1-10)', 'required' => false, 'group' => 'بيانات العميل'],
            ['field' => 'is_credit_allowed', 'label' => 'السقف الائتماني مسموح', 'required' => false, 'group' => 'بيانات العميل'],
            ['field' => 'change_cliche_threshold', 'label' => 'حد تغيير الكليشة', 'required' => false, 'group' => 'بيانات العميل'],
            // ===== بيانات الاشتراك =====
            ['field' => 'contract_status', 'label' => 'حالة الاشتراك', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_payment_type', 'label' => 'نوع الدفع', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_billing_cycle', 'label' => 'دورة الفوترة', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_start_date', 'label' => 'تاريخ البدء', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_end_date', 'label' => 'تاريخ الانتهاء', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_weekly_designs_count', 'label' => 'عدد التصاميم الأسبوعية', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_monthly_designs_count', 'label' => 'عدد التصاميم الشهرية', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_total_amount', 'label' => 'المبلغ الإجمالي للاشتراك', 'required' => true, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_currency_id', 'label' => 'العملة', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_marketing_amount', 'label' => 'مبلغ التسويق', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_auto_renewal', 'label' => 'تجديد تلقائي', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_additional_designs_enabled', 'label' => 'تمكين التصاميم الإضافية', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_additional_design_price', 'label' => 'سعر التصميم الإضافي', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_simple_requests_enabled', 'label' => 'تمكين الطلبات البسيطة', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_simple_request_price', 'label' => 'سعر الطلب البسيط', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_grace_period_days', 'label' => 'مهلة السداد (أيام)', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_is_under_lawsuit', 'label' => 'قيد المقاضاة', 'required' => false, 'group' => 'بيانات الاشتراك'],
            ['field' => 'contract_legal_notes', 'label' => 'ملاحظات المقاضاة', 'required' => false, 'group' => 'بيانات الاشتراك'],
        ];
    }

    /**
     * معالجة رفع الملف.
     */
    public function updatedUploadedFile(): void
    {
        $this->validate([
            'uploadedFile' => [
                'required',
                'file',
                'mimes:csv,xlsx,xls',
                'max:10240', // 10MB
            ],
        ]);

        $this->errorMessage = null;
        $this->step = 1;

        try {
            // حفظ الملف في التخزين المؤقت (قرص local صراحةً لضمان الاتساق)
            $this->fileName = $this->uploadedFile->getClientOriginalName();
            $this->filePath = $this->uploadedFile->store('temp-imports', 'local');

            $storagePath = Storage::disk('local')->path($this->filePath);

            // كشف الأعمدة
            $this->fileColumns = $this->importService->detectColumns($storagePath);

            if (empty($this->fileColumns)) {
                $this->errorMessage = 'لم يتم العثور على أعمدة في الملف. تأكد من أن الملف يحتوي على صف عنوان.';

                return;
            }

            // تعيين أوتوماتيكي للأعمدة إذا تطابقت الأسماء
            $this->autoAssignColumns();

            // قراءة صفوف المعاينة
            $this->previewRows = $this->importService->previewRows(
                $storagePath,
                $this->columnMap,
                10
            );

            $this->isFileUploaded = true;
            $this->step = 2;
        } catch (\Throwable $e) {
            $this->errorMessage = 'حدث خطأ أثناء قراءة الملف: '.$e->getMessage();
        }
    }

    /**
     * تعيين الأعمدة تلقائياً بناءً على تطابق الأسماء مع حقول العميل.
     */
    private function autoAssignColumns(): void
    {
        $this->columnMap = [];

        // قاموس المرادفات: اسم العمود في الملف ← حقل
        $synonyms = [
            'company' => ['company', 'اسم الشركة', 'الشركة', 'client company', 'client_company'],
            'client_name' => ['client_name', 'اسم المالك / الشخص المتواصل', 'اسم المالك', 'اسم الشخص المتواصل', 'owner name', 'client name', 'اسم العميل', 'owner_name'],
            'contact_number' => ['contact_number', 'رقم الاتصال', 'الهاتف', 'phone', 'mobile', 'contact', 'contact_no'],
            'address' => ['address', 'العنوان / المحافظة', 'العنوان', 'المحافظة', 'address/province'],
            'location' => ['location', 'الموقع', 'المحافظة/الموقع'],
            'category' => ['category', 'التصنيف', 'تصنيف', 'category name', 'category_name'],
            'notes' => ['notes', 'الملاحظات', 'ملاحظات'],
            'customer_rating_value' => ['customer_rating_value', 'قيمة التقييم (1-10)', 'التقييم', 'قيمة التقييم', 'rating'],
            'is_credit_allowed' => ['is_credit_allowed', 'السقف الائتماني مسموح', 'السقف الائتماني', 'credit allowed', 'credit_allowed'],
            'change_cliche_threshold' => ['change_cliche_threshold', 'حد تغيير الكليشة', 'cliche threshold', 'change_cliche'],
            'contract_status' => ['contract_status', 'حالة الاشتراك', 'contract status'],
            'contract_payment_type' => ['contract_payment_type', 'نوع الدفع', 'payment type', 'payment_type'],
            'contract_billing_cycle' => ['contract_billing_cycle', 'دورة الفوترة', 'billing cycle', 'billing_cycle'],
            'contract_start_date' => ['contract_start_date', 'تاريخ البدء', 'start date', 'start_date'],
            'contract_end_date' => ['contract_end_date', 'تاريخ الانتهاء', 'end date', 'end_date'],
            'contract_weekly_designs_count' => ['contract_weekly_designs_count', 'عدد التصاميم الأسبوعية', 'weekly designs', 'weekly_designs'],
            'contract_monthly_designs_count' => ['contract_monthly_designs_count', 'عدد التصاميم الشهرية', 'monthly designs', 'monthly_designs'],
            'contract_total_amount' => ['contract_total_amount', 'المبلغ الإجمالي للاشتراك', 'المبلغ الإجمالي', 'total amount', 'total_amount', 'contract amount'],
            'contract_currency_id' => ['contract_currency_id', 'العملة', 'currency', 'currency_id', 'currency_name'],
            'contract_marketing_amount' => ['contract_marketing_amount', 'مبلغ التسويق', 'marketing amount', 'marketing_amount'],
            'contract_auto_renewal' => ['contract_auto_renewal', 'تجديد تلقائي', 'auto renewal', 'auto_renewal'],
            'contract_additional_designs_enabled' => ['contract_additional_designs_enabled', 'تمكين التصاميم الإضافية', 'additional designs', 'additional_designs'],
            'contract_additional_design_price' => ['contract_additional_design_price', 'سعر التصميم الإضافي', 'additional design price', 'additional_design_price'],
            'contract_simple_requests_enabled' => ['contract_simple_requests_enabled', 'تمكين الطلبات البسيطة', 'simple requests', 'simple_requests'],
            'contract_simple_request_price' => ['contract_simple_request_price', 'سعر الطلب البسيط', 'simple request price', 'simple_request_price'],
            'contract_grace_period_days' => ['contract_grace_period_days', 'مهلة السداد (أيام)', 'مهلة السداد', 'grace period', 'grace_period_days'],
            'contract_is_under_lawsuit' => ['contract_is_under_lawsuit', 'قيد المقاضاة', 'under lawsuit', 'is_under_lawsuit', 'lawsuit'],
            'contract_legal_notes' => ['contract_legal_notes', 'ملاحظات المقاضاة', 'legal notes', 'legal_notes'],
        ];

        foreach ($this->fileColumns as $column) {
            $trimmed = trim($column);
            $lower = mb_strtolower($trimmed);

            foreach ($synonyms as $field => $aliases) {
                if (in_array($lower, $aliases) || in_array($trimmed, $aliases)) {
                    $this->columnMap[$field] = $trimmed;
                    break;
                }
            }
        }

        // التأكد من أن الحقول الإلزامية معينة
        if (! isset($this->columnMap['company'])) {
            // إذا لم نجد تطابقاً لـ company، نأخذ أول عمود
            $this->columnMap['company'] = $this->fileColumns[0] ?? '';
        }
    }

    /**
     * تحديث column map عند تغيير dropdown.
     */
    public function updateColumnMap(string $field, ?string $column): void
    {
        if ($column && $column !== '') {
            $this->columnMap[$field] = $column;
        } else {
            unset($this->columnMap[$field]);
        }
    }

    /**
     * العودة إلى خطوة رفع الملف.
     */
    public function backToUpload(): void
    {
        $this->step = 1;
        $this->isFileUploaded = false;
        $this->filePath = null;
        $this->fileName = null;
        $this->fileColumns = [];
        $this->columnMap = [];
        $this->previewRows = [];
        $this->importResultData = null;
        $this->errorMessage = null;
        $this->uploadedFile = null;
    }

    /**
     * بدء عملية الاستيراد.
     */
    public function startImport(): void
    {
        if (! $this->filePath) {
            $this->errorMessage = 'لم يتم رفع ملف بعد';

            return;
        }

        // التحقق من وجود الحقول الإلزامية في column map
        $requiredFields = ['company', 'category', 'contract_total_amount'];
        $missingLabels = [];

        foreach ($requiredFields as $field) {
            if (! isset($this->columnMap[$field])) {
                $fieldInfo = collect($this->modelFields)->firstWhere('field', $field);
                $missingLabels[] = $fieldInfo['label'] ?? $field;
            }
        }

        if (! empty($missingLabels)) {
            $this->errorMessage = 'يجب تعيين الحقول الإلزامية التالية لأعمدة من الملف: '.implode('، ', $missingLabels);

            return;
        }

        $this->isImporting = true;
        $this->progress = 0;
        $this->step = 3;
        $this->errorMessage = null;

        try {
            $storagePath = Storage::disk('local')->path($this->filePath);

            $result = $this->importService->import(
                $storagePath,
                $this->columnMap,
                Auth::id()
            );

            $this->importResultData = [
                'total' => $result->total,
                'successful' => $result->successful,
                'failed' => $result->failed,
                'errors' => $result->errors,
                'warnings' => $result->warnings,
            ];

            $this->isImporting = false;
            $this->progress = 100;
            $this->step = 4;
        } catch (\Throwable $e) {
            $this->isImporting = false;
            $this->errorMessage = 'حدث خطأ أثناء الاستيراد: '.$e->getMessage();
        }
    }

    /**
     * إعادة تعيين الاستيراد بالكامل.
     */
    public function resetImport(): void
    {
        $this->step = 1;
        $this->uploadedFile = null;
        $this->filePath = null;
        $this->fileName = null;
        $this->fileColumns = [];
        $this->columnMap = [];
        $this->previewRows = [];
        $this->importResultData = null;
        $this->isImporting = false;
        $this->progress = 0;
        $this->isFileUploaded = false;
        $this->errorMessage = null;
    }

    /**
     * إعادة الاستيراد مع نفس الملف.
     */
    public function reimport(): void
    {
        $this->importResultData = null;
        $this->step = 2;
    }

    public function render()
    {
        return view('livewire.client-importer-component');
    }
}
