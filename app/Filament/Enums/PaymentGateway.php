<?php

namespace App\Filament\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentGateway: string implements HasLabel
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Check = 'check';
    case Jeeb = 'jeeb';
    case Kuraimi = 'kuraimi';
    case UnifiedNetwork = 'unified_network';
    case Jawali = 'jawali';
    case Wallet = 'wallet';
    case Discount = 'discount';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cash => 'كاش',
            self::Transfer => 'حوالة',
            self::Check => 'شيك',
            self::Jeeb => 'جيب',
            self::Kuraimi => 'الكريمي',
            self::UnifiedNetwork => 'الشبكة الموحدة',
            self::Jawali => 'جوالي',
            self::Wallet => 'محفظة العميل',
            self::Discount => 'خصم',
        };
    }
}
