<?php

namespace App\Filament\Widgets;

use App\Services\WeeklyDistributionAuditService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * ودجة التنبيه التفاعلية للتوزيع الأسبوعي في لوحة التحكم.
 *
 * ترصد وتكشف العملاء النشطين غير المعينين لمصمم، أو المعينين
 * ولم تُوزّع تاقاتهم بعد، مع إتاحة نافذة منبثقة للمعالجة الفورية.
 */
class WeeklyDistributionAlertWidget extends Widget
{
    protected static string $view = 'filament.widgets.weekly-distribution-alert-widget';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public ?string $selectedWeek = null;

    public string $activeModalTab = 'unassigned';

    /**
     * مصفوفة المصمم المختار لكل عقد: [contract_id => designer_id].
     *
     * @var array<int, int|string>
     */
    public array $designerSelections = [];

    public static function canView(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole(['admin', 'supervisor']) || $user->can('view_designer_distribution');
    }

    public function mount(): void
    {
        $this->selectedWeek = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->initDesignerSelections();
    }

    public function initDesignerSelections(): void
    {
        $service = app(WeeklyDistributionAuditService::class);
        $unassigned = $service->getUnassignedContracts($this->selectedWeek);
        $availableDesigners = $service->getAvailableDesignersWithCapacity($this->selectedWeek);
        $firstAvailableId = $availableDesigners->first()['id'] ?? null;

        foreach ($unassigned as $item) {
            if (! isset($this->designerSelections[$item['contract_id']]) && $firstAvailableId) {
                $this->designerSelections[$item['contract_id']] = $firstAvailableId;
            }
        }
    }

    /**
     * جلب ملخص التدقيق.
     *
     * @return array{week_start: string, week_end: string, unassigned_count: int, missing_tags_count: int, total_issues: int, is_fully_distributed: bool}
     */
    public function getAuditSummaryProperty(): array
    {
        return app(WeeklyDistributionAuditService::class)->getAuditSummary($this->selectedWeek);
    }

    /**
     * جلب العقود غير الموزعة.
     *
     * @return Collection<int, array{contract_id: int, client_id: int, client_name: string, category_name: string, weekly_designs_count: int, billing_cycle: string, start_date: ?string, reason: string}>
     */
    public function getUnassignedContractsProperty(): Collection
    {
        return app(WeeklyDistributionAuditService::class)->getUnassignedContracts($this->selectedWeek);
    }

    /**
     * جلب العملاء ذوي التاقات الناقصة.
     *
     * @return Collection<int, array{client_designer_id: int, contract_id: ?int, client_id: int, client_name: string, category_name: string, designer_id: int, designer_name: string, required_designs: int, distributed_tags: int, missing_count: int}>
     */
    public function getClientsWithMissingTagsProperty(): Collection
    {
        return app(WeeklyDistributionAuditService::class)->getClientsWithMissingTags($this->selectedWeek);
    }

    /**
     * جلب المصممين المتاحين مع السعة.
     *
     * @return Collection<int, array{id: int, name: string, available_capacity: int, max_capacity: int, current_load: int, label: string}>
     */
    public function getAvailableDesignersProperty(): Collection
    {
        return app(WeeklyDistributionAuditService::class)->getAvailableDesignersWithCapacity($this->selectedWeek);
    }

    /**
     * فتح نافذة فحص ومعالجة التوزيع.
     */
    public function openAuditModal(?string $tab = null): void
    {
        if ($tab) {
            $this->activeModalTab = $tab;
        } else {
            // التبديل تلقائياً للتبويب الذي يحتوي على مشاكل
            $summary = $this->audit_summary;
            if ($summary['unassigned_count'] > 0) {
                $this->activeModalTab = 'unassigned';
            } elseif ($summary['missing_tags_count'] > 0) {
                $this->activeModalTab = 'missing_tags';
            }
        }

        $this->initDesignerSelections();
        $this->dispatch('open-modal', id: 'weekly-distribution-audit-modal');
    }

    /**
     * إغلاق نافذة المعالجة.
     */
    public function closeAuditModal(): void
    {
        $this->dispatch('close-modal', id: 'weekly-distribution-audit-modal');
    }

    /**
     * تعيين اشتراك معين لمصمم مباشر.
     */
    public function assignContract(int $contractId): void
    {
        $designerId = $this->designerSelections[$contractId] ?? null;

        if (! $designerId) {
            Notification::make()
                ->title('تنبيه')
                ->body('يرجى اختيار مصمم أولاً.')
                ->warning()
                ->send();

            return;
        }

        try {
            $service = app(WeeklyDistributionAuditService::class);
            $service->assignContractToDesigner($contractId, (int) $designerId, $this->selectedWeek);

            unset($this->designerSelections[$contractId]);

            Notification::make()
                ->title('تم التعيين بنجاح')
                ->body('تم تعيين العميل للمصمم المختار بنجاح لهذا الأسبوع.')
                ->success()
                ->send();

            $this->initDesignerSelections();
        } catch (\Exception $e) {
            Notification::make()
                ->title('خطأ أثناء التعيين')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * تشغيل خوارزمية التوزيع التلقائي للمتبقين.
     */
    public function autoDistributeRemaining(): void
    {
        try {
            $service = app(WeeklyDistributionAuditService::class);
            $result = $service->autoDistributeUnassigned($this->selectedWeek);

            if ($result['success']) {
                $distributed = $result['distributed'] ?? 0;
                $failed = $result['failed'] ?? 0;

                if ($failed === 0) {
                    Notification::make()
                        ->title('نجاح التوزيع التلقائي')
                        ->body("تم توزيع {$distributed} اشتراك/اشتراكات بنجاح.")
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('توزيع جزئي')
                        ->body("تم توزيع {$distributed} اشتراك، وتبقى {$failed} بسبب عجز في السعة.")
                        ->warning()
                        ->send();
                }
            } else {
                Notification::make()
                    ->title('فشل التوزيع')
                    ->body($result['message'])
                    ->danger()
                    ->send();
            }

            $this->initDesignerSelections();
        } catch (\Exception $e) {
            Notification::make()
                ->title('حدث خطأ')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * توزيع تاقات لعميل واحد محدد.
     */
    public function distributeTagsForClient(int $clientDesignerId): void
    {
        try {
            $service = app(WeeklyDistributionAuditService::class);
            $count = $service->distributeTagsForAssignment($clientDesignerId, $this->selectedWeek);

            if ($count > 0) {
                Notification::make()
                    ->title('تم توزيع التاقات بنجاح')
                    ->body("تم إسناد وتوزيع {$count} تاق لهذا العميل.")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('تنبيه')
                    ->body('لم يتم العثور على تاقات إضافية مؤهلة للتوزيع لهذا العميل.')
                    ->warning()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('خطأ أثناء توزيع التاقات')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * توزيع التاقات لجميع العملاء ذوي التاقات الناقصة دفعة واحدة.
     */
    public function distributeTagsForAll(): void
    {
        try {
            $service = app(WeeklyDistributionAuditService::class);
            $count = $service->distributeTagsForAllMissing($this->selectedWeek);

            if ($count > 0) {
                Notification::make()
                    ->title('تم توزيع تاقات العملاء بنجاح')
                    ->body("تم إسناد وتوزيع {$count} تاق إجمالاً على العملاء الناقصين.")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('تنبيه')
                    ->body('لا توجد تاقات إضافية يمكن توزيعها حالياً.')
                    ->info()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('حدث خطأ أثناء التوزيع الجماعي')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
