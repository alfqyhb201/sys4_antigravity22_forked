<?php

namespace App\Models;

use App\Models\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ClientTagDistribution extends Model
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
        'client_designer_id',
        'tag_id',
        'distribution_date',
        'scheduled_sending_at',
        'idea_id',
        'custom_idea',
        'status',
        'designer_notes',
        'attachment_path',
        'reviewer_feedback',
        'reviewer_attachments',
        'reviewer_id',
        'sender_id',
        'completed_at',
    ];

    protected $casts = [
        'reviewer_attachments' => 'array',
        'scheduled_sending_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function clientDesigner(): BelongsTo
    {
        return $this->belongsTo(ClientDesigner::class);
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(Tag::class);
    }

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function socialMediaPosts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SocialMediaPost::class);
    }

    public function scopeForSocialMediaPublishing(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('status', ['sending', 'completed'])
            ->where('scheduled_sending_at', '<=', now())
            ->whereHas('clientDesigner.client.contracts', function ($q) {
                $q->where('status', 'active');
            })
            ->whereHas('clientDesigner.client.socialMedia');
    }

    public function scopeWhereFullyPublished(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function ($q) {
            $clientPlatformsSub = '(SELECT COUNT(*) FROM client_social_media INNER JOIN client_designer ON client_designer.client_id = client_social_media.client_id WHERE client_designer.id = client_tag_distributions.client_designer_id)';
            $publishedPostsSub = '(SELECT COUNT(DISTINCT social_media_id) FROM social_media_posts WHERE social_media_posts.client_tag_distribution_id = client_tag_distributions.id)';

            $q->where(function ($sub) use ($clientPlatformsSub, $publishedPostsSub) {
                $sub->whereRaw("{$clientPlatformsSub} > 0")
                    ->whereRaw("{$publishedPostsSub} >= {$clientPlatformsSub}");
            })->orWhere(function ($sub) use ($clientPlatformsSub, $publishedPostsSub) {
                $sub->whereRaw("{$clientPlatformsSub} = 0")
                    ->whereRaw("{$publishedPostsSub} > 0");
            });
        });
    }

    public function scopeWhereNotFullyPublished(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function ($q) {
            $clientPlatformsSub = '(SELECT COUNT(*) FROM client_social_media INNER JOIN client_designer ON client_designer.client_id = client_social_media.client_id WHERE client_designer.id = client_tag_distributions.client_designer_id)';
            $publishedPostsSub = '(SELECT COUNT(DISTINCT social_media_id) FROM social_media_posts WHERE social_media_posts.client_tag_distribution_id = client_tag_distributions.id)';

            $q->where(function ($sub) use ($clientPlatformsSub, $publishedPostsSub) {
                $sub->whereRaw("{$clientPlatformsSub} > 0")
                    ->whereRaw("{$publishedPostsSub} < {$clientPlatformsSub}");
            })->orWhere(function ($sub) use ($clientPlatformsSub, $publishedPostsSub) {
                $sub->whereRaw("{$clientPlatformsSub} = 0")
                    ->whereRaw("{$publishedPostsSub} = 0");
            });
        });
    }

    public function scopeWherePartiallyPublished(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        $clientPlatformsSub = '(SELECT COUNT(*) FROM client_social_media INNER JOIN client_designer ON client_designer.client_id = client_social_media.client_id WHERE client_designer.id = client_tag_distributions.client_designer_id)';
        $publishedPostsSub = '(SELECT COUNT(DISTINCT social_media_id) FROM social_media_posts WHERE social_media_posts.client_tag_distribution_id = client_tag_distributions.id)';

        return $query->whereRaw("{$publishedPostsSub} > 0")
            ->whereRaw("{$publishedPostsSub} < {$clientPlatformsSub}");
    }

    public function scopeWherePendingPublishing(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereDoesntHave('socialMediaPosts');
    }

    public function getSocialMediaPublishingStatusAttribute(): string
    {
        $totalPlatforms = $this->clientDesigner?->client?->socialMedia?->count() ?? 0;
        $publishedCount = $this->socialMediaPosts?->pluck('social_media_id')->unique()->count() ?? 0;

        if ($totalPlatforms > 0) {
            if ($publishedCount >= $totalPlatforms) {
                return 'completed';
            }
            if ($publishedCount > 0) {
                return 'partial';
            }

            return 'pending';
        }

        return $publishedCount > 0 ? 'completed' : 'pending';
    }
}
