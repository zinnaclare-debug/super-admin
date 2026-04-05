<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolAdmissionApplication extends Model
{
    protected $fillable = [
        'school_id',
        'application_number',
        'full_name',
        'phone',
        'email',
        'applying_for_class',
        'exam_status',
        'score',
        'result_status',
        'exam_submitted_at',
        'exam_answers',
        'exam_result',
    ];

    protected $casts = [
        'score' => 'integer',
        'exam_submitted_at' => 'datetime',
        'exam_answers' => 'array',
        'exam_result' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}

