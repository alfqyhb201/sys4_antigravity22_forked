<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * يمثل هذا النموذج العلاقة بين العميل والمصمم.
 *
 * يستخدم هذا النموذج كجدول وسيط (pivot table) في العلاقة بين العملاء والمصممين.
 *
 * @property int $id
 * @property int $client_id
 * @property int $designer_id
 * @property \Illuminate\Support\Carbon $week_start_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ClientDesigner extends Model
{
    use HasFactory;

    /**
     * اسم الجدول المرتبط بالنموذج.
     *
     * @var string
     */
    protected $table = 'client_designer';

    /**
     * السمات التي يمكن تعبئتها بشكل جماعي.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'contract_id',
        'designer_id',
        'week_start_date',
        'is_side',
    ];

    protected $casts = [
        'is_side' => 'boolean',
    ];

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع العميل (Client).
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع الاشتراك (Contract).
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المصمم (Designer).
     */
    public function designer(): BelongsTo
    {
        return $this->belongsTo(Designer::class);
    }

    /**
     * Get the tag distributions for the assignment.
     */
    public function distributions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientTagDistribution::class, 'client_designer_id');
    }
}
