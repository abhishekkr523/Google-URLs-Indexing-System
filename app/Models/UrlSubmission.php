<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrlSubmission extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'user_id',
        'url',
        'notification_type',
        'status',
        'http_status_code',
        'response_body',
        'failure_reason',
        'requested_at',
        'processed_at',
    ];

    protected $casts = [
        'response_body' => 'array',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Badge color used purely for the dashboard/admin UI.
     */
    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_SUBMITTED => 'green',
            self::STATUS_FAILED, self::STATUS_ERROR => 'red',
            self::STATUS_PROCESSING => 'blue',
            default => 'gray',
        };
    }
}
