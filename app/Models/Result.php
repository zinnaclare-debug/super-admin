<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
  protected $fillable = [
    'school_id',
    'term_subject_id',
    'student_id',
    'ca',
    'ca_breakdown',
    'exam',
  ];

  protected $casts = [
    'ca_breakdown' => 'array',
  ];
}
