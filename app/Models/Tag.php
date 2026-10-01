<?php

namespace App\Models;

use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * يمثل هذا النموذج وسمًا (tag) في النظام.
 *
 * تستخدم الوسوم لتصنيف وتنظيم المحتوى والكيانات الأخرى،
 * ويمكن أن تحتوي على معلومات حول التكرار والجدولة.
 *
 * @property int $id
 * @property string $name
 * @property string|null $importance
 * @property int|null $tag_group_id
 * @property bool $is_repetition
 * @property string|null $repetition
 * @property int|null $weekly_times
 * @property int|null $monthly_times
 * @property int|null $yearly_times
 * @property bool $is_there_date_for_sending
 * @property string|null $date_for_sending_yearly
 * @property string|null $weekly_day
 * @property string|null $weekly_time
 * @property string|null $weekly_time_sm
 * @property int $added_by_user
 * @property int|null $updated_by_user
 * @property bool $assign_all_categories
 * @property bool $assign_all_locations
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Tag extends Model
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
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'weekly_day' => 'array',
        'is_repetition' => 'boolean',
        'is_there_date_for_sending' => 'boolean',
        'is_active' => 'boolean',
        'is_auto_assigned' => 'boolean',
        'assign_all_categories' => 'boolean',
        'assign_all_locations' => 'boolean',
    ];

    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'importance',
        'tag_group_id',
        'is_repetition',
        'repetition',
        'weekly_times',
        'monthly_times',
        'yearly_times',
        'is_there_date_for_sending',
        'date_for_sending_yearly',
        'weekly_day',
        'weekly_time',
        'weekly_time_sm',
        'created_by_user',
        'added_by_user',
        'updated_by_user',
        'is_active',
        'is_auto_assigned',
        'assign_all_categories',
        'assign_all_locations',
    ];

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الفئات (Categories).
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_tag');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع العملاء (Clients).
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_tag');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع المواقع (Locations).
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_tag');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع مجموعة الوسوم (TagGroup).
     */
    public function tagGroup(): BelongsTo
    {
        return $this->belongsTo(TagGroup::class, 'tag_group_id');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي أضاف الوسم.
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user');
    }

    /**
     * يحدد علاقة "لديه العديد" (hasMany) مع توزيعات الوسوم (ClientTagDistributions).
     */
    public function clientTagDistributions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientTagDistribution::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الأفكار (Ideas).
     */
    public function ideas(): BelongsToMany
    {
        return $this->belongsToMany(Idea::class, 'tag_idea');
    }

    protected static function booted(): void
    {
        static::saving(function (Tag $tag) {
            if (! $tag->is_there_date_for_sending) {
                $tag->date_for_sending_yearly = null;
                $tag->weekly_day = null;
                $tag->weekly_time = null;
                $tag->weekly_time_sm = null;
                $tag->weekly_times = null;
            } else {
                if ($tag->date_for_sending_yearly) {
                    $tag->weekly_day = null;
                    $tag->weekly_times = null;
                } else {
                    $tag->date_for_sending_yearly = null;
                }
            }
        });

        static::saved(function (Tag $tag) {
            if ($tag->is_auto_assigned) {
                $tag->clients()->detach();
            }
            if ($tag->assign_all_categories) {
                $tag->categories()->sync(Category::pluck('id')->toArray());
            }
            if ($tag->assign_all_locations) {
                $tag->locations()->sync(Location::pluck('id')->toArray());
            }
        });
    }
}
