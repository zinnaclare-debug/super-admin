<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBankGenerationJob extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'school_id',
        'teacher_user_id',
        'subject_id',
        'status',
        'prompt',
        'question_count',
        'import_to_bank',
        'generated_count',
        'imported_count',
        'result_json',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'import_to_bank' => 'boolean',
        'result_json' => 'array',
        'completed_at' => 'datetime',
    ];
}
