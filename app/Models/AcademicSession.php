<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicSession extends Model
{
    protected $fillable = [
        'school_id',
        'session_name',
        'academic_year',
        'status',
        'levels',
    ];

    protected $casts = [
        'levels' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
