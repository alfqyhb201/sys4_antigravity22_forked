<?php

namespace App\Models;

use App\Filament\Enums\DesignTaskPriority;
use App\Filament\Enums\DesignTaskStatus;
use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * يمثل هذا النموذج مهمة تصميم في النظام.
 *
 * يتتبع دورة حياة المهمة من الإنشاء إلى الموافقة، بما يشمل
 * تعيين المصمم، رفع التصاميم، والمراجعة.
 *
 * @property int $id
 * @property int $designer_id
 * @property int $assigner_id
 * @property bool $is_subscribed_client
 * @property int|null $client_id
 * @property string|null $client_name
 * @property string|null $description
 * @property array|null $reference_files
 * @property bool $is_extra
 * @property float|null $amount
 * @property string $priority
 * @property bool $deduct_from_balance
 * @property string $status
 * @property array|null $design_files
 * @property string|null $revision_notes
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class DesignTask extends Model
{
    use HasFactory, HasUserTracking, LogsActivity, SoftDeletes;

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
        'designer_id',
        'assigner_id',
        'is_subscribed_client',
        'client_id',
        'contract_id',
        'additional_designs_invoice_id',
        'client_name',
        'description',
        'reference_files',
        'is_extra',
        'is_template_update',
        'template_type',
        'local_path',
        'amount',
        'priority',
        'deduct_from_balance',
        'status',
        'scheduled_at',
        'design_files',
        'revision_notes',
        'revision_files',
        'submitted_at',
    ];

    /**
     * الحصول على السمات التي يجب تحويلها.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference_files' => 'array',
            'design_files' => 'array',
            'revision_files' => 'array',
            'is_subscribed_client' => 'boolean',
            'is_extra' => 'boolean',
            'deduct_from_balance' => 'boolean',
            'amount' => 'decimal:2',
            'scheduled_at' => 'datetime',
            'submitted_at' => 'datetime',
            'status' => DesignTaskStatus::class,
            'priority' => DesignTaskPriority::class,
        ];
    }

    /**
     * يحدد علاقة "ينتمي إلى" مع المصمم.
     */
    public function designer(): BelongsTo
    {
        return $this->belongsTo(Designer::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى" مع المستخدم الذي أنشأ المهمة.
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigner_id');
    }

    /**
     * يحدد علاقة "ينتمي إلى" مع العميل المشترك.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى" مع الاشتراك.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى" مع الفاتورة (للتصاميم الإضافية المفوتَرة).
     */
    public function additionalDesignsInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'additional_designs_invoice_id');
    }

    /**
     * الحصول على اسم العميل المعروض (سواء مشترك أو يدوي).
     */
    public function getDisplayClientNameAttribute(): string
    {
        if ($this->is_subscribed_client && $this->client) {
            return $this->client->company.($this->client->client_name ? ' - '.$this->client->client_name : '');
        }

        return $this->client_name ?? 'غير محدد';
    }
}
