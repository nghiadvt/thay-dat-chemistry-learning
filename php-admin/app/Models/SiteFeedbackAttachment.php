<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SiteFeedbackAttachment extends Model
{
    protected $fillable = [
        'site_feedback_id',
        'path',
        'mime_type',
        'size_bytes',
    ];

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(SiteFeedback::class, 'site_feedback_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
