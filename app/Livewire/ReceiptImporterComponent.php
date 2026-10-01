<?php

namespace App\Livewire;

use App\Services\ReceiptImportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class ReceiptImporterComponent extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public $uploadedFile = null;

    public ?string $filePath = null;

    public ?string $fileName = null;

    public array $fileColumns = [];

    public array $modelFields = [];

    public array $columnMap = [];

    public array $previewRows = [];

    public ?array $importResultData = null;

    public bool $isImporting = false;

    public int $progress = 0;

    public bool $isFileUploaded = false;

    public ?string $errorMessage = null;

    private ReceiptImportService $importService;

    public function boot(ReceiptImportService $importService): void
    {
        $this->importService = $importService;
    }

    public function mount(): void
    {
        $this->modelFields = $this->getModelFields();
    }

    private function getModelFields(): array
    {
        return [
            ['field' => 'client', 'label' => 'العميل (شركة/رقم)', 'required' => true, 'group' => 'بيانات السند الأساسية'],
            ['field' => 'amount', 'label' => 'المبلغ المدفوع', 'required' => true, 'group' => 'بيانات السند الأساسية'],
            ['field' => 'invoice', 'label' => 'رقم الفاتورة', 'required' => false, 'group' => 'بيانات السند الأساسية'],
            ['field' => 'paid_currency', 'label' => 'عملة الدفع', 'required' => false, 'group' => 'تفاصيل الدفع'],
            ['field' => 'payment_method', 'label' => 'طريقة الدفع', 'required' => false, 'group' => 'تفاصيل الدفع'],
            ['field' => 'receipt_date', 'label' => 'تاريخ السند', 'required' => false, 'group' => 'تفاصيل الدفع'],
            ['field' => 'reference_number', 'label' => 'رقم المرجع', 'required' => false, 'group' => 'تفاصيل الدفع'],
            ['field' => 'notes', 'label' => 'الملاحظات', 'required' => false, 'group' => 'تفاصيل الدفع'],
        ];
    }

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

    private function autoAssignColumns(): void
    {
        $this->columnMap = [];

        $synonyms = [
            'client' => ['client', 'العميل', 'اسم العميل', 'شركة العميل', 'اسم الشركة', 'شركة', 'company', 'client_name', 'client_id'],
            'amount' => ['amount', 'المبلغ', 'المبلغ المدفوع', 'المبلغ المعادل', 'original_amount', 'paid_amount', 'price', 'total'],
            'invoice' => ['invoice', 'invoice_id', 'invoice_number', 'الفاتورة', 'رقم الفاتورة'],
            'paid_currency' => ['paid_currency', 'paid_currency_id', 'currency', 'عملة الدفع', 'العملة'],
            'payment_method' => ['payment_method', 'طريقة الدفع', 'نوع الدفع', 'طريقة السداد'],
            'receipt_date' => ['receipt_date', 'تاريخ السند', 'تاريخ القبض', 'تاريخ الدفع', 'التاريخ', 'date'],
            'reference_number' => ['reference_number', 'رقم المرجع', 'المرجع', 'رقم الشيك', 'رقم العملية', 'ref'],
            'notes' => ['notes', 'الملاحظات', 'ملاحظات', 'البيان', 'السبب'],
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

        $requiredFields = ['client', 'amount'];
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
        return view('livewire.receipt-importer-component');
    }
}
