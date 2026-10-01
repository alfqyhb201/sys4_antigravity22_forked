<?php

namespace App\Filament\Enums;

use Filament\Support\Contracts\HasLabel;

enum ClientTemplateType: string implements HasLabel
{
    case Cliche = 'cliche';
    case Greetings = 'greetings';
    case Newborns = 'newborns';
    case Condolences = 'condolences';
    case Stickers = 'stickers';
    case Backgrounds = 'backgrounds';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cliche => 'الكليشة',
            self::Greetings => 'نموذج التهاني',
            self::Newborns => 'نموذج المواليد',
            self::Condolences => 'نموذج التعزية',
            self::Stickers => 'نموذج الملصقات',
            self::Backgrounds => 'نموذج الخلفيات',
        };
    }
}
