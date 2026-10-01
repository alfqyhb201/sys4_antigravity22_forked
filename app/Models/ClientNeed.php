<?php

namespace App\Models;

use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * يمثل هذا النموذج احتياجات العميل.
 *
 * يحتوي على معلومات حول احتياجات العميل ومستوى أهميتها،
 * بالإضافة إلى علاقاتها مع الوسوم والفئات والعملاء والمستخدمين.
 *
 * @property int $id
 * @property string $name
 * @property array|null $importance_level
 * @property int $created_by_user
 * @property int $added_by_user
 * @property int|null $updated_by_user
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ClientNeed extends Model
{
    use HasUserTracking, LogsActivity;

    /**
     * اسم الجدول المرتبط بالنموذج.
     *
     * @var string
     */
    protected $table = 'client_needs';

    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'importance_level', // مستوى الأهمية
        'created_by_user',
        'added_by_user',
        'updated_by_user',
    ];

    /**
     * السمات التي يجب تحويلها إلى أنواع بيانات أصلية.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'importance_level' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الوسوم (Tags).
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'client_need_tags_group', 'client_need_id', 'tag_id');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الفئات (Categories).
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_client_need');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع العملاء (Clients).
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_client_need');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي أضاف الاحتياج.
     */
    public function addedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user');
    }
}
