<?php

namespace App\Livewire;

use App\Services\InvoiceImportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class InvoiceImporterComponent extends Component
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
     * حقول نموذج الفاتورة المتاحة للتعيين.
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
    private InvoiceImportService $importService;

    public function boot(InvoiceImportService $importService): void
    {
        $this->importService = $importService;
    }

    public function mount(): void
    {
        $this->modelFields = $this->getModelFields();
    }

    /**
     * قائمة حقول الفاتورة المتاحة للتعيين.
     *
     * @return array<int, array{field: string, label: string, required: bool, group: string}>
     */
    private function getModelFields(): array
    {
        return [
            ['field' => 'client', 'label' => 'العميل (شركة/رقم)', 'required' => true, 'group' => 'بيانات الفاتورة الأساسية'],
            ['field' => 'contract', 'label' => 'رقم الاشتراك', 'required' => false, 'group' => 'بيانات الفاتورة الأساسية'],
            ['field' => 'currency', 'label' => 'العملة', 'required' => false, 'group' => 'بيانات الفاتورة الأساسية'],
            ['field' => 'issue_date', 'label' => 'تاريخ الإصدار', 'required' => false, 'group' => 'بيانات الفاتورة الأساسية'],
            ['field' => 'due_date', 'label' => 'تاريخ الاستحقاق', 'required' => false, 'group' => 'بيانات الفاتورة الأساسية'],
            ['field' => 'status', 'label' => 'الحالة', 'required' => false, 'group' => 'بيانات الفاتورة الأساسية'],
            ['field' => 'notes', 'label' => 'الملاحظات', 'required' => false, 'group' => 'بيانات الفاتورة الأساسية'],
            ['field' => 'description', 'label' => 'وصف البند', 'required' => true, 'group' => 'تفاصيل البند'],
            ['field' => 'quantity', 'label' => 'الكمية', 'required' => false, 'group' => 'تفاصيل البند'],
            ['field' => 'unit_amount', 'label' => 'سعر الوحدة', 'required' => true, 'group' => 'تفاصيل البند'],
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
            $this->fileName = $this->uploadedFile->getClientOriginalName();
            $this->filePath = $this->uploadedFile->store('temp-imports', 'local');

            $storagePath = Storage::disk('local')->path($this->filePath);

            $this->fileColumns = $this->importService->detectColumns($storagePath);

            if (empty($this->fileColumns)) {
                $this->errorMessage = 'لم يتم العثور على أعمدة في الملف. تأكد من أن الملف يحتوي على صف عنوان.';

                return;
            }

            $this->autoAssignColumns();

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
     * تعيين الأعمدة تلقائياً بناءً على تطابق الأسماء.
     */
    private function autoAssignColumns(): void
    {
        $this->columnMap = [];

        $synonyms = [
            'client' => ['client', 'العميل', 'اسم العميل', 'شركة العميل', 'اسم الشركة', 'شركة', 'company', 'client_name', 'client_id'],
            'contract' => ['contract', 'contract_id', 'الاشتراك', 'رقم الاشتراك', 'عقد', 'العقد'],
            'currency' => ['currency', 'currency_id', 'العملة', 'عملة'],
            'issue_date' => ['issue_date', 'تاريخ الإصدار', 'تاريخ الاصدار', 'تاريخ الفاتورة', 'تاريخ'],
            'due_date' => ['due_date', 'تاريخ الاستحقاق', 'الاستحقاق'],
            'status' => ['status', 'الحالة', 'حالة الفاتورة'],
            'notes' => ['notes', 'الملاحظات', 'ملاحظات'],
            'description' => ['description', 'وصف البند', 'الوصف', 'البند', 'تفاصيل', 'item', 'item_description'],
            'quantity' => ['quantity', 'الكمية', 'العدد', 'qty'],
            'unit_amount' => ['unit_amount', 'سعر الوحدة', 'السعر', 'المبلغ', 'المبلغ الإجمالي', 'price', 'unit_price', 'amount'],
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

        if (! isset($this->columnMap['client'])) {
            $this->columnMap['client'] = $this->fileColumns[0] ?? '';
        }
    }

    public function updateColumnMap(string $field, ?string $column): void
    {
        if ($column && $column !== '') {
            $this->columnMap[$field] = $column;
        } else {
            unset($this->columnMap[$field]);
        }
    }

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

    public function startImport(): void
    {
        if (! $this->filePath) {
            $this->errorMessage = 'لم يتم رفع ملف بعد';

            return;
        }

        $requiredFields = ['client', 'description', 'unit_amount'];
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

    public function reimport(): void
    {
        $this->importResultData = null;
        $this->step = 2;
    }

    public function render()
    {
        return view('livewire.invoice-importer-component');
    }
}
