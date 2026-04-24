<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'description',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an action. Call this from controllers after any state change.
     */
    public static function record(
        ?User $actor,
        string $action,
        string $targetType,
        int $targetId,
        string $description,
        array $meta = []
    ): void {
        static::create([
            'user_id'     => $actor?->id,
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'description' => $description,
            'meta'        => empty($meta) ? null : $meta,
        ]);
    }
}