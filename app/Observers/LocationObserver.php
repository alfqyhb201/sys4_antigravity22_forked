<?php

namespace App\Observers;

use App\Models\Location;
use App\Models\Tag;

class LocationObserver
{
    /**
     * عند إنشاء موقع جديد، يُسنَد تلقائياً لكل الوسوم
     * التي تفعّل خيار "تحديد جميع المواقع" (assign_all_locations = true).
     */
    public function created(Location $location): void
    {
        Tag::where('assign_all_locations', true)
            ->chunk(100, function ($tags) use ($location) {
                foreach ($tags as $tag) {
                    $tag->locations()->syncWithoutDetaching($location->id);
                }
            });
    }
}
