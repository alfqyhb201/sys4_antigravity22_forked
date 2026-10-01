<?php

namespace App\Livewire;

use App\Services\CategoryImportService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class CategoryImporterComponent extends Component
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
     * حقول نموذج التصنيف المتاحة للتعيين.
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
    private CategoryImportService $importService;

    public function boot(CategoryImportService $importService): void
    {
        $this->importService = $importService;
    }

    public function mount(): void
    {
        $this->modelFields = $this->getModelFields();
    }

    /**
     * قائمة حقول التصنيف المتاحة للتعيين.
     *
     * @return array<int, array{field: string, label: string, required: bool, group: string}>
     */
    private function getModelFields(): array
    {
        return [
            ['field' => 'name', 'label' => 'اسم التصنيف', 'required' => true, 'group' => 'بيانات التصنيف'],
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
                'max:10240',
            ],
        ]);

        $this->errorMessage = null;
        $this->step = 1;

        try {
            $this->fileName = $this->uploadedFile->getClientOriginalName();
            $this->filePath = $this->uploadedFile->store('temp-imports', 'local');

            $storagePath = Storage::disk('local')->path($this->filePath);

            $this->fileColumns = $this->importService->getFileColumns($storagePath);

            if (empty($this->fileColumns)) {
                $this->errorMessage = 'لم يتم العثور على أعمدة في الملف. تأكد من أن الملف يحتوي على صف عنوان.';

                return;
            }

            $this->autoAssignColumns();

            $allRows = $this->importService->readFile($storagePath);
            $this->previewRows = array_slice($allRows, 0, 10);

            $this->isFileUploaded = true;
            $this->step = 2;
        } catch (\Throwable $e) {
            $this->errorMessage = 'حدث خطأ أثناء قراءة الملف: '.$e->getMessage();
        }
    }

    /**
     * تعيين الأعمدة تلقائياً بناءً على تطابق الأسماء مع حقول التصنيف.
     */
    private function autoAssignColumns(): void
    {
        $this->columnMap = [];

        $synonyms = [
            'name' => ['name', 'اسم التصنيف', 'التصنيف', 'category', 'category_name', 'اسم تصنيف'],
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

        if (! isset($this->columnMap['name'])) {
            $this->columnMap['name'] = $this->fileColumns[0] ?? '';
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

        if (! isset($this->columnMap['name'])) {
            $this->errorMessage = 'يجب تعيين الحقل الإلزامي (اسم التصنيف) لأحد أعمدة الملف.';

            return;
        }

        $this->isImporting = true;
        $this->progress = 0;
        $this->step = 3;
        $this->errorMessage = null;

        try {
            $storagePath = Storage::disk('local')->path($this->filePath);
            $allRows = $this->importService->readFile($storagePath);

            $result = $this->importService->import(
                $allRows,
                $this->columnMap
            );

            $this->importResultData = [
                'total' => count($allRows),
                'successful' => $result['imported'],
                'failed' => $result['skipped'],
                'errors' => $result['errors'],
                'warnings' => [],
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
        return view('livewire.category-importer-component');
    }
}
