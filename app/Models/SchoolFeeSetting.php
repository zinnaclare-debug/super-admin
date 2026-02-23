<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolFeeSetting extends Model
{
    protected $fillable = [
        'school_id',
        'academic_session_id',
        'term_id',
        'level',
        'amount_due',
        'set_by_user_id',
    ];

    protected $casts = [
        'level' => 'string',
        'amount_due' => 'decimal:2',
    ];
}
