<?php

namespace App\Models;

use App\Filament\Enums\ComplaintStatus;
use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * يمثل هذا النموذج شكوى عميل في النظام.
 *
 * يحتوي على تفاصيل الشكوى وحالتها وعلاقتها بالعميل والمستخدمين.
 *
 * @property int $id
 * @property int $client_id
 * @property string $description
 * @property ComplaintStatus $status
 * @property int|null $added_by_user
 * @property int|null $updated_by_user
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Complaint extends Model
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
        'client_id',
        'description',
        'status',
        'created_by_user',
        'added_by_user',
        'updated_by_user',
        'resolved_by_user',
    ];

    /**
     * الحصول على السمات التي يجب تحويلها.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ComplaintStatus::class,
        ];
    }

    /**
     * يحدد علاقة "ينتمي إلى" مع العميل.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى" مع المستخدم الذي أضاف الشكوى.
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user');
    }

    /**
     * يحدد علاقة "ينتمي إلى" مع المستخدم الذي قام بحل الشكوى.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user');
    }
}
