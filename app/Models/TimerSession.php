<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimerSession extends Model
{
    use SoftDeletes;

    protected $table = 'timer_sessions';

    protected $fillable = [
        'kid_id',
        'category_id',
        'category_name',
        'phase',
        'started_at',
        'ended_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(Kid::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function scopeWork(Builder $query): Builder
    {
        return $query->where('phase', 'work');
    }

    public function scopeBreak(Builder $query): Builder
    {
        return $query->where('phase', 'break');
    }
}
