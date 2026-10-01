<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Permission\Traits\HasRoles;

/**
 * يمثل هذا النموذج مستخدمًا في النظام.
 *
 * هذا النموذج مسؤول عن مصادقة المستخدمين وتخزين معلوماتهم الأساسية.
 * كما أنه يتكامل مع Filament لإدارة لوحة التحكم وصور المستخدمين الرمزية.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $username
 * @property string|null $work_phone_number
 * @property string|null $personal_phone_number
 * @property string|null $profile_image
 * @property string|null $hire_date
 * @property int $status
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use CausesActivity, HasFactory, HasRoles, Notifiable;

    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'work_phone_number',
        'personal_phone_number',
        'profile_image',
        'hire_date',
        'status',
    ];

    /**
     * السمات التي يجب إخفاؤها عند التحويل إلى JSON.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * يتحقق مما إذا كان المستخدم يمكنه الوصول إلى لوحة تحكم Filament.
     *
     * @param  \Filament\Panel  $panel  لوحة التحكم التي يتم الوصول إليها.
     * @return bool `true` إذا كان بإمكان المستخدم الوصول، و `false` بخلاف ذلك.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->status == 0) {
            session()->invalidate();

            return false;
        }

        return $this->status === 1;
    }

    /**
     * يحصل على عنوان URL للصورة الرمزية للمستخدم في Filament.
     *
     * @return string|null عنوان URL للصورة الرمزية، أو `null` إذا لم تكن موجودة.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->profile_image
            ? asset('storage/'.$this->profile_image)
            : null;
    }

    /**
     * جلسات المستخدم في النظام
     */
    public function sessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Session::class);
    }

    /**
     * أحدث جلسة نشاط للمستخدم
     */
    public function latestSession(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Session::class)->latestOfMany('last_activity');
    }

    /**
     * استرجاع معلومات آخر نشاط للمستخدم (من الكاش المحفوظ أو من جلسات قاعدة البيانات)
     */
    public function getLastActivityInfo(): ?array
    {
        $cached = \Illuminate\Support\Facades\Cache::get('user_last_activity_'.$this->id);
        if ($cached && ! empty($cached['timestamp'])) {
            return $cached;
        }

        if ($this->latestSession) {
            return [
                'timestamp' => $this->latestSession->last_activity,
                'ip_address' => $this->latestSession->ip_address,
                'user_agent' => $this->latestSession->user_agent,
            ];
        }

        return null;
    }

    /**
     * الحصول على رابط لوحة التحكم الافتراضية المناسبة لدور المستخدم.
     */
    public function getDefaultDashboardUrl(): string
    {
        if ($this->hasRole(['admin', 'super_admin'])) {
            return \App\Filament\Pages\Dashboard::getUrl();
        }

        if ($this->hasRole('supervisor') || $this->can('view_supervisor_dashboard')) {
            return \App\Filament\Pages\SupervisorDashboard::getUrl();
        }

        if ($this->hasRole('designer') || $this->can('view_designer_dashboard')) {
            return \App\Filament\Pages\DesignerDashboard::getUrl();
        }

        if ($this->hasRole('reviewer') || $this->can('view_reviewer_dashboard')) {
            return \App\Filament\Pages\ReviewerDashboard::getUrl();
        }

        if ($this->hasRole('accountant') || $this->can('view_financial_reports')) {
            return \App\Filament\Pages\AccountingDashboard::getUrl();
        }

        if ($this->hasRole('social_media') || $this->can('view_social_media_publishing') || $this->can('view_any_social_media')) {
            return \App\Filament\Pages\SocialMediaPublishing::getUrl();
        }

        return \App\Filament\Pages\Dashboard::getUrl();
    }

    /**
     * الحصول على السمات التي يجب تحويلها.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
