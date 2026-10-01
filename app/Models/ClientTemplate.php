<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTemplate extends Model
{
    protected $fillable = [
        'client_id',
        'type',
        'file',
        'local_path',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (ClientTemplate $template) {
            if ($template->file) {
                if ($template->wasChanged('file')) {
                    $oldFile = $template->getOriginal('file');
                    if ($oldFile && $oldFile !== $template->file) {
                        app(\App\Services\ImageThumbnailService::class)->deleteThumbnail($oldFile);
                    }
                }
                app(\App\Services\ImageThumbnailService::class)->generateThumbnail($template->file);
            }
        });

        static::deleted(function (ClientTemplate $template) {
            if ($template->file) {
                app(\App\Services\ImageThumbnailService::class)->deleteThumbnail($template->file);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get thumbnail URL with fallback to original image.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->file) {
            return null;
        }

        $thumbnailService = app(\App\Services\ImageThumbnailService::class);
        $thumbnailPath = $thumbnailService->getThumbnailPath($this->file);

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($thumbnailPath)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($thumbnailPath);
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->file);
    }
}
