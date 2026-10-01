<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasLabel
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case InReview = 'in_review';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'في الانتظار',
            self::InProgress => 'قيد التنفيذ',
            self::InReview => 'قيد المراجعة',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغي',
        };
    }
}
