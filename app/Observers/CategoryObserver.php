<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\Tag;
use App\Models\TagGroup;

class CategoryObserver
{
    /**
     * عند إنشاء تصنيف جديد، يُسنَد تلقائياً لكل الوسوم ومجموعات الوسوم
     * التي تفعّل خيار "تحديد جميع التصنيفات" (assign_all_categories = true).
     */
    public function created(Category $category): void
    {
        Tag::where('assign_all_categories', true)
            ->chunk(100, function ($tags) use ($category) {
                foreach ($tags as $tag) {
                    $tag->categories()->syncWithoutDetaching($category->id);
                }
            });

        TagGroup::where('assign_all_categories', true)
            ->chunk(100, function ($tagGroups) use ($category) {
                foreach ($tagGroups as $tagGroup) {
                    $tagGroup->categories()->syncWithoutDetaching($category->id);
                }
            });
    }
}
