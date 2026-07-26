<?php
// app/Models/ActivityLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ActivityLog extends Model
{
    public $timestamps = false;
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'subject_type', 'subject_id',
        'event', 'properties', 'ip_address',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForSubject(Builder $query, string $type, int $id): Builder
    {
        return $query->where('subject_type', $type)->where('subject_id', $id);
    }

    /**
     * Static helper for logging from anywhere in the app.
     * Usage: ActivityLog::record($product, 'updated', ['price' => [22999, 24999]]);
     */
    public static function record(object $subject, string $event, array $properties = []): self
    {
        return self::create([
            'user_id'      => auth()->id(),
            'subject_type' => get_class($subject),
            'subject_id'   => $subject->id,
            'event'        => $event,
            'properties'   => $properties,
            'ip_address'   => request()?->ip(),
        ]);
    }
}
