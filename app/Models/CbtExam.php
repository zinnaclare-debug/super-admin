<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CbtExam extends Model
{
  protected $fillable = [
    'school_id',
    'teacher_user_id',
    'term_subject_id',
    'title',
    'instructions',
    'starts_at',
    'ends_at',
    'duration_minutes',
    'status',
    'security_policy',
  ];

  protected $casts = [
    'starts_at' => 'datetime',
    'ends_at' => 'datetime',
    'security_policy' => 'array',
  ];
}

