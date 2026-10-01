<?php

namespace App\Models;

use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Receipt extends Model
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
        'invoice_id',
        'paid_currency_id',
        'amount',
        'unallocated_amount',
        'original_amount',
        'exchange_rate',
        'base_currency_amount',
        'receipt_date',
        'payment_method',
        'bank_account_id',
        'reference_number',
        'notes',
        'created_by_user',
        'updated_by_user',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'amount' => 'decimal:2',
        'unallocated_amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'base_currency_amount' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();

        $updateInvoiceStatus = function (self $receipt) {
            $invoice = $receipt->invoice;
            if ($invoice && $invoice->remaining <= 0) {
                $invoice->update(['status' => 'paid']);
            } elseif ($invoice && $invoice->status === 'paid' && $invoice->remaining > 0) {
                $invoice->update(['status' => 'posted']);
            }
        };

        static::creating(function (self $receipt) {
            if (empty($receipt->client_id) && $receipt->invoice_id) {
                $receipt->client_id = $receipt->invoice?->client_id;
            }

            // وراثة عملة الدفع من الفاتورة
            if (empty($receipt->paid_currency_id) && $receipt->invoice_id) {
                $receipt->paid_currency_id = $receipt->invoice?->currency_id;
            }

            $baseCurrency = Currency::getBase();
            $service = app(\App\Services\CurrencyService::class);

            // حساب سعر الصرف (عملة الدفع ← العملة الأساسية)
            $needsRateCalculation = empty($receipt->exchange_rate)
                || (float) $receipt->exchange_rate === 0.0
                || ((float) $receipt->exchange_rate === 1.0 && $baseCurrency && $receipt->paid_currency_id && (int) $receipt->paid_currency_id !== (int) $baseCurrency->id);

            if ($needsRateCalculation) {
                if ($baseCurrency && $receipt->paid_currency_id && (int) $receipt->paid_currency_id !== (int) $baseCurrency->id) {
                    $date = $receipt->receipt_date ?? now();
                    $receipt->exchange_rate = $service->getRateForDate(
                        $receipt->paid_currency_id,
                        $baseCurrency->id,
                        $date
                    );
                } else {
                    $receipt->exchange_rate = 1.0;
                }
            }

            // original_amount = المبلغ المدفوع بعملة الدفع
            if (empty($receipt->original_amount)) {
                $receipt->original_amount = $receipt->amount;
            }

            // إذا كانت دفعة مقدمة (بدون فاتورة) ولم يُحدد unallocated_amount صراحةً
            if (empty($receipt->invoice_id) && ! isset($receipt->attributes['unallocated_amount'])) {
                $receipt->unallocated_amount = $receipt->amount;
            }

            // إذا كانت عملة الدفع تختلف عن عملة الفاتورة، حساب amount بعملة الفاتورة
            $invoice = $receipt->invoice_id ? $receipt->invoice : null;
            if ($invoice && $receipt->paid_currency_id && (int) $receipt->paid_currency_id !== (int) $invoice->currency_id) {
                // تحويل original_amount من عملة الدفع إلى عملة الفاتورة
                if ($receipt->original_amount && (float) $receipt->original_amount > 0 && empty($receipt->amount)) {
                    $receipt->amount = $service->convert(
                        (float) $receipt->original_amount,
                        $receipt->paid_currency_id,
                        $invoice->currency_id
                    );
                }
            }

            // base_currency_amount = original_amount × exchange_rate (عملة الدفع ← الأساسية)
            $receipt->base_currency_amount = $service->convertToBase(
                (float) ($receipt->original_amount ?? $receipt->amount ?? 0),
                $receipt->paid_currency_id ?? ($baseCurrency?->id ?? 0),
                (float) ($receipt->exchange_rate ?? 1)
            );
        });

        static::saving(function (self $receipt) {
            if ($receipt->isDirty('amount') || $receipt->isDirty('original_amount') || $receipt->isDirty('exchange_rate') || $receipt->isDirty('paid_currency_id')) {
                $service = app(\App\Services\CurrencyService::class);
                $receipt->base_currency_amount = $service->convertToBase(
                    (float) ($receipt->original_amount ?? $receipt->amount ?? 0),
                    $receipt->paid_currency_id ?? (Currency::getBase()?->id ?? 0),
                    (float) ($receipt->exchange_rate ?? 1)
                );
            }
        });

        static::created($updateInvoiceStatus);
        static::updated($updateInvoiceStatus);
        static::deleted($updateInvoiceStatus);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function paidCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'paid_currency_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function allocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    public function scopeUnallocated(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('invoice_id')->where('unallocated_amount', '>', 0);
    }
}
