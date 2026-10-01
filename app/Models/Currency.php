<?php

namespace App\Models;

use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * يمثل هذا النموذج عملة في النظام.
 *
 * @property int $id
 * @property string $currency
 * @property string $currency_name
 * @property float $value
 * @property bool $is_base
 * @property string|null $symbol
 * @property int $decimal_places
 * @property bool $is_active
 * @property int|null $added_by_user
 * @property int|null $created_by_user
 * @property int|null $updated_by_user
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Currency extends Model
{
    use HasFactory, HasUserTracking, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'currency',
        'currency_name',
        'value',
        'is_base',
        'symbol',
        'decimal_places',
        'is_active',
        'created_by_user',
        'added_by_user',
        'updated_by_user',
    ];

    /**
     * التحويلات الجماعية للأنواع.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'float',
        'is_base' => 'boolean',
        'is_active' => 'boolean',
        'decimal_places' => 'integer',
    ];

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user');
    }

    public function exchangeRatesFrom(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency_id');
    }

    public function exchangeRatesTo(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'to_currency_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function getBase(): ?self
    {
        return static::where('is_base', true)->first();
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (Currency $currency) {
            if ($currency->is_base) {
                static::where('id', '!=', $currency->id)->where('is_base', true)->update(['is_base' => false]);
            }
        });
    }
}
