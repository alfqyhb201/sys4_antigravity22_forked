<?php

namespace App\Livewire;

use App\Services\TagImportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class TagImporterComponent extends Component
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
     * حقول نموذج Tag المتاحة للتعيين.
     *
     * @var array<int, array{field: string, label: string, required: bool}>
     */
    public array $modelFields = [];

    /**
     * تعيين الأعمدة: [حقل Tag => اسم العمود في الملف].
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
    private TagImportService $importService;

    public function boot(TagImportService $importService): void
    {
        $this->importService = $importService;
    }

    public function mount(): void
    {
        $this->modelFields = $this->getModelFields();
    }

    /**
     * قائمة حقول Tag المتاحة للتعيين.
     *
     * @return array<int, array{field: string, label: string, required: bool}>
     */
    private function getModelFields(): array
    {
        return [
            ['field' => 'name',                  'label' => 'الاسم',              'required' => true],
            ['field' => 'importance',             'label' => 'درجة الأهمية',       'required' => false],
            ['field' => 'tag_group',              'label' => 'مجموعة الوسوم',      'required' => false],
            ['field' => 'is_repetition',          'label' => 'تمكين التكرار',      'required' => false],
            ['field' => 'repetition',             'label' => 'نوع التكرار',        'required' => false],
            ['field' => 'weekly_times',           'label' => 'مرات التكرار الأسبوعي', 'required' => false],
            ['field' => 'monthly_times',          'label' => 'مرات التكرار الشهري',   'required' => false],
            ['field' => 'yearly_times',           'label' => 'مرات التكرار السنوي',   'required' => false],
            ['field' => 'is_there_date_for_sending', 'label' => 'جدولة الإرسال',    'required' => false],
            ['field' => 'date_for_sending_yearly', 'label' => 'التاريخ السنوي',     'required' => false],
            ['field' => 'weekly_day',             'label' => 'أيام الأسبوع',       'required' => false],
            ['field' => 'weekly_time',            'label' => 'وقت الإرسال',        'required' => false],
            ['field' => 'weekly_time_sm',         'label' => 'وقت السوشيال ميديا', 'required' => false],
            ['field' => 'is_active',              'label' => 'نشط',                'required' => false],
            ['field' => 'is_auto_assigned',       'label' => 'تعيين تلقائي',       'required' => false],
            ['field' => 'categories',             'label' => 'التصنيفات',          'required' => false],
            ['field' => 'locations',              'label' => 'المواقع',            'required' => false],
            ['field' => 'clients',                'label' => 'العملاء',            'required' => false],
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
            // حفظ الملف في التخزين المؤقت
            $this->fileName = $this->uploadedFile->getClientOriginalName();
            $this->filePath = $this->uploadedFile->store('temp-imports');

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
     * تعيين الأعمدة تلقائياً بناءً على تطابق الأسماء مع حقول Tag.
     */
    private function autoAssignColumns(): void
    {
        $this->columnMap = [];

        // قاموس المرادفات: اسم العمود في الملف ← حقل Tag
        $synonyms = [
            'name' => ['name', 'الاسم', 'tag name', 'tag_name', 'tag'],
            'importance' => ['importance', 'الأهمية', 'degree', 'priority', 'الأولوية', 'درجة الأهمية'],
            'tag_group' => ['tag_group', 'tag group', 'مجموعة الوسوم', 'group', 'المجموعة'],
            'is_repetition' => ['is_repetition', 'repetition_enabled', 'تمكين التكرار', 'تكرار'],
            'repetition' => ['repetition', 'نوع التكرار', 'repetition_type', 'repeat'],
            'weekly_times' => ['weekly_times', 'weekly times', 'مرات التكرار الأسبوعي'],
            'monthly_times' => ['monthly_times', 'monthly times', 'مرات التكرار الشهري'],
            'yearly_times' => ['yearly_times', 'yearly times', 'مرات التكرار السنوي'],
            'is_there_date_for_sending' => ['is_there_date_for_sending', 'date_for_sending_enabled', 'جدولة الإرسال', 'scheduling'],
            'date_for_sending_yearly' => ['date_for_sending_yearly', 'yearly_date', 'التاريخ السنوي', 'date'],
            'weekly_day' => ['weekly_day', 'weekly days', 'أيام الأسبوع', 'days'],
            'weekly_time' => ['weekly_time', 'weekly time', 'وقت الإرسال', 'time'],
            'weekly_time_sm' => ['weekly_time_sm', 'social media time', 'وقت السوشيال ميديا'],
            'is_active' => ['is_active', 'active', 'نشط', 'الحالة', 'status'],
            'is_auto_assigned' => ['is_auto_assigned', 'auto_assign', 'تعيين تلقائي'],
            'categories' => ['categories', 'category', 'التصنيفات', 'تصنيفات', 'التصنيف'],
            'locations' => ['locations', 'location', 'المواقع', 'موقع', 'الموقع'],
            'clients' => ['clients', 'client', 'العملاء', 'عميل', 'العميل', 'company'],
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
        if (! isset($this->columnMap['name'])) {
            // إذا لم نجد تطابقاً لـ name، نأخذ أول عمود
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

        // التحقق من وجود حقل name في column map
        if (! isset($this->columnMap['name'])) {
            $this->errorMessage = 'يجب تعيين حقل "الاسم" لعمود من الملف';

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
        return view('livewire.tag-importer-component');
    }
}
