<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointEntry extends Model
{
    public const LABELS = [
        'on_time' => 'Submitted on time',
        'late' => 'Late submit',
        'first_approve' => 'Approved first time',
    ];

    protected $fillable = ['user_id', 'task_id', 'content_id', 'reason', 'points'];

    /** On task submit: by the deadline = on_time, otherwise late */
    public static function forSubmittedTask(Task $task): void
    {
        $reason = $task->submitted_at->toDateString() <= $task->deadline->toDateString() ? 'on_time' : 'late';

        static::firstOrCreate(
            ['task_id' => $task->id, 'reason' => $reason],
            ['user_id' => $task->assigned_to, 'points' => config("points.$reason")],
        );
    }

    /** Bonus when content is approved in V1 */
    public static function forApprovedContent(Content $content): void
    {
        if ($content->current_version !== 1) {
            return;
        }

        static::firstOrCreate(
            ['content_id' => $content->id, 'reason' => 'first_approve'],
            ['user_id' => $content->task->assigned_to, 'points' => config('points.first_approve')],
        );
    }

    public function label(): string
    {
        return self::LABELS[$this->reason] ?? $this->reason;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
