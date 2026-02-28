<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassDepartment extends Model
{
    protected $table = 'class_departments';

    protected $fillable = [
        'school_id',
        'class_id',
        'name',
        'class_teacher_user_id',
    ];

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'class_teacher_user_id');
    }
}
