<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\ClassDepartment;

class Enrollment extends Model
{
    protected $table = 'enrollments';

    protected $fillable = [
        'school_id',
        'student_id',
        'class_id',
        'term_id',
        'department_id',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(ClassDepartment::class, 'department_id');
    }
}
