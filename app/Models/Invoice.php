<?php

namespace App\Models;

use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Invoice extends Model
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
        'contract_id',
        'currency_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'total_amount',
        'exchange_rate',
        'base_currency_amount',
        'status',
        'notes',
        'created_by_user',
        'updated_by_user',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'base_currency_amount' => 'decimal:2',
    ];

    public function getRemainingAttribute(): float
    {
        $directPaid = match (true) {
            $this->relationLoaded('receipts') && $this->receipts instanceof \Illuminate\Support\Collection => $this->receipts->sum('amount'),
            isset($this->attributes['receipts_sum_amount']) => $this->attributes['receipts_sum_amount'],
            default => $this->receipts()->sum('amount'),
        };

        $allocatedPaid = match (true) {
            $this->relationLoaded('allocations') && $this->allocations instanceof \Illuminate\Support\Collection => $this->allocations->sum('amount'),
            isset($this->attributes['allocations_sum_amount']) => $this->attributes['allocations_sum_amount'],
            default => $this->allocations()->sum('amount'),
        };

        return round((float) $this->total_amount - (float) ($directPaid + $allocatedPaid), 2);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Invoice $invoice) {
            // وراثة العملة من الاشتراك أو العملة الأساسية
            if (empty($invoice->currency_id)) {
                if ($invoice->contract_id) {
                    $invoice->currency_id = $invoice->contract?->currency_id;
                }
                if (empty($invoice->currency_id)) {
                    $invoice->currency_id = Currency::getBase()?->id ?? Currency::first()?->id;
                }
            }

            // جلب سعر الصرف تلقائياً إذا لم يُحدد
            $baseCurrency = Currency::getBase();
            if ($baseCurrency && $invoice->currency_id && $invoice->currency_id !== $baseCurrency->id) {
                if (empty($invoice->exchange_rate) || (float) $invoice->exchange_rate === 1.0) {
                    $service = app(\App\Services\CurrencyService::class);
                    $date = $invoice->issue_date ?? now();
                    $invoice->exchange_rate = $service->getRateForDate(
                        $invoice->currency_id,
                        $baseCurrency->id,
                        $date
                    );
                }
            } elseif (empty($invoice->exchange_rate)) {
                $invoice->exchange_rate = 1.0;
            }

            // حساب المبلغ بالعملة الأساسية
            $invoice->base_currency_amount = app(\App\Services\CurrencyService::class)->convertToBase(
                (float) ($invoice->total_amount ?? 0),
                $invoice->currency_id ?? ($baseCurrency?->id ?? 0),
                (float) ($invoice->exchange_rate ?? 1)
            );

            $prefix = 'INV-'.now()->format('Y').'-';
            $invoice->invoice_number = DB::transaction(function () use ($prefix) {
                $lastInvoice = DB::table('invoices')
                    ->where('invoice_number', 'like', $prefix.'%')
                    ->orderBy('id', 'desc')
                    ->lockForUpdate()
                    ->first();

                if ($lastInvoice) {
                    $lastNumber = (int) substr($lastInvoice->invoice_number, strlen($prefix));

                    return $prefix.str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                }

                return $prefix.'0001';
            });
        });

        static::saving(function (Invoice $invoice) {
            if ($invoice->isDirty('total_amount') || $invoice->isDirty('exchange_rate')) {
                $baseCurrency = Currency::getBase();
                $invoice->base_currency_amount = app(\App\Services\CurrencyService::class)->convertToBase(
                    (float) ($invoice->total_amount ?? 0),
                    $invoice->currency_id ?? ($baseCurrency?->id ?? 0),
                    (float) ($invoice->exchange_rate ?? 1)
                );
            }
        });

        static::updated(function (Invoice $invoice) {
            if ($invoice->isDirty('status') && $invoice->status === 'paid') {
                $contract = $invoice->contract;
                if ($contract && $contract->is_under_lawsuit) {
                    $client = $invoice->client;
                    if ($client) {
                        $client->unsetRelation('invoices');
                        if (! $client->hasOverdueInvoices()) {
                            $contract->update(['is_under_lawsuit' => false]);
                        }
                    }
                }
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function additionalDesignTasks(): HasMany
    {
        return $this->hasMany(DesignTask::class, 'additional_designs_invoice_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user');
    }
}
