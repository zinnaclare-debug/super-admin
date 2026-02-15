<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'school_id',
        'academic_session_id',
        'level',
        'name',
        'class_teacher_user_id',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'class_teacher_user_id');
    }
}
