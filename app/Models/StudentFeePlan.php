<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFeePlan extends Model
{
    protected $fillable = [
        'school_id',
        'student_id',
        'academic_session_id',
        'term_id',
        'line_items',
        'amount_due',
        'configured_by_user_id',
    ];

    protected $casts = [
        'line_items' => 'array',
        'amount_due' => 'decimal:2',
    ];
}

