<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CycleSetting extends Model
{
    use SoftDeletes;

    protected $fillable = ['work_minutes', 'break_minutes', 'cutoff_time'];

    protected function casts(): array
    {
        return [
            'work_minutes' => 'integer',
            'break_minutes' => 'integer',
        ];
    }

    /**
     * The single global settings row (v1 is global). Created if missing.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'work_minutes' => 120,
            'break_minutes' => 45,
            'cutoff_time' => '00:00:00',
        ]);
    }
}
