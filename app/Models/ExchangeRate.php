<?php

namespace App\Models;

use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * يمثل سعر الصرف التاريخي بين عملتين.
 *
 * @property int $id
 * @property int $from_currency_id
 * @property int $to_currency_id
 * @property float $rate
 * @property Carbon $effective_date
 * @property string|null $notes
 * @property int|null $created_by_user
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExchangeRate extends Model
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
        'from_currency_id',
        'to_currency_id',
        'rate',
        'effective_date',
        'notes',
        'created_by_user',
    ];

    protected $casts = [
        'rate' => 'float',
        'effective_date' => 'date',
    ];

    public function fromCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    public function toCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user');
    }

    public static function getLatestRate(int $fromId, int $toId): ?float
    {
        if ($fromId === $toId) {
            return 1.0;
        }

        $direct = static::where('from_currency_id', $fromId)
            ->where('to_currency_id', $toId)
            ->latest('effective_date')
            ->latest('id')
            ->first();

        if ($direct) {
            return (float) $direct->rate;
        }

        $inverse = static::where('from_currency_id', $toId)
            ->where('to_currency_id', $fromId)
            ->latest('effective_date')
            ->latest('id')
            ->first();

        if ($inverse && $inverse->rate > 0) {
            return (float) round(1 / $inverse->rate, 6);
        }

        return null;
    }

    public static function getRateForDate(int $fromId, int $toId, Carbon|string $date): ?float
    {
        if ($fromId === $toId) {
            return 1.0;
        }

        $formattedDate = $date instanceof Carbon ? $date->toDateString() : $date;

        $direct = static::where('from_currency_id', $fromId)
            ->where('to_currency_id', $toId)
            ->where('effective_date', '<=', $formattedDate)
            ->latest('effective_date')
            ->latest('id')
            ->first();

        if ($direct) {
            return (float) $direct->rate;
        }

        $inverse = static::where('from_currency_id', $toId)
            ->where('to_currency_id', $fromId)
            ->where('effective_date', '<=', $formattedDate)
            ->latest('effective_date')
            ->latest('id')
            ->first();

        if ($inverse && $inverse->rate > 0) {
            return (float) round(1 / $inverse->rate, 6);
        }

        return null;
    }
}
