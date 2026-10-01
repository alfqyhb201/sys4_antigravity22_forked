<?php

namespace App\Filament\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * حالات مهمة التصميم المتاحة في النظام.
 */
enum DesignTaskStatus: string implements HasLabel
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case NeedsRevision = 'needs_revision';
    case Approved = 'approved';

    /**
     * الحصول على التسمية العربية للحالة.
     */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::InReview => 'قيد المراجعة',
            self::NeedsRevision => 'يحتاج تعديل',
            self::Approved => 'معتمد',
        };
    }
}
