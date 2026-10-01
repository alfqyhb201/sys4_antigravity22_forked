<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * يمثل إعداداً لبيئة النظام العامة.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SystemSetting extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * جلب قيمة إعداد معين.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $setting = static::where('key', $key)->first();

            return $setting ? $setting->value : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * تعيين أو تحديث قيمة إعداد معين.
     */
    public static function set(string $key, mixed $value): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );
    }

    /**
     * الحصول على الحد الأقصى لحجم الملفات المرفوعة بالكيلوبايت (KB).
     */
    public static function getMaxFileSize(): int
    {
        $val = static::get('max_file_size');
        if ($val !== null && is_numeric($val) && (int) $val > 0) {
            return (int) $val;
        }

        return (int) config('filesystems.max_file_size', 10240);
    }

    /**
     * تعيين الحد الأقصى لحجم الملفات المرفوعة بالكيلوبايت (KB).
     */
    public static function setMaxFileSize(int $kilobytes): static
    {
        return static::set('max_file_size', $kilobytes);
    }
}
