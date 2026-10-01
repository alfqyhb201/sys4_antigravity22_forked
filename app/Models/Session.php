<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Session extends Model
{
    /**
     * جدول الجلسات في قاعدة البيانات
     *
     * @var string
     */
    protected $table = 'sessions';

    /**
     * المفتاح الرئيسي نصي (Session ID)
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * المفتاح الرئيسي غير متزايد تلقائياً
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * عدم استخدام timestamps الافتراضية
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * الحقول القابلة للتعبئة
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
    ];

    /**
     * تحويل الحقول
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_activity' => 'integer',
        ];
    }

    /**
     * علاقة الجلسة بالمستخدم صاحب الجلسة
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * الحصول على تاريخ آخر نشاط ككائن Carbon
     */
    public function getLastActivityDateAttribute(): ?Carbon
    {
        return $this->last_activity ? Carbon::createFromTimestamp($this->last_activity) : null;
    }

    /**
     * التحقق مما إذا كانت هذه الجلسة هي جلسة المتصفح الحالي
     */
    public function getIsCurrentSessionAttribute(): bool
    {
        return request()->hasSession() && $this->id === request()->session()->getId();
    }

    /**
     * استخراج اسم المتصفح ونظام التشغيل من الـ User-Agent
     */
    public function getAgentDetailsAttribute(): array
    {
        return static::parseUserAgent($this->user_agent);
    }

    /**
     * دالة مساعدة لتحليل الـ User Agent
     */
    public static function parseUserAgent(?string $userAgent): array
    {
        $userAgent = $userAgent ?? '';

        $platform = 'غير معروف';
        if (preg_match('/windows|win32/i', $userAgent)) {
            $platform = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
            $platform = 'macOS';
        } elseif (preg_match('/iphone/i', $userAgent)) {
            $platform = 'iOS (iPhone)';
        } elseif (preg_match('/ipad/i', $userAgent)) {
            $platform = 'iPadOS';
        } elseif (preg_match('/android/i', $userAgent)) {
            $platform = 'Android';
        } elseif (preg_match('/linux/i', $userAgent)) {
            $platform = 'Linux';
        }

        $browser = 'غير معروف';
        if (preg_match('/edg/i', $userAgent)) {
            $browser = 'Microsoft Edge';
        } elseif (preg_match('/chrome|crios/i', $userAgent) && ! preg_match('/opr|opera/i', $userAgent)) {
            $browser = 'Google Chrome';
        } elseif (preg_match('/firefox|fxios/i', $userAgent)) {
            $browser = 'Mozilla Firefox';
        } elseif (preg_match('/safari/i', $userAgent) && ! preg_match('/chrome|crios/i', $userAgent)) {
            $browser = 'Apple Safari';
        } elseif (preg_match('/opr|opera/i', $userAgent)) {
            $browser = 'Opera';
        }

        $deviceType = 'سطح المكتب';
        $deviceIcon = 'heroicon-o-computer-desktop';
        if (preg_match('/mobile|iphone|android.*mobile/i', $userAgent)) {
            $deviceType = 'هاتف ذكي';
            $deviceIcon = 'heroicon-o-device-phone-mobile';
        } elseif (preg_match('/tablet|ipad|android(?!.*mobile)/i', $userAgent)) {
            $deviceType = 'جهاز لوحي';
            $deviceIcon = 'heroicon-o-device-tablet';
        }

        return [
            'platform' => $platform,
            'browser' => $browser,
            'device_type' => $deviceType,
            'device_icon' => $deviceIcon,
        ];
    }
}
