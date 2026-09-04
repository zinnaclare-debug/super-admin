<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeachingAiGenerationJob extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'school_id', 'staff_user_id', 'academic_session_id', 'term_id', 'term_subject_id',
        'subject_id', 'topics', 'question_count', 'status', 'progress', 'result_json',
        'exam_questions_path', 'lesson_notes_path', 'lesson_plan_path', 'error_message', 'completed_at',
    ];

    protected $casts = [
        'question_count' => 'integer',
        'progress' => 'integer',
        'result_json' => 'array',
        'completed_at' => 'datetime',
    ];
}
