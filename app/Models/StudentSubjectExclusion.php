<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSubjectExclusion extends Model
{
    protected $fillable = [
        'school_id',
        'academic_session_id',
        'class_id',
        'subject_id',
        'student_id',
    ];
}

