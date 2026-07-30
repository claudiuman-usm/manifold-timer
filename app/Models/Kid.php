<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kid extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'pin', 'color', 'dark_mode',
        'work_minutes', 'break_minutes', 'cutoff_time',
    ];

    protected $hidden = ['pin'];

    protected function casts(): array
    {
        return [
            'dark_mode' => 'boolean',
            'work_minutes' => 'integer',
            'break_minutes' => 'integer',
        ];
    }

    /** True when this kid has any custom cycle setting (vs. inheriting the default). */
    public function hasCycleOverride(): bool
    {
        return $this->work_minutes !== null
            || $this->break_minutes !== null
            || $this->cutoff_time !== null;
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TimerSession::class);
    }
}
