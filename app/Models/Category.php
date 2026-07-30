<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'active'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TimerSession::class);
    }
}
