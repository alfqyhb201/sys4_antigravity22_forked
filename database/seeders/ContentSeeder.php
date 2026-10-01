<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // get admin user only
        $user = User::where('email', 'admin@admin.com')->first();

        // add added_by_user and created_by_user to all categories
        // إضافة فئات افتراضية
        $categories = [
            [
                'name' => 'تجارة',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'صرافة',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'سفريات وسياحة',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'خدمات طبية',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'نقل وتخليص جمركي',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        foreach ($categories as $category) {
            $category['added_by_user'] = $user->id;
            $category['created_by_user'] = $user->id;
            DB::table('categories')->insert($category);
        }

        // إضافة مجموعات وسوم افتراضية
        $tagGroups = [
            [
                'name' => 'اعلانات سفريات وسياحة',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'جمعة',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'مناسبات',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'اعلانات صرافة',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'اعلانات منتجات',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        foreach ($tagGroups as $tagGroup) {
            $tagGroup['added_by_user'] = $user->id;
            $tagGroup['created_by_user'] = $user->id;
            DB::table('tags_groups')->insert($tagGroup);
        }

        // إضافة مواقع افتراضية
        $locations = [
            [
                'name' => 'جيبوتي',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'عمان',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'السعودية',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'شمالي',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'جنوبي',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        foreach ($locations as $location) {
            $location['added_by_user'] = $user->id;
            $location['created_by_user'] = $user->id;
            DB::table('locations')->insert($location);
        }

        // إضافة عملات افتراضية
        $currencies = [
            [
                'currency' => 'USD',
                'currency_name' => 'دولار أمريكي',
                'value' => '1',
                'is_base' => false,
                'symbol' => '$',
                'decimal_places' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'currency' => 'YER',
                'currency_name' => 'ريال يمني',
                'value' => '530.00',
                'is_base' => false,
                'symbol' => 'ر.ي',
                'decimal_places' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'currency' => 'SAR',
                'currency_name' => 'ريال سعودي',
                'value' => '3.75',
                'is_base' => true,
                'symbol' => 'ر.س',
                'decimal_places' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        foreach ($currencies as $currency) {
            $currency['added_by_user'] = $user->id;
            $currency['created_by_user'] = $user->id;
            DB::table('currencies')->insert($currency);
        }
    }
}
