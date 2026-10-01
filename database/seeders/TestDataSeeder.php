<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Filament\Enums\ClientTemplateType;
use App\Filament\Enums\ComplaintStatus;
use App\Filament\Enums\DesignTaskPriority;
use App\Filament\Enums\DesignTaskStatus;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDesigner;
use App\Models\ClientNeed;
use App\Models\ClientTagDistribution;
use App\Models\ClientTemplate;
use App\Models\Complaint;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\Custody;
use App\Models\Designer;
use App\Models\DesignTask;
use App\Models\Idea;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Location;
use App\Models\Order;
use App\Models\Receipt;
use App\Models\SocialMedia;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Database\Seeders\TestData\ArabicDataProvider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class TestDataSeeder extends Seeder
{
    /**
     * خيارات التشغيل.
     */
    private array $options = [
        'clients' => 100,
        'designers' => 12,
        'invoices' => 200,
        'fresh' => false,
    ];

    /**
     * مراجع الكيانات التي تم إنشاؤها للاستخدام عبر الدوال.
     */
    private array $refs = [];

    /**
     * تشغيل السيدر.
     */
    public function run(array $options = []): void
    {
        $this->options = array_merge($this->options, $options);

        if ($this->options['fresh']) {
            $this->truncateAllTestTables();
        }

        $this->command?->info('🚀 بدء تجهيز بيئة البيانات الاختبارية...');

        // المرحلة 1: المستخدمون
        $this->command?->info('📦 1/14 تجهيز المستخدمين...');
        $this->createUsers();

        // المرحلة 2-5: البيانات الأساسية
        $this->command?->info('📦 2/14 تجهيز الفئات...');
        $this->createCategories();
        $this->command?->info('📦 3/14 تجهيز مجموعات الوسوم...');
        $this->createTagGroups();
        $this->command?->info('📦 4/14 تجهيز المواقع...');
        $this->createLocations();
        $this->command?->info('📦 5/14 تجهيز العملات...');
        $this->createCurrencies();

        // المرحلة 6: احتياجات العملاء
        $this->command?->info('📦 6/14 تجهيز احتياجات العملاء...');
        $this->createClientNeeds();

        // المرحلة 7: الوسوم
        $this->command?->info('📦 7/14 تجهيز الوسوم...');
        $this->createTags();

        // المرحلة 8: العملاء
        $this->command?->info('📦 8/14 تجهيز العملاء...');
        $this->createClients();

        // المرحلة 9: المصممين
        $this->command?->info('📦 9/14 تجهيز المصممين...');
        $this->createDesigners();

        // المرحلة 10: الاشتراكات
        $this->command?->info('📦 10/14 تجهيز الاشتراكات...');
        $this->createContracts();

        // المرحلة 11: الفواتير
        $this->command?->info('📦 11/14 تجهيز الفواتير...');
        $this->createInvoices();

        // المرحلة 12: سندات القبض
        $this->command?->info('📦 12/14 تجهيز سندات القبض...');
        $this->createReceipts();

        // المرحلة 13: مهام التوزيع والتصميم
        $this->command?->info('📦 13/14 تجهيز مهام التوزيع والتصميم...');
        $this->createClientDesignerAssignments();
        $this->createClientTagDistributions();
        $this->createDesignTasks();
        $this->createOrders();

        // المرحلة 14: الكيانات المتبقية
        $this->command?->info('📦 14/14 تجهيز الكيانات المتبقية...');
        $this->createSocialMedia();
        $this->createCustodyItems();
        $this->createComplaints();
        $this->createIdeas();
        $this->createPivotData();
        $this->createClientTemplates();

        $this->command?->info('✅ تم تجهيز بيئة البيانات الاختبارية بنجاح!');
        $this->command?->info(sprintf(
            '📊 الإحصائيات: %d عميل | %d مصمم | %d اشتراك | %d فاتورة | %d سند | %d مهمة تصميم',
            count($this->refs['clients'] ?? []),
            count($this->refs['designers'] ?? []),
            count($this->refs['contracts'] ?? []),
            count($this->refs['invoices'] ?? []),
            count($this->refs['receipts'] ?? []),
            count($this->refs['design_tasks'] ?? [])
        ));
    }

    /**
     * مسح جميع جداول بيانات الاختبار.
     */
    private function truncateAllTestTables(): void
    {
        $this->command?->warn('🧹 جاري مسح بيانات الاختبار السابقة...');

        Schema::disableForeignKeyConstraints();

        $tables = [
            'client_tag_distributions',
            'client_designer',
            'design_tasks',
            'orders',
            'complaints',
            'invoice_items',
            'receipts',
            'invoices',
            'client_templates',
            'contracts',
            'client_tag',
            'client_client_need',
            'client_idea',
            'idea_client_blocks',
            'tag_idea',
            'location_idea',
            'category_designer',
            'category_tag',
            'location_tag',
            'category_tag_group',
            'client_need_tags_group',
            'category_client_need',
            'ideas',
            'client_needs',
            'clients',
            'designers',
            'custody',
            'social_media',
            'tags',
            'tags_groups',
            'categories',
            'locations',
            'currencies',
            'client_needs',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        // حذف المستخدمين الاختباريين (الاحتفاظ بالمستخدم الأساسي admin@admin.com)
        $adminUser = User::where('email', 'admin@admin.com')->first();
        User::where('email', '!=', 'admin@admin.com')->delete();
        // إزالة الأدوار من جميع المستخدمين عدا admin
        DB::table('model_has_roles')->where('model_id', '!=', $adminUser?->id)->delete();

        Schema::enableForeignKeyConstraints();

        $this->command?->info('✅ تم مسح جميع البيانات.');
    }

    // ========================================================================
    // المرحلة 1: المستخدمون والأدوار
    // ========================================================================

    private function createUsers(): void
    {
        $adminUser = User::where('email', 'admin@admin.com')->first();
        if (! $adminUser) {
            // إنشاء المستخدم الأساسي إن لم يكن موجودًا
            $adminUser = User::create([
                'name' => 'عبدالله الفقيه',
                'username' => 'admin',
                'email' => 'admin@admin.com',
                'password' => Hash::make('123321'),
                'work_phone_number' => '777777777',
                'personal_phone_number' => '777777777',
                'status' => 1,
            ]);
            $adminUser->assignRole('admin');
        }

        $this->refs['admin_user'] = $adminUser;

        // إنشاء المستخدمين الاختباريين
        $createdUsers = [];

        foreach (ArabicDataProvider::$employeeNames as $key => $emp) {
            $username = match ($key) {
                'inactive' => 'inactive_user',
                default => str($emp['name'])->slug('_')->toString(),
            };

            $email = match ($key) {
                'inactive' => 'inactive@test.com',
                default => $username.'@test.com',
            };

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $emp['name'],
                    'username' => $username,
                    'password' => Hash::make('123321'),
                    'work_phone_number' => fake()->numerify('77#######'),
                    'personal_phone_number' => fake()->numerify('77#######'),
                    'status' => $key === 'inactive' ? 0 : 1,
                ]
            );

            $user->assignRole($emp['role']);
            $createdUsers[$key] = $user;
        }

        $this->refs['users'] = $createdUsers;

        // إنشاء حسابات المصممين (مستخدم + مصمم)
        $this->createDesignerUsers();
    }

    private function createDesignerUsers(): void
    {
        $designerUsers = [];
        $i = 1;

        foreach (ArabicDataProvider::$designerNames as $name) {
            $username = 'designer_'.$i;
            $email = $username.'@test.com';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'username' => $username,
                    'password' => Hash::make('123321'),
                    'work_phone_number' => fake()->numerify('77#######'),
                    'personal_phone_number' => fake()->numerify('77#######'),
                    'status' => 1,
                ]
            );

            $user->assignRole('designer');
            $designerUsers[] = $user;
            $i++;
        }

        $this->refs['designer_users'] = $designerUsers;
    }

    // ========================================================================
    // المرحلة 2: الفئات
    // ========================================================================

    private function createCategories(): void
    {
        $categories = [];
        foreach (ArabicDataProvider::$categoryNames as $name) {
            $cat = Category::firstOrCreate(['name' => $name]);
            $categories[] = $cat;
        }
        $this->refs['categories'] = $categories;
    }

    // ========================================================================
    // المرحلة 3: مجموعات الوسوم
    // ========================================================================

    private function createTagGroups(): void
    {
        $groups = [];
        foreach (ArabicDataProvider::$tagGroupNames as $name) {
            $group = TagGroup::firstOrCreate(['name' => $name]);
            $groups[] = $group;
        }
        $this->refs['tag_groups'] = $groups;
    }

    // ========================================================================
    // المرحلة 4: المواقع
    // ========================================================================

    private function createLocations(): void
    {
        $locations = [];
        foreach (ArabicDataProvider::$locationNames as $name) {
            $loc = Location::firstOrCreate(['name' => $name]);
            $locations[] = $loc;
        }
        $this->refs['locations'] = $locations;
    }

    // ========================================================================
    // المرحلة 5: العملات
    // ========================================================================

    private function createCurrencies(): void
    {
        $currencies = [];
        $adminId = $this->refs['admin_user']->id;

        foreach (ArabicDataProvider::$currencyData as $data) {
            $currency = Currency::firstOrCreate(
                ['currency' => $data['code']],
                [
                    'currency_name' => $data['name'],
                    'value' => $data['value'],
                    'added_by_user' => $adminId,
                ]
            );
            $currencies[] = $currency;
        }
        $this->refs['currencies'] = $currencies;
        $this->refs['default_currency_id'] = $currencies[0]->id; // YER
    }

    // ========================================================================
    // المرحلة 6: احتياجات العملاء
    // ========================================================================

    private function createClientNeeds(): void
    {
        $needs = [];
        $adminId = $this->refs['admin_user']->id;

        $importanceLevels = [
            ['very_high', 'high', 'medium'],
            ['high', 'medium'],
            ['very_high', 'high'],
            ['medium', 'low'],
            ['high', 'medium', 'low'],
            ['medium'],
            ['very_high'],
            ['high', 'medium'],
            ['medium', 'low'],
            ['low'],
            ['very_high', 'high', 'medium', 'low'],
            ['high'],
        ];

        foreach (ArabicDataProvider::$clientNeedNames as $i => $name) {
            $need = ClientNeed::create([
                'name' => $name,
                'importance_level' => $importanceLevels[$i] ?? ['medium'],
                'added_by_user' => $adminId,
            ]);
            $needs[] = $need;
        }
        $this->refs['client_needs'] = $needs;
    }

    // ========================================================================
    // المرحلة 7: الوسوم
    // ========================================================================

    private function createTags(): void
    {
        $tags = [];
        $adminId = $this->refs['admin_user']->id;
        $allCategories = $this->refs['categories'];

        foreach (ArabicDataProvider::$tagData as $i => $tagInfo) {
            $groupId = $tagInfo['group'];
            $tagGroup = $this->refs['tag_groups'][$groupId] ?? $this->refs['tag_groups'][0];

            $data = [
                'name' => $tagInfo['name'],
                'importance' => $tagInfo['importance'],
                'tag_group_id' => $tagGroup->id,
                'is_active' => true,
                'is_auto_assigned' => fake()->boolean(70),
                'added_by_user' => $adminId,
            ];

            if (isset($tagInfo['scheduling'])) {
                $sch = $tagInfo['scheduling'];
                $data['is_repetition'] = $sch['is_repetition'] ?? false;
                $data['is_there_date_for_sending'] = $sch['is_there_date_for_sending'] ?? false;

                if (($data['is_repetition'])) {
                    $data['repetition'] = $sch['repetition'] ?? 'weekly';
                    if (isset($sch['weekly_times'])) {
                        $data['weekly_times'] = $sch['weekly_times'];
                    }
                    if (isset($sch['monthly_times'])) {
                        $data['monthly_times'] = $sch['monthly_times'];
                    }
                    if (isset($sch['yearly_times'])) {
                        $data['yearly_times'] = $sch['yearly_times'];
                    }
                    if (isset($sch['weekly_day'])) {
                        $data['weekly_day'] = $sch['weekly_day'];
                    }
                }

                if (($data['is_there_date_for_sending']) && isset($sch['date_for_sending_yearly'])) {
                    $data['date_for_sending_yearly'] = $sch['date_for_sending_yearly'];
                }
            }

            $tag = Tag::create($data);
            $tags[] = $tag;

            // ربط الوسم بفئة عشوائية (1-2 فئات)
            $randomCategories = fake()->randomElements($allCategories, fake()->numberBetween(1, 2));
            foreach ($randomCategories as $cat) {
                DB::table('category_tag')->insert([
                    'category_id' => $cat->id,
                    'tag_id' => $tag->id,
                ]);
            }
        }

        $this->refs['tags'] = $tags;

        // ربط المواقغ ببعض الوسوم
        $allLocations = $this->refs['locations'];
        foreach (fake()->randomElements($tags, 20) as $tag) {
            $location = fake()->randomElement($allLocations);
            DB::table('location_tag')->insert([
                'location_id' => $location->id,
                'tag_id' => $tag->id,
            ]);
        }
    }

    // ========================================================================
    // المرحلة 8: العملاء
    // ========================================================================

    private function createClients(): void
    {
        $clientCount = $this->options['clients'];
        $clients = [];
        $adminId = $this->refs['admin_user']->id;
        $allCategories = $this->refs['categories'];
        $allLocations = $this->refs['locations'];
        $allNeeds = $this->refs['client_needs'];
        $allTags = $this->refs['tags'];

        // توليد أسماء شركات إضافية
        $baseNames = ArabicDataProvider::$companyNames;
        $personNames = ArabicDataProvider::$clientPersonNames;
        $jobTitles = ArabicDataProvider::$contactJobTitles;
        $streets = ArabicDataProvider::$streetNames;

        for ($i = 0; $i < $clientCount; $i++) {
            // اختيار اسم الشركة
            if ($i < count($baseNames)) {
                $company = $baseNames[$i];
            } else {
                $person = $personNames[$i % count($personNames)];
                $company = 'مؤسسة '.$person.' التجارية';
            }

            // اختيار اسم العميل
            $clientName = $personNames[array_rand($personNames)];

            $category = $allCategories[array_rand($allCategories)];
            $location = $allLocations[array_rand($allLocations)];

            // تحديد الخصائص
            $status = true;
            $suspendedAt = null;
            $isCreditAllowed = fake()->boolean(20);
            $walletBalance = 0;
            $isPayPerDesign = false;
            $hasGenerateFeature = false;
            $generateTypes = null;
            $enableVeryHigh = true;
            $importanceWeights = null;
            $fixedDesignerId = null;
            $additionalDesignsBalance = 0;

            // ~10% عملاء غير نشطين
            if ($i >= $clientCount - 10) {
                $status = false;
            }

            // ~5% معلقين
            if ($i >= round($clientCount * 0.70) && $i < round($clientCount * 0.75)) {
                $suspendedAt = now()->subDays(fake()->numberBetween(1, 30));
            }

            // ~5% معلقين تلقائيًا (suspended_at مع إيقاف تلقائي)
            if ($i >= round($clientCount * 0.75) && $i < round($clientCount * 0.80)) {
                $suspendedAt = now()->subDays(fake()->numberBetween(1, 30));
            }

            // ~15% مع رصيد محفظة
            if ($i % 7 === 0) {
                $walletBalance = fake()->randomFloat(2, 500, 10000);
            }

            // ~5% pay-per-design
            if ($i % 20 === 0) {
                $isPayPerDesign = true;
            }

            // ~10% مع generate feature
            if ($i % 10 === 0) {
                $hasGenerateFeature = true;
                $generateTypes = fake()->randomElements(['facebook', 'instagram', 'twitter', 'snapchat', 'tiktok'], fake()->numberBetween(1, 3));
            }

            // ~20% مع importance_weights
            if ($i % 5 === 0) {
                $enableVeryHigh = fake()->boolean(50);
                $importanceWeights = [
                    'very_high' => $enableVeryHigh ? fake()->randomFloat(1, 1, 5) : 0,
                    'high' => fake()->randomFloat(1, 1, 5),
                    'medium' => fake()->randomFloat(1, 1, 5),
                    'low' => fake()->randomFloat(1, 1, 5),
                ];
            }

            // تقييم عشوائي
            $rating = fake()->numberBetween(1, 5);

            $data = [
                'company' => $company,
                'client_name' => $clientName,
                'location_id' => $location->id,
                'category_id' => $category->id,
                'address' => $streets[array_rand($streets)].'، '.$location->name,
                'contact_number' => fake()->numerify('77########'),
                'contact_job' => $jobTitles[array_rand($jobTitles)],
                'status' => $status,
                'suspended_at' => $suspendedAt,
                'is_credit_allowed' => $isCreditAllowed,
                'wallet_balance' => $walletBalance,
                'is_pay_per_design' => $isPayPerDesign,
                'has_generate_feature' => $hasGenerateFeature,
                'generate_types' => $generateTypes,
                'enable_very_high' => $enableVeryHigh,
                'importance_weights' => $importanceWeights,
                'customer_rating_value' => $rating,
                'additional_designs_balance' => $additionalDesignsBalance,
                'cliche_counter' => 0,
                'change_cliche_threshold' => fake()->numberBetween(5, 20),
                'notes' => fake()->boolean(30) ? 'ملاحظات على العميل: '.fake()->sentence() : null,
                'added_by_user' => $adminId,
                'created_by_user' => $adminId,
                'updated_by_user' => $adminId,
            ];

            $client = Client::create($data);
            $clients[] = $client;

            // ربط العميل باحتياج عشوائي
            $randomNeeds = fake()->randomElements($allNeeds, fake()->numberBetween(1, 4));
            foreach ($randomNeeds as $need) {
                DB::table('client_client_need')->insert([
                    'client_id' => $client->id,
                    'client_need_id' => $need->id,
                ]);
            }

            // ربط العميل بوسوم عشوائية
            $randomTags = fake()->randomElements($allTags, fake()->numberBetween(2, 8));
            foreach ($randomTags as $tag) {
                DB::table('client_tag')->insert([
                    'client_id' => $client->id,
                    'tag_id' => $tag->id,
                ]);
            }
        }

        $this->refs['clients'] = $clients;
        $this->refs['active_clients'] = collect($clients)->filter(fn ($c) => $c->status && ! $c->suspended_at)->values();
    }

    // ========================================================================
    // المرحلة 9: المصممين
    // ========================================================================

    private function createDesigners(): void
    {
        $designerCount = min($this->options['designers'], count($this->refs['designer_users']));
        $designers = [];
        $allCategories = $this->refs['categories'];

        $configs = [
            ['min' => 3, 'max' => 10, 'rate' => 4.5, 'shift' => 8, 'cats' => 2, 'score' => 4.5, 'designs' => 500],
            ['min' => 5, 'max' => 15, 'rate' => 3.8, 'shift' => 6, 'cats' => 2, 'score' => 3.8, 'designs' => 300],
            ['min' => 3, 'max' => 8, 'rate' => 5.0, 'shift' => 8, 'cats' => 1, 'score' => 5.0, 'designs' => 700],
            ['min' => 4, 'max' => 12, 'rate' => 4.2, 'shift' => 8, 'cats' => 2, 'score' => 4.0, 'designs' => 450],
            ['min' => 6, 'max' => 20, 'rate' => 3.5, 'shift' => 6, 'cats' => 1, 'score' => 3.5, 'designs' => 250],
            ['min' => 3, 'max' => 10, 'rate' => 4.8, 'shift' => 8, 'cats' => 2, 'score' => 4.8, 'designs' => 600],
            ['min' => 5, 'max' => 15, 'rate' => 3.2, 'shift' => 6, 'cats' => 2, 'score' => 3.0, 'designs' => 200],
            ['min' => 4, 'max' => 12, 'rate' => 4.0, 'shift' => 8, 'cats' => 2, 'score' => 4.2, 'designs' => 350],
            ['min' => 3, 'max' => 8, 'rate' => 4.6, 'shift' => 8, 'cats' => 1, 'score' => 4.6, 'designs' => 550],
            ['min' => 5, 'max' => 18, 'rate' => 3.0, 'shift' => 6, 'cats' => 2, 'score' => 2.5, 'designs' => 150],
            ['min' => 3, 'max' => 10, 'rate' => 4.3, 'shift' => 8, 'cats' => 1, 'score' => 4.3, 'designs' => 400],
            ['min' => 4, 'max' => 15, 'rate' => 3.7, 'shift' => 6, 'cats' => 2, 'score' => 3.7, 'designs' => 280],
        ];

        for ($i = 0; $i < $designerCount && $i < count($this->refs['designer_users']); $i++) {
            $user = $this->refs['designer_users'][$i];
            $cfg = $configs[$i % count($configs)];

            $designer = Designer::create([
                'user_id' => $user->id,
                'min_capacity' => $cfg['min'],
                'max_capacity' => $cfg['max'],
                'rate' => $cfg['rate'],
                'shift_hours' => $cfg['shift'],
                'discipline_score' => $cfg['score'],
                'amount_of_designs' => $cfg['designs'],
                'freepik_account' => fake()->boolean(50) ? 'freepik_'.str($user->name)->slug('_') : null,
                'pc_number' => 'PC-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
            ]);

            $designers[] = $designer;

            // ربط المصمم بفئة
            $randomCategories = fake()->randomElements($allCategories, $cfg['cats']);
            foreach ($randomCategories as $cat) {
                DB::table('category_designer')->insert([
                    'category_id' => $cat->id,
                    'designer_id' => $designer->id,
                ]);
            }
        }

        $this->refs['designers'] = $designers;
    }

    // ========================================================================
    // المرحلة 10: الاشتراكات
    // ========================================================================

    private function createContracts(): void
    {
        $contracts = [];
        $adminId = $this->refs['admin_user']->id;
        $activeClients = $this->refs['active_clients'];
        $currencyId = $this->refs['default_currency_id'];

        $billingCycles = ['monthly', 'yearly', 'weekly'];
        $paymentTypes = ['deferred', 'advance'];

        $contractIndex = 0;

        foreach ($activeClients as $client) {
            // لكل عميل نشط: اشتراك واحد ساري
            $billingCycle = $billingCycles[array_rand($billingCycles)];
            $paymentType = $paymentTypes[array_rand($paymentTypes)];

            $monthlyDesigns = match ($billingCycle) {
                'weekly' => fake()->numberBetween(4, 20) * 4,
                'yearly' => fake()->numberBetween(50, 500),
                default => fake()->numberBetween(10, 100),
            };

            $unitPrice = fake()->numberBetween(50, 200);
            $totalAmount = $monthlyDesigns * $unitPrice;

            $startDate = now()->subMonths(fake()->numberBetween(1, 12));
            $endDate = match ($billingCycle) {
                'weekly' => (clone $startDate)->addWeeks(fake()->numberBetween(4, 12)),
                'yearly' => (clone $startDate)->addYear(),
                default => (clone $startDate)->addMonth(),
            };

            // بعض الاشتراكات على وشك الانتهاء
            if ($contractIndex % 10 === 0) {
                $endDate = now()->addDays(fake()->numberBetween(1, 5));
            }

            // بعض الاشتراكات منتهية
            if ($contractIndex % 15 === 0) {
                $endDate = now()->subDays(fake()->numberBetween(1, 30));
            }

            $autoSuspension = fake()->boolean(30);
            $suspensionDays = $autoSuspension ? fake()->numberBetween(7, 30) : null;
            $gracePeriodDays = fake()->numberBetween(3, 15);
            $isUnderLawsuit = fake()->boolean(10);

            $contractData = [
                'client_id' => $client->id,
                'currency_id' => $currencyId,
                'status' => $endDate->isPast() ? 'expired' : 'active',
                'payment_type' => $paymentType,
                'billing_cycle' => $billingCycle,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'weekly_designs_count' => $billingCycle === 'weekly' ? $monthlyDesigns / 4 : 0,
                'monthly_designs_count' => $billingCycle === 'monthly' ? $monthlyDesigns : 0,
                'total_amount' => $totalAmount,
                'auto_suspension_enabled' => $autoSuspension,
                'suspension_period_days' => $suspensionDays,
                'grace_period_days' => $gracePeriodDays,
                'auto_renewal' => fake()->boolean(25),
                'is_under_lawsuit' => $isUnderLawsuit,
                'additional_designs_enabled' => fake()->boolean(40),
                'additional_design_price' => fake()->boolean(40) ? fake()->randomFloat(2, 100, 500) : null,
                'simple_requests_enabled' => fake()->boolean(30),
                'simple_request_price' => fake()->boolean(30) ? fake()->randomFloat(2, 50, 200) : null,
                'simple_requests_count' => fake()->numberBetween(0, 10),
                'additional_designs_count' => fake()->numberBetween(0, 5),
                'marketing_amount' => fake()->boolean(40) ? fake()->randomFloat(2, 200, 2000) : null,
                'legal_notes' => $isUnderLawsuit ? 'ملاحظات قانونية: '.fake()->sentence() : null,
                'created_by_user' => $adminId,
            ];

            $contract = Contract::create($contractData);
            $contracts[] = $contract;

            // بعض العملاء لديهم عقود منتهية سابقة
            if ($contractIndex % 5 === 0 && $contractIndex > 0) {
                $oldStart = (clone $startDate)->subMonths(6);
                $oldEnd = (clone $startDate)->subDay();

                $oldContract = Contract::create([
                    'client_id' => $client->id,
                    'currency_id' => $currencyId,
                    'status' => 'expired',
                    'payment_type' => $paymentType,
                    'billing_cycle' => $billingCycle,
                    'start_date' => $oldStart,
                    'end_date' => $oldEnd,
                    'weekly_designs_count' => $billingCycle === 'weekly' ? $monthlyDesigns / 4 : 0,
                    'monthly_designs_count' => $billingCycle === 'monthly' ? $monthlyDesigns : 0,
                    'total_amount' => $totalAmount,
                    'created_by_user' => $adminId,
                ]);
                $contracts[] = $oldContract;
            }

            // بعض العملاء معلقين
            if ($contractIndex % 20 === 0 && $contractIndex > 0) {
                $contract->update(['status' => 'suspended']);
            }

            $contractIndex++;
        }

        // عقود إضافية لعملاء غير نشطين (لضمان وجود سجل)
        $inactiveClients = collect($this->refs['clients'])->filter(fn ($c) => ! $c->status || $c->suspended_at)->take(5);
        foreach ($inactiveClients as $client) {
            $contract = Contract::create([
                'client_id' => $client->id,
                'currency_id' => $currencyId,
                'status' => 'expired',
                'payment_type' => 'deferred',
                'billing_cycle' => 'monthly',
                'start_date' => now()->subMonths(3),
                'end_date' => now()->subMonth(),
                'weekly_designs_count' => 0,
                'monthly_designs_count' => fake()->numberBetween(10, 30),
                'total_amount' => fake()->randomFloat(2, 1000, 5000),
                'created_by_user' => $adminId,
            ]);
            $contracts[] = $contract;
        }

        $this->refs['contracts'] = $contracts;
        $this->refs['active_contracts'] = collect($contracts)->filter(fn ($c) => $c->status === 'active')->values();
    }

    // ========================================================================
    // المرحلة 11: الفواتير
    // ========================================================================

    private function createInvoices(): void
    {
        $invoiceCount = $this->options['invoices'];
        $invoices = [];
        $adminId = $this->refs['admin_user']->id;
        $allClients = $this->refs['clients'];
        $activeContracts = $this->refs['active_contracts'];

        $statuses = [
            'draft' => 0.10,
            'posted' => 0.35,
            'paid' => 0.35,
            'cancelled' => 0.10,
            'overdue' => 0.10,
        ];

        for ($i = 0; $i < $invoiceCount; $i++) {
            // اختيار عميل عشوائي
            $client = $allClients[array_rand($allClients)];

            // اختيار اشتراك مرتبط بالعميل إن وجد
            $clientContracts = $activeContracts->filter(fn ($c) => $c->client_id === $client->id);
            $contract = $clientContracts->isNotEmpty() ? $clientContracts->random() : null;

            // اختيار الحالة
            $status = $this->weightedRandom($statuses);
            $issueDate = now()->subDays(fake()->numberBetween(1, 90));
            $dueDate = (clone $issueDate)->addDays(fake()->numberBetween(15, 45));

            // الفواتير المتأخرة: due_date في الماضي
            if ($status === 'overdue') {
                $status = 'posted'; // المخزّن في DB
                $dueDate = now()->subDays(fake()->numberBetween(5, 60));
            }

            $totalAmount = fake()->randomFloat(2, 1000, 50000);

            $invoice = Invoice::create([
                'client_id' => $client->id,
                'contract_id' => $contract?->id,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'total_amount' => $totalAmount,
                'status' => $status,
                'notes' => fake()->boolean(40) ? fake()->sentence() : null,
                'created_by_user' => $adminId,
            ]);

            $invoices[] = $invoice;

            // إنشاء أصناف الفاتورة
            $itemCount = fake()->numberBetween(1, 5);
            $calculatedTotal = 0;
            for ($j = 0; $j < $itemCount; $j++) {
                $qty = fake()->numberBetween(1, 10);
                $unitAmount = fake()->randomFloat(2, 100, 3000);
                $total = $qty * $unitAmount;
                $calculatedTotal += $total;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => 'خدمة تصميم - '.fake()->randomElement([
                        'تصميم إعلان',
                        'تصميم منشور',
                        'تصميم فيديو',
                        'تصميم شعار',
                        'حملة إعلانية',
                        'تصميم بروشور',
                        'إنفوجرافيك',
                        'تغطية فعالية',
                    ]),
                    'quantity' => $qty,
                    'unit_amount' => $unitAmount,
                    'total' => $total,
                ]);
            }

            // تحديث إجمالي الفاتورة
            $invoice->update(['total_amount' => $calculatedTotal]);
        }

        $this->refs['invoices'] = $invoices;
    }

    // ========================================================================
    // المرحلة 12: سندات القبض
    // ========================================================================

    private function createReceipts(): void
    {
        $receipts = [];
        $adminId = $this->refs['admin_user']->id;
        $allInvoices = collect($this->refs['invoices']);

        $paymentMethods = ['cash', 'jeeb', 'kuraimi', 'unified_network', 'jawali', 'transfer'];
        $paymentWeights = [0.30, 0.15, 0.20, 0.10, 0.05, 0.20];

        // سندات قبض للفواتير المدفوعة
        $paidInvoices = $allInvoices->where('status', 'paid');
        foreach ($paidInvoices as $invoice) {
            $paymentMethod = $this->weightedRandom(array_combine($paymentMethods, $paymentWeights));
            $receiptDate = (clone $invoice->due_date)->subDays(fake()->numberBetween(1, 15));

            // إذا كان تاريخ الاستحقاق في المستقبل، نضع تاريخ القبض قبل تاريخ الاستحقاق
            if ($receiptDate->isFuture()) {
                $receiptDate = now()->subDays(fake()->numberBetween(1, 10));
            }

            $receipt = Receipt::create([
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'amount' => $invoice->total_amount,
                'receipt_date' => $receiptDate,
                'payment_method' => $paymentMethod,
                'reference_number' => fake()->boolean(70) ? 'REF-'.fake()->unique()->numberBetween(1000, 9999) : null,
                'notes' => fake()->boolean(30) ? 'سند قبض عن '.fake()->sentence(3) : null,
                'created_by_user' => $adminId,
            ]);

            $receipts[] = $receipt;
        }

        // بعض الفواتير المصدّرة (posted) عليها دفعة جزئية
        $postedInvoices = $allInvoices->where('status', 'posted')->take(round($allInvoices->where('status', 'posted')->count() * 0.2));
        foreach ($postedInvoices as $invoice) {
            $partialAmount = $invoice->total_amount * fake()->randomFloat(2, 0.1, 0.5);
            $paymentMethod = $this->weightedRandom(array_combine($paymentMethods, $paymentWeights));

            $receipt = Receipt::create([
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'amount' => round($partialAmount, 2),
                'receipt_date' => now()->subDays(fake()->numberBetween(1, 20)),
                'payment_method' => $paymentMethod,
                'reference_number' => 'PARTIAL-'.fake()->unique()->numberBetween(1000, 9999),
                'notes' => 'دفعة جزئية',
                'created_by_user' => $adminId,
            ]);

            $receipts[] = $receipt;
        }

        $this->refs['receipts'] = $receipts;
    }

    // ========================================================================
    // المرحلة 13 أ: تعيينات ClientDesigner
    // ========================================================================

    private function createClientDesignerAssignments(): void
    {
        $activeClients = $this->refs['active_clients'];
        $designers = $this->refs['designers'];
        $activeContracts = $this->refs['active_contracts'];

        $currentWeekStart = now()->startOfWeek();
        $created = [];

        foreach ($activeClients as $client) {
            // اختيار مصمم عشوائي
            $designer = $designers[array_rand($designers)];

            // البحث عن اشتراك نشط للعميل
            $contract = $activeContracts->firstWhere('client_id', $client->id);

            try {
                $assignment = ClientDesigner::create([
                    'client_id' => $client->id,
                    'designer_id' => $designer->id,
                    'contract_id' => $contract?->id,
                    'week_start_date' => $currentWeekStart,
                ]);
                $created[] = $assignment;
            } catch (\Exception $e) {
                // تجاهل الخطأ (قد يكون due to unique constraint)
                continue;
            }
        }

        $this->refs['client_designer_assignments'] = $created;
    }

    // ========================================================================
    // المرحلة 13 ب: توزيعات ClientTagDistribution
    // ========================================================================

    private function createClientTagDistributions(): void
    {
        $assignments = $this->refs['client_designer_assignments'] ?? [];
        $allTags = $this->refs['tags'];
        $adminId = $this->refs['admin_user']->id;

        $distributionStatuses = ['pending', 'in_progress', 'completed', 'changes_requested', 'reviewing', 'sending'];
        $statusWeights = [0.30, 0.25, 0.20, 0.10, 0.10, 0.05];

        $distributions = [];

        foreach ($assignments as $assignment) {
            // لكل تعيين: 2-5 توزيعات
            $count = fake()->numberBetween(2, 5);
            $randomTags = fake()->randomElements($allTags, min($count, count($allTags)));

            foreach ($randomTags as $tag) {
                $status = $this->weightedRandom(array_combine($distributionStatuses, $statusWeights));
                $completedAt = in_array($status, ['completed', 'reviewing', 'sending']) ? now()->subHours(fake()->numberBetween(1, 72)) : null;

                $data = [
                    'client_designer_id' => $assignment->id,
                    'tag_id' => $tag->id,
                    'distribution_date' => now()->subDays(fake()->numberBetween(0, 7)),
                    'scheduled_sending_at' => fake()->boolean(30) ? now()->addDays(fake()->numberBetween(1, 7)) : null,
                    'status' => $status,
                    'designer_notes' => fake()->boolean(40) && $status === 'completed' ? fake()->sentence() : null,
                    'reviewer_feedback' => fake()->boolean(30) && $status === 'changes_requested' ? fake()->sentence() : null,
                    'completed_at' => $completedAt,
                    'reviewer_id' => $status === 'reviewing' || $status === 'changes_requested' ? $this->getRandomReviewerId() : null,
                    'sender_id' => $adminId,
                ];

                try {
                    $distribution = ClientTagDistribution::create($data);
                    $distributions[] = $distribution;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        $this->refs['distributions'] = $distributions;
    }

    // ========================================================================
    // المرحلة 13 ج: مهام التصميم
    // ========================================================================

    private function createDesignTasks(): void
    {
        $designers = $this->refs['designers'];
        $adminId = $this->refs['admin_user']->id;
        $allClients = $this->refs['clients'];

        $statuses = DesignTaskStatus::cases();
        $statusWeights = [
            'pending' => 0.35,
            'in_review' => 0.25,
            'needs_revision' => 0.15,
            'approved' => 0.25,
        ];

        $priorities = DesignTaskPriority::cases();
        $priorityWeights = [
            'high' => 0.20,
            'medium' => 0.50,
            'low' => 0.30,
        ];

        $tasks = [];
        $taskCount = round($this->options['invoices'] * 1.0); // نفس عدد الفواتير

        for ($i = 0; $i < $taskCount; $i++) {
            $designer = $designers[array_rand($designers)];
            $client = $allClients[array_rand($allClients)];
            $statusStr = $this->weightedRandom($statusWeights);
            $priorityStr = $this->weightedRandom($priorityWeights);
            $status = DesignTaskStatus::tryFrom($statusStr) ?? DesignTaskStatus::Pending;
            $priority = DesignTaskPriority::tryFrom($priorityStr) ?? DesignTaskPriority::Medium;
            $isExtra = fake()->boolean(10);
            $subscribed = $client->status && ! $client->suspended_at;

            $data = [
                'designer_id' => $designer->id,
                'assigner_id' => $adminId,
                'is_subscribed_client' => $subscribed,
                'client_id' => $subscribed ? $client->id : null,
                'client_name' => $subscribed ? $client->company : fake()->company(),
                'description' => fake()->sentence(10),
                'priority' => $priority,
                'status' => $status,
                'is_extra' => $isExtra,
                'amount' => $isExtra ? fake()->randomFloat(2, 200, 2000) : null,
                'deduct_from_balance' => $isExtra ? fake()->boolean(30) : false,
                'scheduled_at' => fake()->boolean(30) ? now()->addDays(fake()->numberBetween(1, 7)) : null,
                'submitted_at' => in_array($statusStr, ['in_review', 'needs_revision', 'approved'])
                    ? now()->subHours(fake()->numberBetween(1, 48))
                    : null,
                'revision_notes' => $statusStr === 'needs_revision' ? fake()->sentence() : null,
            ];

            try {
                $task = DesignTask::create($data);
                $tasks[] = $task;
            } catch (\Exception $e) {
                continue;
            }
        }

        $this->refs['design_tasks'] = $tasks;
    }

    // ========================================================================
    // المرحلة 13 د: الطلبات
    // ========================================================================

    private function createOrders(): void
    {
        $designers = $this->refs['designers'];
        $adminId = $this->refs['admin_user']->id;
        $allClients = $this->refs['clients'];

        $statusWeights = [
            'pending' => 0.30,
            'in_progress' => 0.25,
            'in_review' => 0.20,
            'completed' => 0.15,
            'cancelled' => 0.10,
        ];

        $orders = [];
        $orderCount = 50;

        for ($i = 0; $i < $orderCount; $i++) {
            $designer = $designers[array_rand($designers)];
            $client = $allClients[array_rand($allClients)];
            $statusStr = $this->weightedRandom($statusWeights);
            $status = OrderStatus::tryFrom($statusStr) ?? OrderStatus::Pending;

            $order = Order::create([
                'client_name' => $client->company,
                'description' => fake()->boolean(70) ? fake()->sentence(8) : null,
                'designer_id' => $designer->id,
                'assigner_id' => $adminId,
                'status' => $status,
                'client_id' => $client->id,
            ]);

            $orders[] = $order;
        }

        $this->refs['orders'] = $orders;
    }

    // ========================================================================
    // المرحلة 14 أ: منصات التواصل الاجتماعي
    // ========================================================================

    private function createSocialMedia(): void
    {
        $adminId = $this->refs['admin_user']->id;

        foreach (ArabicDataProvider::$socialMediaNames as $name) {
            SocialMedia::firstOrCreate(
                ['name' => $name],
                ['added_by_user' => $adminId]
            );
        }
    }

    // ========================================================================
    // المرحلة 14 ب: العهد
    // ========================================================================

    private function createCustodyItems(): void
    {
        $adminId = $this->refs['admin_user']->id;
        $allUsers = collect($this->refs['users']);

        foreach (ArabicDataProvider::$custodyNames as $name) {
            $user = $allUsers->random();

            Custody::create([
                'name' => $name,
                'user_id' => $user->id,
                'added_by_user' => $adminId,
            ]);
        }
    }

    // ========================================================================
    // المرحلة 14 ج: الشكاوى
    // ========================================================================

    private function createComplaints(): void
    {
        $allClients = $this->refs['clients'];
        $adminId = $this->refs['admin_user']->id;
        $allUsers = collect($this->refs['users']);

        $complaints = [];
        $complaintCount = 25;
        $descriptions = ArabicDataProvider::$complaintDescriptions;

        for ($i = 0; $i < $complaintCount; $i++) {
            $client = $allClients[array_rand($allClients)];
            $isResolved = $i < 10; // أول 10 محلولة، الباقي جديدة
            $resolvedBy = $isResolved ? $adminId : null;

            $complaint = Complaint::create([
                'client_id' => $client->id,
                'description' => $descriptions[$i % count($descriptions)],
                'status' => $isResolved ? ComplaintStatus::Resolved : ComplaintStatus::New,
                'added_by_user' => $adminId,
                'resolved_by_user' => $resolvedBy,
            ]);

            $complaints[] = $complaint;
        }

        $this->refs['complaints'] = $complaints;
    }

    // ========================================================================
    // المرحلة 14 د: الأفكار
    // ========================================================================

    private function createIdeas(): void
    {
        $adminId = $this->refs['admin_user']->id;
        $allClients = $this->refs['clients'];
        $allLocations = $this->refs['locations'];
        $allTags = $this->refs['tags'];

        $ideas = [];
        $ideaCount = count(ArabicDataProvider::$ideaNames);

        foreach (ArabicDataProvider::$ideaNames as $i => $name) {
            $isVisible = $i < 25; // أول 25 ظاهرة في المولد
            $content = ArabicDataProvider::$ideaContents[$i % count(ArabicDataProvider::$ideaContents)];
            $scheduledAt = ($i >= 30 && $i < 40) ? now()->addDays(fake()->numberBetween(1, 30)) : null;

            $idea = Idea::create([
                'name' => $name,
                'content' => $content,
                'description' => fake()->boolean(60) ? fake()->paragraph() : null,
                'repeat_for_clients' => fake()->boolean(20),
                'scheduled_at' => $scheduledAt,
                'is_visible_in_generator' => $isVisible,
                'added_by_user' => $adminId,
            ]);

            $ideas[] = $idea;

            // ربط الفكرة بموقع عشوائي
            $randomLocation = $allLocations[array_rand($allLocations)];
            DB::table('location_idea')->insert([
                'idea_id' => $idea->id,
                'location_id' => $randomLocation->id,
            ]);

            // ربط الفكرة بوسوم عشوائية
            $randomTags = fake()->randomElements($allTags, fake()->numberBetween(1, 4));
            foreach ($randomTags as $tag) {
                DB::table('tag_idea')->insert([
                    'idea_id' => $idea->id,
                    'tag_id' => $tag->id,
                ]);
            }

            // ربط الفكرة بعملاء عشوائيين (لبعض الأفكار)
            if (fake()->boolean(30)) {
                $randomClients = fake()->randomElements($allClients, fake()->numberBetween(1, 5));
                foreach ($randomClients as $client) {
                    DB::table('client_idea')->insert([
                        'idea_id' => $idea->id,
                        'client_id' => $client->id,
                    ]);
                }
            }
        }

        // حظر أفكار لبعض العملاء (5 أفكار)
        for ($i = 0; $i < 5 && $i < count($ideas); $i++) {
            $client = $allClients[array_rand($allClients)];
            DB::table('idea_client_blocks')->insert([
                'idea_id' => $ideas[$i]->id,
                'client_id' => $client->id,
            ]);
        }

        $this->refs['ideas'] = $ideas;
    }

    // ========================================================================
    // المرحلة 14 هـ: جداول الربط الإضافية
    // ========================================================================

    private function createPivotData(): void
    {
        $allCategories = $this->refs['categories'];
        $allTagGroups = $this->refs['tag_groups'];
        $allClientNeeds = $this->refs['client_needs'];

        // ربط مجموعات الوسوم بالفئات
        foreach ($allTagGroups as $group) {
            $randomCategories = fake()->randomElements($allCategories, fake()->numberBetween(1, 3));
            foreach ($randomCategories as $cat) {
                DB::table('category_tag_group')->insert([
                    'category_id' => $cat->id,
                    'tag_group_id' => $group->id,
                ]);
            }
        }

        // ربط احتياجات العملاء بالفئات
        foreach ($allClientNeeds as $need) {
            $randomCategories = fake()->randomElements($allCategories, fake()->numberBetween(1, 3));
            foreach ($randomCategories as $cat) {
                DB::table('category_client_need')->insert([
                    'category_id' => $cat->id,
                    'client_need_id' => $need->id,
                ]);
            }
        }

        // ربط احتياجات العملاء بالوسوم
        $allTags = $this->refs['tags'];
        foreach ($allClientNeeds as $need) {
            $randomTags = fake()->randomElements($allTags, fake()->numberBetween(2, 5));
            foreach ($randomTags as $tag) {
                DB::table('client_need_tags_group')->insert([
                    'client_need_id' => $need->id,
                    'tag_id' => $tag->id,
                ]);
            }
        }
    }

    // ========================================================================
    // المرحلة 14 و: قوالب العملاء
    // ========================================================================

    private function createClientTemplates(): void
    {
        $activeClients = $this->refs['active_clients'];
        $templateTypes = ClientTemplateType::cases();
        $templates = [];

        foreach ($activeClients as $client) {
            // لكل عميل نشط: 1-3 قوالب
            $count = fake()->numberBetween(1, 3);
            $randomTypes = fake()->randomElements($templateTypes, $count);

            foreach ($randomTypes as $type) {
                try {
                    $template = ClientTemplate::create([
                        'client_id' => $client->id,
                        'type' => $type,
                        'file' => null, // لا توجد ملفات فعلية
                        'local_path' => null,
                    ]);
                    $templates[] = $template;
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        $this->refs['templates'] = $templates;
    }

    // ========================================================================
    // دوال مساعدة
    // ========================================================================

    /**
     * اختيار عشوائي موزون.
     */
    private function weightedRandom(array $weightedValues): mixed
    {
        $totalWeight = array_sum($weightedValues);
        $rand = fake()->randomFloat(2, 0, $totalWeight);
        $cumulative = 0;

        foreach ($weightedValues as $value => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $value;
            }
        }

        return array_key_first($weightedValues);
    }

    /**
     * الحصول على ID مراجع عشوائي.
     */
    private function getRandomReviewerId(): ?int
    {
        $users = $this->refs['users'] ?? [];
        $reviewerKeys = array_filter(array_keys($users), fn ($k) => str_starts_with((string) $k, 'rev_'));
        if (empty($reviewerKeys)) {
            return $this->refs['admin_user']->id;
        }

        return $users[$reviewerKeys[array_rand($reviewerKeys)]]->id;
    }
}
