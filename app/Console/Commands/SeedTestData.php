<?php

namespace App\Console\Commands;

use Database\Seeders\TestDataSeeder;
use Illuminate\Console\Command;

class SeedTestData extends Command
{
    /**
     * اسم وسيط الأمر.
     *
     * @var string
     */
    protected $signature = 'db:seed-test-data
                            {--clients=100 : عدد العملاء المراد إنشاؤهم}
                            {--designers=12 : عدد المصممين المراد إنشاؤهم}
                            {--invoices=200 : عدد الفواتير المراد إنشاؤها}
                            {--fresh : مسح البيانات الموجودة قبل الإنشاء}';

    /**
     * وصف الأمر.
     *
     * @var string
     */
    protected $description = 'تجهيز بيئة بيانات اختبارية واقعية للنظام';

    /**
     * تنفيذ الأمر.
     */
    public function handle(): int
    {
        $fresh = $this->option('fresh');
        $noInteraction = $this->option('no-interaction');

        $clients = (int) $this->option('clients');
        $designers = (int) $this->option('designers');
        $invoices = (int) $this->option('invoices');

        // عرض الملخص
        $this->info('🧪 بيئة بيانات اختبارية - نظام TrueERP');
        $this->newLine();
        $this->table(
            ['المعامل', 'القيمة'],
            [
                ['عدد العملاء', number_format($clients)],
                ['عدد المصممين', number_format($designers)],
                ['عدد الفواتير', number_format($invoices)],
                ['مسح البيانات السابقة', $fresh ? '✅ نعم' : '❌ لا'],
            ]
        );

        // التأكيد
        if (! $noInteraction) {
            $this->newLine();
            if (! $this->confirm('هل تريد المتابعة؟', true)) {
                $this->warn('❌ تم إلغاء الأمر.');

                return Command::INVALID;
            }
        }

        // التحقق من وجود المستخدم الأساسي، وإنشاءه مع الأدوار إن لم يكن موجودًا
        $adminUser = \App\Models\User::where('email', 'admin@admin.com')->first();
        if (! $adminUser) {
            $this->info('🔄 تشغيل السيدرات الأساسية...');
            $this->call('db:seed', [
                '--class' => 'Database\Seeders\UsersTableSeeder',
                '--no-interaction' => true,
            ]);
            $this->call('db:seed', [
                '--class' => 'Database\Seeders\ContentSeeder',
                '--no-interaction' => true,
            ]);
            $this->call('db:seed', [
                '--class' => 'Database\Seeders\RolesAndPermissionsSeeder',
                '--no-interaction' => true,
            ]);
        }

        // تشغيل سيدر بيانات الاختبار
        $this->info('🚀 جاري تجهيز بيانات الاختبار...');

        $seeder = app(TestDataSeeder::class);
        $seeder->run([
            'clients' => $clients,
            'designers' => $designers,
            'invoices' => $invoices,
            'fresh' => $fresh,
        ]);

        $this->newLine();
        $this->info('✅ تم تجهيز بيئة البيانات الاختبارية بنجاح!');
        $this->warn('🔑 كلمة مرور جميع المستخدمين: 123321');
        $this->warn('👤 المستخدم الأساسي: admin@admin.com');

        return Command::SUCCESS;
    }
}
