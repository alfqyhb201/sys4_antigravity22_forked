<?php

namespace App\Observers;

use App\Filament\Enums\DesignTaskStatus;
use App\Models\DesignTask;
use Filament\Notifications\Notification;

class DesignTaskObserver
{
    /**
     * إرسال إشعار للمصمم عند إنشاء مهمة جانبية جديدة له.
     */
    public function created(DesignTask $designTask): void
    {
        $designerUser = $designTask->designer?->user;
        if ($designerUser) {
            $clientName = $designTask->display_client_name;
            $priorityLabel = $designTask->priority?->getLabel() ?? '';
            $priorityText = $priorityLabel ? " [الأهمية: {$priorityLabel}]" : '';

            Notification::make()
                ->title('مهمة جديدة 🎨')
                ->body("تم إسناد مهمة جديدة لك: {$clientName}{$priorityText}")
                ->icon('heroicon-o-clipboard-document-list')
                ->iconColor('info')
                ->info()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('عرض لوحة المصمم')
                        ->url('/admin/designer-dashboard'),
                ])
                ->sendToDatabase($designerUser, isEventDispatched: true);
        }
    }

    public function saved(DesignTask $designTask): void
    {
        // Notify designer if status changed to NeedsRevision
        if ($designTask->wasChanged('status') && $designTask->status === DesignTaskStatus::NeedsRevision) {
            $designerUser = $designTask->designer?->user;
            if ($designerUser) {
                $clientName = $designTask->display_client_name;
                $notes = $designTask->revision_notes ? " - ملاحظات: {$designTask->revision_notes}" : '';
                $attachmentNotice = (! empty($designTask->revision_files)) ? ' [مع صور ومرفقات]' : '';

                Notification::make()
                    ->title('طلب تعديل على مهمة 📝')
                    ->body("تم طلب تعديل على المهمة للعميل {$clientName}{$attachmentNotice}{$notes}")
                    ->icon('heroicon-o-arrow-path')
                    ->iconColor('warning')
                    ->warning()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view')
                            ->label('عرض لوحة المصمم')
                            ->url('/admin/designer-dashboard'),
                    ])
                    ->sendToDatabase($designerUser, isEventDispatched: true);
            }
        }

        // Only trigger if status changed to Approved
        if ($designTask->wasChanged('status') && $designTask->status === DesignTaskStatus::Approved) {
            if (! $designTask->is_template_update && $designTask->is_subscribed_client && $designTask->client) {
                $contract = $designTask->client->currentContract;
                if ($contract && $contract->additional_designs_enabled) {
                    if (! $designTask->contract_id) {
                        $designTask->updateQuietly(['contract_id' => $contract->id]);
                    }

                    $client = $designTask->client;
                    if ($client->additional_designs_balance > 0) {
                        $client->decrement('additional_designs_balance');

                        // Find oldest additional designs invoice that still has unfilled slots
                        $invoice = \App\Models\Invoice::where('client_id', $client->id)
                            ->whereHas('items', fn ($q) => $q->where('description', 'like', '%تصاميم إضافية%'))
                            ->get()
                            ->first(function ($inv) {
                                $qty = $inv->items()->where('description', 'like', '%تصاميم إضافية%')->sum('quantity');
                                $linkedCount = $inv->additionalDesignTasks()->count();

                                return $linkedCount < $qty;
                            });
                        if ($invoice) {
                            $designTask->updateQuietly(['additional_designs_invoice_id' => $invoice->id]);
                        }
                    } else {
                        $contract->increment('additional_designs_count');
                    }
                }
            }
        }
    }

    public function deleted(DesignTask $designTask): void
    {
        if ($designTask->status === DesignTaskStatus::Approved) {
            if (! $designTask->is_template_update && $designTask->is_subscribed_client && $designTask->client) {
                $contract = $designTask->client->currentContract;
                if ($contract && $contract->additional_designs_enabled) {
                    if ($designTask->additional_designs_invoice_id) {
                        $designTask->client->increment('additional_designs_balance');
                    } else {
                        $contract->decrement('additional_designs_count');
                    }
                }
            }
        }
    }
}
