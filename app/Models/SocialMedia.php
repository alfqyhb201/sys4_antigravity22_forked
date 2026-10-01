<?php

namespace App\Models;

use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * يمثل هذا النموذج وسيلة تواصل اجتماعي في النظام.
 *
 * يحتوي على معلومات حول وسيلة التواصل الاجتماعي، بالإضافة إلى
 * علاقاتها مع المستخدمين الذين قاموا بإضافتها وتحديثها.
 *
 * @property int $id
 * @property string $name
 * @property int $added_by_user
 * @property int|null $updated_by_user
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SocialMedia extends Model
{
    use HasUserTracking, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'created_by_user', 'added_by_user', 'updated_by_user'];

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي أضاف وسيلة التواصل.
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user');
    }

    /**
     * العملاء المرتبطون بهذه الوسيلة.
     */
    public function clients(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_social_media')
            ->withPivot(['account_url', 'credentials', 'notes'])
            ->withTimestamps();
    }
}
