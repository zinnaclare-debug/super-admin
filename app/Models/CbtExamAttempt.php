<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CbtExamAttempt extends Model
{
    protected $fillable = [
        'school_id',
        'cbt_exam_id',
        'student_id',
        'user_id',
        'status',
        'submit_mode',
        'answers',
        'total_questions',
        'attempted',
        'correct',
        'wrong',
        'unanswered',
        'score_percent',
        'security_warnings',
        'head_movement_warnings',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'score_percent' => 'float',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
}

