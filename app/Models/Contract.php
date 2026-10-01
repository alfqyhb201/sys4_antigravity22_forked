<?php

namespace App\Models;

use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Contract extends Model
{
    use HasFactory, HasUserTracking, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'client_id',
        'status',
        'payment_type',
        'billing_cycle',
        'start_date',
        'end_date',
        'weekly_designs_count',
        'monthly_designs_count',
        'additional_designs_enabled',
        'additional_design_price',
        'simple_requests_enabled',
        'simple_request_price',
        'simple_requests_count',
        'additional_designs_count',
        'notification_date',
        'auto_suspension_enabled',
        'suspension_period_days',
        'total_amount',
        'currency_id',
        'marketing_amount',
        'auto_renewal',
        'is_under_lawsuit',
        'grace_period_days',
        'legal_notes',
        'created_by_user',
        'updated_by_user',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'notification_date' => 'datetime',
        'auto_suspension_enabled' => 'boolean',
        'auto_renewal' => 'boolean',
        'is_under_lawsuit' => 'boolean',
        'additional_designs_enabled' => 'boolean',
        'additional_design_price' => 'decimal:2',
        'simple_requests_enabled' => 'boolean',
        'simple_request_price' => 'decimal:2',
        'simple_requests_count' => 'integer',
        'additional_designs_count' => 'integer',
        'grace_period_days' => 'integer',
        'weekly_designs_count' => 'integer',
        'monthly_designs_count' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function designTasks(): HasMany
    {
        return $this->hasMany(DesignTask::class);
    }

    public function renewAndGenerateInvoice(?int $userId = null, ?\Illuminate\Support\Carbon $customEndDate = null, ?\Illuminate\Support\Carbon $customStartDate = null): Invoice
    {
        if ($this->status !== 'active') {
            throw new \LogicException("لا يمكن تجديد اشتراك غير نشط (الحالة الحالية: {$this->status})");
        }

        if (! $this->end_date) {
            \Illuminate\Support\Facades\Log::warning("تجديد اشتراك بدون تاريخ نهاية محدد. معرف الاشتراك: #{$this->id}");
        }

        return DB::transaction(function () use ($userId, $customEndDate, $customStartDate) {
            // 1. Calculate new cycle dates
            $oldEndDate = $this->end_date ?? now();
            $newStartDate = $customStartDate ?? $oldEndDate->copy();

            $newEndDate = $customEndDate ?? self::calculateEndDate($newStartDate, $this->billing_cycle);

            // Capture current counts to prevent race condition
            $currentSimpleRequestsCount = $this->simple_requests_count;
            $currentAdditionalDesignsCount = $this->additional_designs_count;

            // Calculate overtime designs (delivered after oldEndDate)
            $overtimeDesignsCount = \App\Models\ClientTagDistribution::where('status', 'completed')
                ->where('completed_at', '>', $oldEndDate)
                ->whereHas('clientDesigner', fn ($q) => $q->where('client_id', $this->client_id))
                ->count();

            $unitDesignPrice = $this->monthly_designs_count > 0
                ? round((float) $this->total_amount / $this->monthly_designs_count, 2)
                : 0.0;

            $overtimeDesignsAmount = round($overtimeDesignsCount * $unitDesignPrice, 2);

            // 2. Update contract dates
            $this->update([
                'start_date' => $newStartDate,
                'end_date' => $newEndDate,
                'notification_date' => null,
                'updated_by_user' => $userId,
            ]);

            // Reactivate client if manually suspended
            if ($this->client) {
                $this->client->update([
                    'status' => true,
                    'suspended_at' => null,
                ]);
            }

            // 3. Generate the invoice
            $issueDate = now();
            $dueDate = $this->payment_type === 'advance'
                ? $issueDate->copy()->addDays(7)
                : $newEndDate;

            $designAmount = (float) $this->total_amount;
            $marketingAmount = (float) $this->marketing_amount;

            $simpleRequestsAmount = 0.0;
            if ($this->simple_requests_enabled && $this->simple_request_price && $currentSimpleRequestsCount > 0) {
                $simpleRequestsAmount = round($currentSimpleRequestsCount * (float) $this->simple_request_price, 2);
            }

            $additionalDesignsAmount = 0.0;
            if ($this->additional_designs_enabled && $this->additional_design_price && $currentAdditionalDesignsCount > 0) {
                $additionalDesignsAmount = round($currentAdditionalDesignsCount * (float) $this->additional_design_price, 2);
            }

            $invoice = Invoice::create([
                'client_id' => $this->client_id,
                'contract_id' => $this->id,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'total_amount' => round($designAmount + $marketingAmount + $simpleRequestsAmount + $additionalDesignsAmount + $overtimeDesignsAmount, 2),
                'status' => 'posted',
                'notes' => 'فاتورة تجديد دورية للاشتراك #'.$this->id,
                'created_by_user' => $userId,
                'updated_by_user' => $userId,
            ]);

            $items = [];
            if ($designAmount > 0) {
                $items[] = new InvoiceItem([
                    'description' => 'خدمات التصميم - '.number_format($this->monthly_designs_count).' تصاميم شهرياً',
                    'quantity' => 1,
                    'unit_amount' => $designAmount,
                    'total' => $designAmount,
                ]);
            }

            if ($marketingAmount > 0) {
                $items[] = new InvoiceItem([
                    'description' => 'خدمات التسويق',
                    'quantity' => 1,
                    'unit_amount' => $marketingAmount,
                    'total' => $marketingAmount,
                ]);
            }

            if ($simpleRequestsAmount > 0) {
                $items[] = new InvoiceItem([
                    'description' => 'رسوم الطلبات البسيطة ('.$currentSimpleRequestsCount.' طلب × '.number_format((float) $this->simple_request_price, 2).')',
                    'quantity' => $currentSimpleRequestsCount,
                    'unit_amount' => (float) $this->simple_request_price,
                    'total' => $simpleRequestsAmount,
                ]);
            }

            if ($additionalDesignsAmount > 0) {
                $items[] = new InvoiceItem([
                    'description' => 'رسوم التصاميم الإضافية ('.$currentAdditionalDesignsCount.' تصميم × '.number_format((float) $this->additional_design_price, 2).')',
                    'quantity' => $currentAdditionalDesignsCount,
                    'unit_amount' => (float) $this->additional_design_price,
                    'total' => $additionalDesignsAmount,
                ]);
            }

            if ($overtimeDesignsAmount > 0) {
                $items[] = new InvoiceItem([
                    'description' => 'رسوم تصاميم الفترة الزائدة متأخرة التسليم ('.$overtimeDesignsCount.' تصميم × '.number_format($unitDesignPrice, 2).')',
                    'quantity' => $overtimeDesignsCount,
                    'unit_amount' => $unitDesignPrice,
                    'total' => $overtimeDesignsAmount,
                ]);
            }

            if (! empty($items)) {
                $invoice->items()->saveMany($items);
            }

            // Reset simple requests count
            if ($simpleRequestsAmount > 0) {
                $this->decrement('simple_requests_count', $currentSimpleRequestsCount);
            }

            // Link additional design tasks to this invoice and reset count
            if ($additionalDesignsAmount > 0) {
                $this->designTasks()
                    ->where('is_template_update', false)
                    ->where('status', \App\Filament\Enums\DesignTaskStatus::Approved)
                    ->whereNull('additional_designs_invoice_id')
                    ->update(['additional_designs_invoice_id' => $invoice->id]);

                $this->decrement('additional_designs_count', $currentAdditionalDesignsCount);
            }

            // 5. Apply client wallet balance if any — atomic operation prevents race conditions
            $this->applyWalletToInvoice($invoice, $userId);

            return $invoice->fresh(['receipts', 'allocations']);
        });
    }

    /**
     * Create a new renewal contract and terminate the old one.
     * Unlike renewAndGenerateInvoice(), this creates a NEW contract record
     * and sets the old contract status to 'renewed' to preserve contract history.
     */
    public function createRenewalContract(?int $userId = null, ?\Illuminate\Support\Carbon $customEndDate = null, ?\Illuminate\Support\Carbon $customStartDate = null): Contract
    {
        if ($this->status !== 'active') {
            throw new \LogicException("لا يمكن تجديد اشتراك غير نشط (الحالة الحالية: {$this->status})");
        }

        if (! $this->end_date) {
            \Illuminate\Support\Facades\Log::warning("تجديد اشتراك بدون تاريخ نهاية محدد. معرف الاشتراك: #{$this->id}");
        }

        return DB::transaction(function () use ($userId, $customEndDate, $customStartDate) {
            // 1. Calculate new cycle dates
            $oldEndDate = $this->end_date ?? now();
            $newStartDate = $customStartDate ?? $oldEndDate->copy();

            $newEndDate = $customEndDate ?? self::calculateEndDate($newStartDate, $this->billing_cycle);

            // Capture current counts to prevent race condition
            $currentSimpleRequestsCount = $this->simple_requests_count;
            $currentAdditionalDesignsCount = $this->additional_designs_count;

            // Calculate overtime designs (delivered after oldEndDate)
            $overtimeDesignsCount = \App\Models\ClientTagDistribution::where('status', 'completed')
                ->where('completed_at', '>', $oldEndDate)
                ->whereHas('clientDesigner', fn ($q) => $q->where('client_id', $this->client_id))
                ->count();

            $unitDesignPrice = $this->monthly_designs_count > 0
                ? round((float) $this->total_amount / $this->monthly_designs_count, 2)
                : 0.0;

            $overtimeDesignsAmount = round($overtimeDesignsCount * $unitDesignPrice, 2);

            $simpleRequestsAmount = 0.0;
            if ($this->simple_requests_enabled && $this->simple_request_price && $currentSimpleRequestsCount > 0) {
                $simpleRequestsAmount = round($currentSimpleRequestsCount * (float) $this->simple_request_price, 2);
            }

            $additionalDesignsAmount = 0.0;
            if ($this->additional_designs_enabled && $this->additional_design_price && $currentAdditionalDesignsCount > 0) {
                $additionalDesignsAmount = round($currentAdditionalDesignsCount * (float) $this->additional_design_price, 2);
            }

            // 2. إذا كان للاشتراك المنتهي (المؤخر) فاتورة مسودة، نقوم بتحديث مبالغها وتحويلها إلى مرحلة/مستحقة (posted)
            $draftInvoice = Invoice::where('contract_id', $this->id)
                ->where('status', 'draft')
                ->latest()
                ->first();

            if ($draftInvoice) {
                $extraAmount = $simpleRequestsAmount + $additionalDesignsAmount + $overtimeDesignsAmount;
                if ($extraAmount > 0) {
                    $draftInvoice->total_amount = round((float) $draftInvoice->total_amount + $extraAmount, 2);
                }

                $extraItems = [];
                if ($simpleRequestsAmount > 0) {
                    $extraItems[] = new \App\Models\InvoiceItem([
                        'description' => 'رسوم الطلبات البسيطة ('.$currentSimpleRequestsCount.' طلب × '.number_format((float) $this->simple_request_price, 2).')',
                        'quantity' => $currentSimpleRequestsCount,
                        'unit_amount' => (float) $this->simple_request_price,
                        'total' => $simpleRequestsAmount,
                    ]);
                }

                if ($additionalDesignsAmount > 0) {
                    $extraItems[] = new \App\Models\InvoiceItem([
                        'description' => 'رسوم التصاميم الإضافية ('.$currentAdditionalDesignsCount.' تصميم × '.number_format((float) $this->additional_design_price, 2).')',
                        'quantity' => $currentAdditionalDesignsCount,
                        'unit_amount' => (float) $this->additional_design_price,
                        'total' => $additionalDesignsAmount,
                    ]);
                }

                if ($overtimeDesignsAmount > 0) {
                    $extraItems[] = new \App\Models\InvoiceItem([
                        'description' => 'رسوم تصاميم الفترة الزائدة متأخرة التسليم ('.$overtimeDesignsCount.' تصميم × '.number_format($unitDesignPrice, 2).')',
                        'quantity' => $overtimeDesignsCount,
                        'unit_amount' => $unitDesignPrice,
                        'total' => $overtimeDesignsAmount,
                    ]);
                }

                if (! empty($extraItems)) {
                    $draftInvoice->items()->saveMany($extraItems);
                }

                $draftInvoice->status = 'posted';
                $draftInvoice->save();

                // تطبيق المحفظة على الفاتورة التي أصبحت مستحقة
                $this->applyWalletToInvoice($draftInvoice, $userId);
            }

            // Reset simple requests count on the OLD contract
            if ($simpleRequestsAmount > 0) {
                $this->decrement('simple_requests_count', $currentSimpleRequestsCount);
            }

            // Link additional design tasks to invoice and reset count on OLD contract
            if ($additionalDesignsAmount > 0) {
                $invoiceToLink = $draftInvoice ? $draftInvoice->id : null;
                if ($invoiceToLink) {
                    $this->designTasks()
                        ->where('is_template_update', false)
                        ->where('status', \App\Filament\Enums\DesignTaskStatus::Approved)
                        ->whereNull('additional_designs_invoice_id')
                        ->update(['additional_designs_invoice_id' => $invoiceToLink]);
                }

                $this->decrement('additional_designs_count', $currentAdditionalDesignsCount);
            }

            // 3. Set old contract status to 'renewed' (keep original dates for history)
            $this->update([
                'status' => 'renewed',
                'notification_date' => null,
                'updated_by_user' => $userId,
            ]);

            // Reactivate client if manually suspended
            if ($this->client) {
                $this->client->update([
                    'status' => true,
                    'suspended_at' => null,
                ]);
            }

            // 4. Create NEW contract with same terms
            $newContract = $this->client->contracts()->create([
                'status' => 'active',
                'payment_type' => $this->payment_type,
                'billing_cycle' => $this->billing_cycle,
                'start_date' => $newStartDate,
                'end_date' => $newEndDate,
                'weekly_designs_count' => $this->weekly_designs_count,
                'monthly_designs_count' => $this->monthly_designs_count,
                'total_amount' => $this->total_amount,
                'marketing_amount' => $this->marketing_amount,
                'currency_id' => $this->currency_id,
                'auto_renewal' => $this->auto_renewal ?? true,
                'additional_designs_enabled' => $this->additional_designs_enabled ?? false,
                'additional_design_price' => $this->additional_design_price,
                'additional_designs_count' => 0,
                'simple_requests_enabled' => $this->simple_requests_enabled ?? false,
                'simple_request_price' => $this->simple_request_price,
                'simple_requests_count' => 0,
                'grace_period_days' => $this->grace_period_days,
                'is_under_lawsuit' => $this->is_under_lawsuit ?? false,
                'legal_notes' => $this->legal_notes,
                'created_by_user' => $userId,
                'updated_by_user' => $userId,
            ]);

            // تحديث ارتباطات المصممين للاشتراك المجدد لترتبط بالعقد النشط الجديد
            \App\Models\ClientDesigner::where('contract_id', $this->id)
                ->update(['contract_id' => $newContract->id]);

            // 5. Generate the invoice linked to the NEW contract
            $issueDate = now();
            $dueDate = $newContract->payment_type === 'advance'
                ? $issueDate->copy()->addDays(7)
                : $newEndDate;

            $designAmount = (float) $newContract->total_amount;
            $marketingAmount = (float) $newContract->marketing_amount;

            // تحديد حالة الفاتورة الجديدة: مسودة (draft) للدفع المؤخر، ومرحلة (posted) للدفع المقدم
            $newInvoiceStatus = $newContract->payment_type === 'deferred' ? 'draft' : 'posted';

            // إذا كان الاشتراك دافعاً مقدماً وكان هناك مبالغ إضافية لم تُربط بفاتورة مسودة، نضيفها هنا
            $newInvoiceExtraAmount = 0.0;
            if (! $draftInvoice) {
                $newInvoiceExtraAmount = $simpleRequestsAmount + $additionalDesignsAmount + $overtimeDesignsAmount;
            }

            $invoice = Invoice::create([
                'client_id' => $newContract->client_id,
                'contract_id' => $newContract->id,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'total_amount' => round($designAmount + $marketingAmount + $newInvoiceExtraAmount, 2),
                'status' => $newInvoiceStatus,
                'notes' => 'فاتورة تجديد دورية للاشتراك #'.$newContract->id.' (بديل للاشتراك #'.$this->id.')',
                'created_by_user' => $userId,
                'updated_by_user' => $userId,
            ]);

            $items = [];
            if ($designAmount > 0) {
                $items[] = new \App\Models\InvoiceItem([
                    'description' => 'خدمات التصميم - '.number_format($newContract->monthly_designs_count).' تصاميم شهرياً',
                    'quantity' => 1,
                    'unit_amount' => $designAmount,
                    'total' => $designAmount,
                ]);
            }

            if ($marketingAmount > 0) {
                $items[] = new \App\Models\InvoiceItem([
                    'description' => 'خدمات التسويق',
                    'quantity' => 1,
                    'unit_amount' => $marketingAmount,
                    'total' => $marketingAmount,
                ]);
            }

            if (! $draftInvoice) {
                if ($simpleRequestsAmount > 0) {
                    $items[] = new \App\Models\InvoiceItem([
                        'description' => 'رسوم الطلبات البسيطة ('.$currentSimpleRequestsCount.' طلب × '.number_format((float) $this->simple_request_price, 2).')',
                        'quantity' => $currentSimpleRequestsCount,
                        'unit_amount' => (float) $this->simple_request_price,
                        'total' => $simpleRequestsAmount,
                    ]);
                }

                if ($additionalDesignsAmount > 0) {
                    $items[] = new \App\Models\InvoiceItem([
                        'description' => 'رسوم التصاميم الإضافية ('.$currentAdditionalDesignsCount.' تصميم × '.number_format((float) $this->additional_design_price, 2).')',
                        'quantity' => $currentAdditionalDesignsCount,
                        'unit_amount' => (float) $this->additional_design_price,
                        'total' => $additionalDesignsAmount,
                    ]);
                }

                if ($overtimeDesignsAmount > 0) {
                    $items[] = new \App\Models\InvoiceItem([
                        'description' => 'رسوم تصاميم الفترة الزائدة متأخرة التسليم ('.$overtimeDesignsCount.' تصميم × '.number_format($unitDesignPrice, 2).')',
                        'quantity' => $overtimeDesignsCount,
                        'unit_amount' => $unitDesignPrice,
                        'total' => $overtimeDesignsAmount,
                    ]);
                }
            }

            if (! empty($items)) {
                $invoice->items()->saveMany($items);
            }

            if (! $draftInvoice && $additionalDesignsAmount > 0) {
                $this->designTasks()
                    ->where('is_template_update', false)
                    ->where('status', \App\Filament\Enums\DesignTaskStatus::Approved)
                    ->whereNull('additional_designs_invoice_id')
                    ->update(['additional_designs_invoice_id' => $invoice->id]);
            }

            // 6. إذا كانت الفاتورة الجديدة مرحلة مباشرة (للدفع المقدم)، نطبق خصم المحفظة عليها إن وجد رصيد
            if ($newInvoiceStatus === 'posted') {
                $this->applyWalletToInvoice($invoice, $userId);
            }

            return $newContract;
        });
    }

    /**
     * تطبيق الدفعات المقدمة غير المخصصة أو رصيد المحفظة للعميل على فاتورة مستحقة.
     */
    public function applyWalletToInvoice(Invoice $invoice, ?int $userId = null): void
    {
        $client = $this->client;
        if (! $client) {
            return;
        }

        $invoiceTotal = (float) $invoice->total_amount;
        if ($invoiceTotal <= 0) {
            return;
        }

        // 1. Auto allocate from advance receipts
        if ($client->total_advance_balance > 0) {
            try {
                app(\App\Services\PaymentService::class)->autoAllocateAdvances($invoice, $userId);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("تعذر التخصيص التلقائي للدفعات المقدمة على الفاتورة #{$invoice->id}: {$e->getMessage()}");
            }
        }

        // 2. Auto allocate from legacy wallet balance if present
        $freshInvoice = $invoice->fresh(['receipts', 'allocations']);
        if ($freshInvoice && $freshInvoice->remaining > 0 && (float) ($client->getRawOriginal('wallet_balance') ?? 0) > 0) {
            $legacyBalance = (float) $client->getRawOriginal('wallet_balance');
            $applyAmount = min($legacyBalance, (float) $freshInvoice->remaining);
            if ($applyAmount > 0) {
                DB::update(
                    'UPDATE clients SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?',
                    [$applyAmount, $client->id, $applyAmount]
                );
                Receipt::create([
                    'client_id' => $client->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $applyAmount,
                    'receipt_date' => now()->toDateString(),
                    'payment_method' => 'wallet',
                    'notes' => 'سداد تلقائي من محفظة العميل عند تجديد الاشتراك',
                    'created_by_user' => $userId,
                    'updated_by_user' => $userId,
                ]);
                $freshInvoice->refresh();
                if ($freshInvoice->remaining <= 0) {
                    $freshInvoice->update(['status' => 'paid']);
                }
            }
        }
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired(Builder $query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeSuspended(Builder $query)
    {
        return $query->where('status', 'suspended');
    }

    public function scopeRenewed(Builder $query)
    {
        return $query->where('status', 'renewed');
    }

    public static function calculateMonthlyDesignsCount(int $weeklyCount): int
    {
        $monthly = $weeklyCount * 4;

        if ($weeklyCount === 7 || $weeklyCount === 6) {
            $monthly += 2;
        } elseif ($weeklyCount === 5 || $weeklyCount === 4) {
            $monthly += 1;
        }

        return $monthly;
    }

    public static function calculateEndDate(\Illuminate\Support\Carbon|string $startDate, string $billingCycle): \Illuminate\Support\Carbon
    {
        $date = \Illuminate\Support\Carbon::parse($startDate);

        return match ($billingCycle) {
            'weekly' => $date->copy()->addWeek()->subDay(),
            'yearly' => $date->copy()->addYear(),
            default => $date->copy()->addMonth(),
        };
    }
}
