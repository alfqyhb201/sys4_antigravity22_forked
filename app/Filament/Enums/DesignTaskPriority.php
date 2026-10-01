<?php

namespace App\Filament\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * مستويات أهمية مهمة التصميم.
 */
enum DesignTaskPriority: string implements HasLabel
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /**
     * الحصول على التسمية العربية للأهمية.
     */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::High => 'عالية',
            self::Medium => 'متوسطة',
            self::Low => 'منخفضة',
        };
    }
}
