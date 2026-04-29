<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Roster extends Model
{
    protected $fillable = [
        'filename',
        'total_headcount',
        'total_labor_spend',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
