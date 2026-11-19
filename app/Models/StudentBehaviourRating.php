<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentBehaviourRating extends Model
{
  protected $fillable = [
    'school_id',
    'class_id',
    'term_id',
    'student_id',
    'handwriting',
    'speech',
    'attitude',
    'reading',
    'punctuality',
    'teamwork',
    'self_control',
    'ratings',
    'learning_observations',
    'teacher_comment',
    'set_by_user_id',
  ];

  protected $casts = [
    'ratings' => 'array',
    'learning_observations' => 'array',
  ];
}
