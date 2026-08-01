<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedbackThread extends Model
{
    use SoftDeletes;

    protected $fillable = ['kid_id', 'type', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(Kid::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(FeedbackMessage::class, 'thread_id');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }
}
