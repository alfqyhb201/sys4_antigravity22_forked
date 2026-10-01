<?php

namespace App\Enums;

enum ImportanceLevel: string
{
    case VeryHigh = 'very_high';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Other = 'other';

    public function rank(): int
    {
        return match ($this) {
            self::VeryHigh => 1,
            self::High => 2,
            self::Medium => 3,
            self::Low => 4,
            self::Other => 5,
        };
    }

    public static function fromString(string $value): self
    {
        $normalized = trim(strtolower($value));

        $veryHighVariants = [
            'very_high',
            'very high',
            'veryhigh',
            'عالية جدا',
            'عالية جداً',
            'عالي جدا',
            'عالي جداً',
            'هام جدا',
            'هام جداً',
        ];

        $highVariants = [
            'high',
            'عالية',
            'عالي',
            'هام',
        ];

        $mediumVariants = [
            'medium',
            'متوسطة',
            'متوسط',
        ];

        $lowVariants = [
            'low',
            'منخفضة',
            'منخفض',
        ];

        if (in_array($normalized, $veryHighVariants, true)) {
            return self::VeryHigh;
        }

        if (in_array($normalized, $highVariants, true)) {
            return self::High;
        }

        if (in_array($normalized, $mediumVariants, true)) {
            return self::Medium;
        }

        if (in_array($normalized, $lowVariants, true)) {
            return self::Low;
        }

        return self::Other;
    }
}
