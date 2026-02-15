<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAttendance extends Model
{
  protected $fillable = [
    'school_id',
    'class_id',
    'term_id',
    'student_id',
    'days_present',
    'comment',
    'set_by_user_id',
  ];
}
