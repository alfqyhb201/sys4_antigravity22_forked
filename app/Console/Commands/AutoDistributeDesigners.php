<?php

namespace App\Console\Commands;

use App\Services\DesignerDistributionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * أمر Artisan لتوزيع المصممين على العملاء بشكل تلقائي.
 *
 * يوفر هذا الأمر واجهة سطر أوامر لاستخدام `DesignerDistributionService`
 * لتوزيع المصممين، مع خيارات لمسح التوزيع الحالي وعرض تقرير مفصل.
 */
class AutoDistributeDesigners extends Command
{
    /**
     * اسم وتوقيع أمر Artisan.
     *
     * @var string
     */
    protected $signature = 'designers:auto-distribute 
                            {week_start? : تاريخ بداية الأسبوع (Y-m-d), افتراضياً الأسبوع الحالي}
                            {--clear : مسح التوزيع الحالي قبل التوزيع الجديد}
                            {--report : عرض تقرير مفصل بعد التوزيع}';

    /**
     * وصف أمر Artisan.
     *
     * @var string
     */
    protected $description = 'توزيع العملاء على المصممين بشكل تلقائي باستخدام الخوارزمية الذكية';

    /**
     * خدمة توزيع المصممين.
     */
    protected DesignerDistributionService $service;

    /**
     * إنشاء نسخة جديدة من الأمر.
     *
     * @return void
     */
    public function __construct(DesignerDistributionService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    /**
     * تنفيذ أمر Artisan.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🚀 بدء عملية التوزيع التلقائي...');
        $this->newLine();

        // تحديد تاريخ بداية الأسبوع
        $weekStart = $this->argument('week_start')
            ? Carbon::parse($this->argument('week_start'))->startOfWeek()->format('Y-m-d')
            : Carbon::now()->startOfWeek()->format('Y-m-d');

        $this->info("📅 الأسبوع المحدد: {$weekStart}");
        $this->newLine();

        // مسح التوزيع الحالي إذا طُلب ذلك
        if ($this->option('clear')) {
            if ($this->confirm('هل أنت متأكد من مسح التوزيع الحالي؟', true)) {
                $this->clearDistribution($weekStart);
            }
        }

        // تنفيذ التوزيع
        $this->info('⚙️  جاري التوزيع...');

        $progressBar = $this->output->createProgressBar();
        $progressBar->start();

        $result = $this->service->autoDistribute($weekStart);

        $progressBar->finish();
        $this->newLine(2);

        // عرض النتائج
        $this->displayResults($result);

        // عرض التقرير المفصل إذا طُلب ذلك
        if ($this->option('report')) {
            $this->newLine();
            $this->displayDetailedReport($weekStart);
        }

        return $result['success'] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * يقوم بمسح التوزيع الحالي لأسبوع معين.
     */
    protected function clearDistribution(string $weekStart): void
    {
        $this->warn('🗑️  جاري مسح التوزيع الحالي...');

        $deleted = \App\Models\ClientDesigner::where('week_start_date', $weekStart)->delete();

        $this->info("✅ تم حذف {$deleted} تعيين");
        $this->newLine();
    }

    /**
     * يقوم بعرض نتائج التوزيع.
     */
    protected function displayResults(array $result): void
    {
        if ($result['success']) {
            $this->components->success('✅ نجح التوزيع!');
        } else {
            $this->components->error('❌ فشل التوزيع!');
        }

        $this->newLine();
        $this->info($result['message']);
        $this->newLine();

        // جدول الإحصائيات
        $this->table(
            ['المؤشر', 'القيمة'],
            [
                ['إجمالي العملاء', $result['total'] ?? 0],
                ['تم التوزيع بنجاح', $result['distributed']],
                ['فشل التوزيع', $result['failed']],
                ['نسبة النجاح', $this->calculateSuccessRate($result).'%'],
            ]
        );

        // إحصائيات المصممين
        if (isset($result['statistics']['designers']) && ! empty($result['statistics']['designers'])) {
            $this->newLine();
            $this->info('📊 توزيع المصممين:');
            $this->newLine();

            $designerData = [];
            foreach ($result['statistics']['designers'] as $designerId => $stats) {
                $designerData[] = [
                    $stats['designer_name'],
                    $stats['clients_count'],
                    $stats['total_designs'],
                ];
            }

            $this->table(
                ['المصمم', 'عدد العملاء', 'إجمالي التصاميم'],
                $designerData
            );
        }

        // عرض الفشل إذا وُجد
        if ($result['failed'] > 0 && isset($result['details']['failed'])) {
            $this->newLine();
            $this->warn('⚠️  العملاء الذين فشل توزيعهم:');
            $this->newLine();

            $failedData = [];
            foreach ($result['details']['failed'] as $failed) {
                $failedData[] = [
                    $failed['client_name'],
                    $failed['reason'],
                ];
            }

            $this->table(
                ['العميل', 'السبب'],
                $failedData
            );
        }
    }

    /**
     * يقوم بعرض تقرير مفصل للتوزيع.
     */
    protected function displayDetailedReport(string $weekStart): void
    {
        $this->info('📋 التقرير المفصل:');
        $this->newLine();

        $report = $this->service->getDistributionReport($weekStart);

        $this->info("الأسبوع: {$report['week_start']}");
        $this->info("إجمالي التعيينات: {$report['total_assignments']}");
        $this->newLine();

        foreach ($report['designers'] as $designerData) {
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("👤 المصمم: {$designerData['name']}");
            $this->line("   عدد العملاء: {$designerData['total_clients']}");
            $this->line("   إجمالي التصاميم: {$designerData['total_designs']}");
            $this->newLine();

            if (! empty($designerData['clients'])) {
                $this->line('   العملاء:');
                foreach ($designerData['clients'] as $client) {
                    $this->line("   • {$client['name']} ({$client['designs_count']} تصميم)");
                }
            }
            $this->newLine();
        }
    }

    /**
     * يقوم بحساب نسبة نجاح التوزيع.
     */
    protected function calculateSuccessRate(array $result): string
    {
        $total = $result['total'] ?? 0;
        if ($total == 0) {
            return '0.00';
        }

        $distributed = $result['distributed'] ?? 0;
        $rate = ($distributed / $total) * 100;

        return number_format($rate, 2);
    }
}
