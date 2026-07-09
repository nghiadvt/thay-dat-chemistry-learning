<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteFeedback extends Model
{
    protected $table = 'site_feedback';

    protected $fillable = [
        'user_id',
        'page_url',
        'page_title',
        'body',
        'priority',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SiteFeedbackAttachment::class);
    }

    public function priorityLabel(): string
    {
        return match ($this->priority) {
            'high' => 'Cao',
            'low' => 'Thấp',
            default => 'Trung bình',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'read' => 'Đã xem',
            'done' => 'Hoàn thành',
            default => 'Mới',
        };
    }
}
