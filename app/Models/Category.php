<?php

namespace App\Models;

use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * يمثل هذا النموذج فئة أو تصنيفًا في النظام.
 *
 * يمكن ربط الفئات بالمصممين والوسوم.
 *
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Category extends Model
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
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع المصممين (Designers).
     */
    public function designers(): BelongsToMany
    {
        return $this->belongsToMany(Designer::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الوسوم (Tags).
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'category_tag');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع مجموعات الوسوم (TagGroups).
     */
    public function tagGroups(): BelongsToMany
    {
        return $this->belongsToMany(TagGroup::class, 'category_tag_group', 'category_id', 'tag_group_id');
    }
}
