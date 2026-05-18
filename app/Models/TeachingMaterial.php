<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeachingMaterial extends Model
{
    public const CATEGORY_TOPIC = 'topic';
    public const CATEGORY_EXAM_QUESTION = 'exam_question';
    public const CATEGORY_LESSON_NOTE = 'lesson_note';
    public const CATEGORY_LESSON_PLAN = 'lesson_plan';

    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'school_id',
        'staff_user_id',
        'academic_session_id',
        'term_id',
        'term_subject_id',
        'subject_id',
        'category',
        'title',
        'original_name',
        'mime_type',
        'file_path',
        'file_size',
        'compressed_size',
        'status',
        'processing_note',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'compressed_size' => 'integer',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function termSubject(): BelongsTo
    {
        return $this->belongsTo(TermSubject::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
