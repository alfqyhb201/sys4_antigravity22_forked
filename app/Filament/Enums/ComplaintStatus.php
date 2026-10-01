<?php

namespace App\Filament\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * حالات الشكوى المتاحة في النظام.
 */
enum ComplaintStatus: string implements HasLabel
{
    case New = 'new';
    case Resolved = 'resolved';

    /**
     * الحصول على التسمية العربية للحالة.
     */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::New => 'جديدة',
            self::Resolved => 'تم الحل',
        };
    }
}
