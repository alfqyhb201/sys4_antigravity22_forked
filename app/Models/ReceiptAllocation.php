<?php

namespace App\Models;

use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ReceiptAllocation extends Model
{
    use HasFactory, HasUserTracking, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'receipt_id',
        'invoice_id',
        'amount',
        'allocated_currency_id',
        'exchange_rate',
        'notes',
        'created_by_user',
        'updated_by_user',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    protected static function boot(): void
    {
        parent::boot();

        $updateInvoiceStatus = function (self $allocation) {
            $invoice = $allocation->invoice;
            if ($invoice && $invoice->remaining <= 0) {
                $invoice->update(['status' => 'paid']);
            } elseif ($invoice && $invoice->status === 'paid' && $invoice->remaining > 0) {
                $invoice->update(['status' => 'posted']);
            }
        };

        static::created($updateInvoiceStatus);
        static::deleted($updateInvoiceStatus);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function allocatedCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'allocated_currency_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user');
    }
}
