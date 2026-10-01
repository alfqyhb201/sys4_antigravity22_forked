<?php

namespace App\Services;

/**
 * يمثل نتيجة عملية الاستيراد.
 * يحمل إحصائيات النجاح والفشل وتفاصيل الأخطاء.
 */
class ImportResult
{
    /**
     * @param  int  $total  إجمالي عدد الصفوف
     * @param  int  $successful  عدد الصفوف التي تم استيرادها بنجاح
     * @param  int  $failed  عدد الصفوف التي فشلت
     * @param  array<int, string>  $errors  أخطاء الصفوف [رقم الصف => رسالة الخطأ]
     * @param  array<int, string>  $warnings  تحذيرات الصفوف [رقم الصف => رسالة التحذير]
     */
    public function __construct(
        public int $total = 0,
        public int $successful = 0,
        public int $failed = 0,
        public array $errors = [],
        public array $warnings = [],
    ) {}

    /**
     * إضافة خطأ لصف معين.
     */
    public function addError(int $rowNumber, string $message): void
    {
        $this->errors[$rowNumber] = $message;
        $this->failed++;
    }

    /**
     * إضافة تحذير لصف معين.
     */
    public function addWarning(int $rowNumber, string $message): void
    {
        $this->warnings[$rowNumber] = $message;
    }

    /**
     * تسجيل صف ناجح.
     */
    public function addSuccess(): void
    {
        $this->successful++;
    }
}
