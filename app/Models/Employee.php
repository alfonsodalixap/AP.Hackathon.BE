<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    protected $fillable = [
        'roster_id',
        'employee_id',
        'job_title',
        'job_function',
        'seniority',
        'country',
        'fully_loaded_cost',
    ];

    protected $casts = [
        'fully_loaded_cost' => 'integer',
    ];

    public function roster(): BelongsTo
    {
        return $this->belongsTo(Roster::class);
    }
}
