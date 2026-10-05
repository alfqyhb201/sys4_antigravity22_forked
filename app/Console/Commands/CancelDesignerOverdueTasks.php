<?php

namespace App\Console\Commands;

use App\Filament\Enums\DesignTaskStatus;
use App\Models\ClientTagDistribution;
use App\Models\Designer;
use App\Models\DesignTask;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CancelDesignerOverdueTasks extends Command
{
    protected $signature = 'designers:cancel-overdue
        {--designer=* : اسم المصمم (بحث جزئي) أو رقمه، ويمكن تكراره}
        {--before= : إلغاء المهام ذات التاريخ الأقدم من هذا التاريخ (الافتراضي: بداية الشهر الحالي)}
        {--from= : إلغاء المهام ابتداءً من هذا التاريخ (اختياري)}
        {--include-design-tasks : حذف (Soft Delete) مهام التصميم المعلقة المتأخرة أيضاً}
        {--dry-run : عرض ما سيتم دون تنفيذ أي تغيير}';

    protected $description = 'إلغاء المهام المتأخرة غير المكتملة لمصممين محددين قبل تاريخ معين';

    public function handle(): int
    {
        $designerInputs = array_filter((array) $this->option('designer'));

        if ($designerInputs === []) {
            $this->error('يجب تحديد مصمم واحد على الأقل عبر --designer');

            return self::FAILURE;
        }

        $before = $this->option('before')
            ? Carbon::parse($this->option('before'))->startOfDay()
            : now()->startOfMonth();
        $from = $this->option('from') ? Carbon::parse($this->option('from'))->startOfDay() : null;
        $dryRun = (bool) $this->option('dry-run');

        $designers = collect();
        foreach ($designerInputs as $input) {
            $matches = Designer::query()
                ->with('user')
                ->when(
                    is_numeric($input),
                    fn ($q) => $q->where('id', $input),
                    fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$input}%"))
                )
                ->get();

            if ($matches->count() !== 1) {
                $this->error("المدخل \"{$input}\" يطابق {$matches->count()} مصمم؛ يجب أن يطابق مصمماً واحداً بالضبط.");

                return self::FAILURE;
            }

            $designers->push($matches->first());
        }

        $this->info('الفترة: '.($from ? $from->toDateString() : 'البداية').' → قبل '.$before->toDateString());
        $this->info($dryRun ? 'وضع المعاينة (لن يتم تغيير أي شيء)' : 'وضع التنفيذ');

        $total = 0;
        foreach ($designers->unique('id') as $designer) {
            $name = $designer->user?->name ?? "#{$designer->id}";

            $distributions = ClientTagDistribution::query()
                ->whereHas('clientDesigner', fn ($q) => $q->where('designer_id', $designer->id))
                ->whereIn('status', ['pending', 'in_progress', 'changes_requested'])
                ->where('distribution_date', '<', $before->toDateString())
                ->when($from, fn ($q) => $q->where('distribution_date', '>=', $from->toDateString()))
                ->get();

            $designTasks = collect();
            if ($this->option('include-design-tasks')) {
                $designTasks = DesignTask::query()
                    ->where('designer_id', $designer->id)
                    ->whereIn('status', [DesignTaskStatus::Pending->value, DesignTaskStatus::NeedsRevision->value])
                    ->whereRaw('COALESCE(scheduled_at, created_at) < ?', [$before->toDateTimeString()])
                    ->when($from, fn ($q) => $q->whereRaw('COALESCE(scheduled_at, created_at) >= ?', [$from->toDateTimeString()]))
                    ->get();
            }

            $this->line("المصمم: {$name} — مهام يومية: {$distributions->count()} | مهام تصميم: {$designTasks->count()}");

            if (! $dryRun) {
                $distributions->each(fn (ClientTagDistribution $d) => $d->update(['status' => 'cancelled']));
                $designTasks->each(fn (DesignTask $t) => $t->delete());
            }

            $total += $distributions->count() + $designTasks->count();
        }

        $this->info(($dryRun ? 'سيتم إلغاء ' : 'تم إلغاء ')."{$total} مهمة.");

        return self::SUCCESS;
    }
}
