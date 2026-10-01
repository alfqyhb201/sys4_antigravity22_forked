<?php

namespace App\Models;

use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * يمثل هذا النموذج مجموعة وسوم في النظام.
 *
 * يستخدم لتجميع وتنظيم الوسوم ذات الصلة.
 *
 * @property int $id
 * @property string $name
 * @property bool $assign_all_categories
 * @property int $added_by_user
 * @property int|null $updated_by_user
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class TagGroup extends Model
{
    use HasFactory, HasUserTracking, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * اسم الجدول المرتبط بالنموذج.
     *
     * @var string
     */
    protected $table = 'tags_groups';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'assign_all_categories' => 'boolean',
    ];

    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'assign_all_categories',
        'created_by_user',
        'added_by_user',
        'updated_by_user',
    ];

    protected static function booted(): void
    {
        static::saved(function (TagGroup $tagGroup) {
            if ($tagGroup->assign_all_categories) {
                $tagGroup->categories()->sync(Category::pluck('id')->toArray());
            }
        });
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي أضاف مجموعة الوسوم.
     */
    public function addedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي قام بتحديث مجموعة الوسوم.
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user');
    }

    /**
     * يحدد علاقة "لديه العديد" (hasMany) مع الوسوم (Tags).
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع احتياجات العميل (ClientNeeds).
     */
    public function clientNeeds(): BelongsToMany
    {
        return $this->belongsToMany(ClientNeed::class, 'client_need_tags_group');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الفئات (Categories).
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_tag_group', 'tag_group_id', 'category_id');
    }
}
