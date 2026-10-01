<?php

namespace App\Models\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasUserTracking
{
    public static function bootHasUserTracking(): void
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                if (! $model->created_by_user) {
                    $model->created_by_user = auth()->id();
                }
                $model->updated_by_user = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by_user = auth()->id();
            }
        });
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
