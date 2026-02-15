<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermAttendanceSetting extends Model
{
  protected $fillable = [
    'school_id',
    'class_id',
    'term_id',
    'total_school_days',
    'next_term_begin_date',
    'set_by_user_id',
  ];
}
