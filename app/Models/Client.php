<?php

namespace App\Models;

use App\Models\Traits\HasUserTracking;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * يمثل هذا النموذج عميلاً في النظام.
 *
 * يحتوي على معلومات العميل الأساسية وعلاقاته مع النماذج الأخرى
 * مثل الموقع، العملة، الاحتياجات، الفئة، المصممين، والمستخدمين.
 *
 * @property int $id
 * @property string $company
 * @property string $client_name
 * @property int $location_id
 * @property string|null $address
 * @property string $contact_number
 * @property string|null $contact_job
 * @property string|null $marketing_amount
 * @property \Illuminate\Support\Carbon|null $notified_at
 * @property int|null $suspension_days
 * @property bool $is_credit_allowed
 * @property \Illuminate\Support\Carbon|null $suspended_at
 * @property int|null $category_id
 * @property string|null $customer_rating_value
 * @property int|null $change_cliche_threshold
 * @property int $added_by_user
 * @property int|null $updated_by_user
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $has_generate_feature
 * @property array|null $generate_types
 */
class Client extends Model
{
    use HasFactory, HasUserTracking, LogsActivity, SoftDeletes;

    public ?array $_tracked_tag_groups = null;

    public ?array $_tracked_tags = null;

    protected static function booted(): void
    {
        static::updating(function (Client $client) {
            if ($client->_tracked_tag_groups === null && $client->exists) {
                $client->captureOriginalRelations();
            }
        });
    }

    public function captureOriginalRelations(): void
    {
        if ($this->exists) {
            $this->_tracked_tag_groups = $this->tagGroups()->get()->pluck('name', 'id')->toArray();
            $this->_tracked_tags = $this->tags()->get()->pluck('name', 'id')->toArray();
        } else {
            $this->_tracked_tag_groups = [];
            $this->_tracked_tags = [];
        }
    }

    public function logRelationshipChanges(): void
    {
        $originalTagGroups = $this->_tracked_tag_groups ?? [];
        $currentTagGroups = $this->tagGroups()->get()->pluck('name', 'id')->toArray();

        $originalTags = $this->_tracked_tags ?? [];
        $currentTags = $this->tags()->get()->pluck('name', 'id')->toArray();

        $origTagGroupIds = array_map('strval', array_keys($originalTagGroups));
        $currTagGroupIds = array_map('strval', array_keys($currentTagGroups));
        sort($origTagGroupIds);
        sort($currTagGroupIds);

        $origTagIds = array_map('strval', array_keys($originalTags));
        $currTagIds = array_map('strval', array_keys($currentTags));
        sort($origTagIds);
        sort($currTagIds);

        $tagGroupsChanged = ($origTagGroupIds !== $currTagGroupIds);
        $tagsChanged = ($origTagIds !== $currTagIds);

        if (! $tagGroupsChanged && ! $tagsChanged) {
            return;
        }

        // Search for recent updated activity log created for this client in the current request
        $recentUpdatedActivity = Activity::forSubject($this)
            ->where('description', 'updated')
            ->where('created_at', '>=', now()->subSeconds(10))
            ->latest('id')
            ->first();

        if ($recentUpdatedActivity) {
            $properties = $recentUpdatedActivity->properties ? $recentUpdatedActivity->properties->toArray() : [];
            $old = $properties['old'] ?? [];
            $attributes = $properties['attributes'] ?? [];

            if ($tagGroupsChanged) {
                $old['tag_groups'] = array_values($originalTagGroups);
                $attributes['tag_groups'] = array_values($currentTagGroups);
            }

            if ($tagsChanged) {
                $old['tags'] = array_values($originalTags);
                $attributes['tags'] = array_values($currentTags);
            }

            $properties['old'] = $old;
            $properties['attributes'] = $attributes;

            $recentUpdatedActivity->properties = $properties;
            $recentUpdatedActivity->save();
        } elseif ($this->wasRecentlyCreated) {
            $recentCreatedActivity = Activity::forSubject($this)
                ->where('description', 'created')
                ->where('created_at', '>=', now()->subSeconds(10))
                ->latest('id')
                ->first();

            if ($recentCreatedActivity) {
                $properties = $recentCreatedActivity->properties ? $recentCreatedActivity->properties->toArray() : [];
                $attributes = $properties['attributes'] ?? [];

                if ($tagGroupsChanged) {
                    $attributes['tag_groups'] = array_values($currentTagGroups);
                }

                if ($tagsChanged) {
                    $attributes['tags'] = array_values($currentTags);
                }

                $properties['attributes'] = $attributes;
                $recentCreatedActivity->properties = $properties;
                $recentCreatedActivity->save();
            }
        } else {
            $old = [];
            $attributes = [];

            if ($tagGroupsChanged) {
                $old['tag_groups'] = array_values($originalTagGroups);
                $attributes['tag_groups'] = array_values($currentTagGroups);
            }

            if ($tagsChanged) {
                $old['tags'] = array_values($originalTags);
                $attributes['tags'] = array_values($currentTags);
            }

            activity()
                ->performedOn($this)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old' => $old,
                    'attributes' => $attributes,
                ])
                ->log('updated');
        }

        // Reset tracked relations to current state
        $this->_tracked_tag_groups = $currentTagGroups;
        $this->_tracked_tags = $currentTags;
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

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
        'company',
        'client_name',
        'location_id',
        'address',
        'contact_number',
        'contact_job',
        'marketing_amount',
        'notified_at',
        'suspension_days',
        'is_credit_allowed',
        'tax_number',
        'is_subscribed_client',
        'cliche_counter',
        'change_cliche_threshold',

        'suspended_at',
        'category_id',
        'customer_rating_value',
        'created_by_user',
        'added_by_user',
        'updated_by_user',
        'fixed_designer_id',
        'additional_designs_balance',
        'is_pay_per_design',
        'cliche_counter',
        'notes',
        'logo_path',
        'design_data',
        'status',
        'wallet_balance',
        'has_generate_feature',
        'generate_types',
        'enable_very_high',
        'importance_weights',
    ];

    /**
     * السمات التي يجب تحويلها إلى أنواع بيانات أصلية.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_credit_allowed' => 'boolean',
        'is_pay_per_design' => 'boolean',
        'has_generate_feature' => 'boolean',
        'generate_types' => 'array',
        'enable_very_high' => 'boolean',
        'importance_weights' => 'array',
        'wallet_balance' => 'decimal:2',
    ];

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المصمم المثبت (Fixed Designer).
     */
    public function fixedDesigner(): BelongsTo
    {
        return $this->belongsTo(Designer::class, 'fixed_designer_id');
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع الموقع (Location).
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع العملة (Currency).
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع احتياجات العميل (Client Needs).
     */
    public function clientNeeds(): BelongsToMany
    {
        return $this->belongsToMany(ClientNeed::class, 'client_client_need');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(ClientTemplate::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع الفئة (Category).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع المصممين (Designers).
     */
    public function designers(): BelongsToMany
    {
        return $this->belongsToMany(Designer::class, 'client_designer');
    }

    /**
     * يحدد علاقة "لديه العديد" (hasMany) مع ClientDesigner.
     */
    public function clientDesigners(): HasMany
    {
        return $this->hasMany(ClientDesigner::class);
    }

    /**
     * سجلات توزيع العميل للأسبوع الحالي.
     */
    public function currentWeekClientDesigners(): HasMany
    {
        $currentWeek = Carbon::now()->startOfWeek()->format('Y-m-d');

        return $this->hasMany(ClientDesigner::class, 'client_id')
            ->whereDate('week_start_date', $currentWeek);
    }

    /**
     * سجلات توزيع العميل السابقة مرتبة تنازلياً حسب تاريخ الأسبوع.
     */
    public function recentClientDesigners(): HasMany
    {
        return $this->hasMany(ClientDesigner::class, 'client_id')
            ->orderByDesc('week_start_date')
            ->orderBy('is_side');
    }

    /**
     * التصاميم المكتملة الخاصة بهذا العميل.
     */
    public function completedDistributions(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(ClientTagDistribution::class, ClientDesigner::class, 'client_id', 'client_designer_id', 'id', 'id')
            ->where('client_tag_distributions.status', 'completed');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع الوسوم (Tags).
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'client_tag');
    }

    /**
     * يحدد علاقة "ينتمي إلى العديد" (belongsToMany) مع مجموعات الوسوم (Tag Groups).
     */
    public function tagGroups(): BelongsToMany
    {
        return $this->belongsToMany(TagGroup::class, 'client_tag_group');
    }

    /**
     * وسائل التواصل الاجتماعي الخاصة بالعميل.
     */
    public function socialMedia(): BelongsToMany
    {
        return $this->belongsToMany(SocialMedia::class, 'client_social_media')
            ->withPivot(['account_url', 'credentials', 'notes'])
            ->withTimestamps();
    }

    /**
     * سجلات منصات التواصل الاجتماعي الخاصة بالعميل.
     */
    public function clientSocialMedia(): HasMany
    {
        return $this->hasMany(ClientSocialMedia::class);
    }

    /**
     * يحدد علاقة "ينتمي إلى" (belongsTo) مع المستخدم الذي أضاف العميل.
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function currentContract(): HasOne
    {
        return $this->hasOne(Contract::class)->latestOfMany();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * إجمالي قيمة الفواتير المُصدرة (posted + paid).
     */
    public function getTotalInvoicedAttribute(): float
    {
        if (array_key_exists('total_invoiced', $this->attributes)) {
            return (float) $this->attributes['total_invoiced'];
        }

        if ($this->relationLoaded('invoices')) {
            return (float) $this->invoices
                ->whereIn('status', ['posted', 'paid'])
                ->sum('total_amount');
        }

        return (float) $this->invoices()
            ->whereIn('status', ['posted', 'paid'])
            ->sum('total_amount');
    }

    /**
     * إجمالي المسدد فعلياً = إجمالي المُفوتر - الرصيد المتبقي.
     */
    public function getTotalPaidAttribute(): float
    {
        return $this->total_invoiced - $this->outstanding_balance;
    }

    /**
     * الرصيد المتبقي الفعلي = مجموع remaining للفواتير المستحقة (posted).
     */
    public function getOutstandingBalanceAttribute(): float
    {
        if (array_key_exists('outstanding_balance', $this->attributes)) {
            return round((float) $this->attributes['outstanding_balance'], 2);
        }

        if ($this->relationLoaded('invoices')) {
            return (float) $this->invoices
                ->where('status', 'posted')
                ->sum('remaining');
        }

        return (float) $this->invoices()
            ->where('status', 'posted')
            ->get()
            ->sum('remaining');
    }

    /**
     * الرصيد المتبقي (alias لـ outstanding_balance للتوافق).
     */
    public function getBalanceAttribute(): float
    {
        return $this->outstanding_balance;
    }

    /**
     * السندات غير المخصصة (الدفعات المقدمة).
     */
    public function unallocatedReceipts(): HasMany
    {
        return $this->hasMany(Receipt::class)->whereNull('invoice_id')->where('unallocated_amount', '>', 0);
    }

    /**
     * إجمالي رصيد الدفعات المقدمة غير المخصصة للعميل.
     */
    public function getTotalAdvanceBalanceAttribute(): float
    {
        if ($this->relationLoaded('receipts')) {
            return round((float) $this->receipts
                ->whereNull('invoice_id')
                ->where('unallocated_amount', '>', 0)
                ->sum('unallocated_amount'), 2);
        }

        return round((float) $this->receipts()
            ->whereNull('invoice_id')
            ->where('unallocated_amount', '>', 0)
            ->sum('unallocated_amount'), 2);
    }

    /**
     * رصيد المحفظة (متوافق مع النظام الجديد كـ Alias للدفعات المقدمة).
     */
    public function getWalletBalanceAttribute(): float
    {
        if (array_key_exists('wallet_balance', $this->attributes) && (float) $this->attributes['wallet_balance'] > 0) {
            return round((float) $this->attributes['wallet_balance'], 2);
        }

        return $this->total_advance_balance;
    }

    /**
     * تحقق مما إذا كان لدى العميل فواتير مستحقة متجاوزة لمهلة السداد.
     */
    public function hasOverdueInvoices(): bool
    {
        $postedInvoices = $this->relationLoaded('invoices')
            ? $this->invoices->where('status', 'posted')
            : $this->invoices()->where('status', 'posted')->get();

        if ($postedInvoices->isEmpty()) {
            return false;
        }

        $graceDays = $this->currentContract?->grace_period_days ?? 7;
        foreach ($postedInvoices as $invoice) {
            if ($invoice->due_date && $invoice->due_date->addDays($graceDays)->isBefore(now()->startOfDay())) {
                return true;
            }
        }

        return false;
    }

    public function getLastPaymentDateAttribute(): ?\Illuminate\Support\Carbon
    {
        if (array_key_exists('last_payment_date', $this->attributes)) {
            return $this->attributes['last_payment_date'] !== null
                ? \Illuminate\Support\Carbon::parse($this->attributes['last_payment_date'])
                : null;
        }

        if ($this->relationLoaded('receipts')) {
            $date = $this->receipts->max('receipt_date');
        } else {
            $date = $this->receipts()->max('receipt_date');
        }

        return $date ? \Illuminate\Support\Carbon::parse($date) : null;
    }

    public function getDaysOverdueAttribute(): int
    {
        $postedInvoices = $this->relationLoaded('invoices')
            ? $this->invoices->where('status', 'posted')
            : $this->invoices()->where('status', 'posted')->get();

        if ($postedInvoices->isEmpty()) {
            return 0;
        }

        $graceDays = $this->currentContract?->grace_period_days ?? 7;
        $maxDays = 0;
        foreach ($postedInvoices as $invoice) {
            if ($invoice->due_date) {
                $dueDateWithGrace = $invoice->due_date->copy()->addDays($graceDays);
                if ($dueDateWithGrace->isBefore(now()->startOfDay())) {
                    $diff = $dueDateWithGrace->diffInDays(now()->startOfDay(), false);
                    if ($diff > $maxDays) {
                        $maxDays = (int) $diff;
                    }
                }
            }
        }

        return $maxDays;
    }

    public function getActivityStatusAttribute(): string
    {
        $contract = $this->currentContract;

        // 1. If contract is explicitly suspended
        if ($contract && $contract->status === 'suspended') {
            return 'suspended';
        }

        // 2. If client is manually suspended
        if (! $this->status) {
            return 'manually_suspended';
        }

        // 3. If contract is expired
        if ($contract && $contract->status === 'expired') {
            return 'expired';
        }

        // 4. Red: Auto suspended (has overdue invoices AND auto suspension is enabled)
        $hasOverdue = $this->hasOverdueInvoices();
        $autoSuspension = $contract?->auto_suspension_enabled ?? false;

        if ($hasOverdue && $autoSuspension) {
            return 'auto_suspended';
        }

        // 5. Yellow: Active but has unpaid invoices (pre-suspension / stage before auto-suspension)
        $hasUnpaid = $this->relationLoaded('invoices')
            ? $this->invoices->where('status', 'posted')->isNotEmpty()
            : $this->invoices()->where('status', 'posted')->exists();

        if ($hasUnpaid) {
            return 'pending_arrears';
        }

        // 6. Green: Active (status is true and no unpaid invoices)
        return 'active';
    }

    public function isUnderLawsuit(): bool
    {
        return (bool) ($this->currentContract?->is_under_lawsuit ?? false);
    }
}
