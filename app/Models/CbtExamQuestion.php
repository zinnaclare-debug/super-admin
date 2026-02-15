<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CbtExamQuestion extends Model
{
  protected $fillable = [
    'school_id',
    'cbt_exam_id',
    'question_bank_question_id',
    'question_text',
    'option_a',
    'option_b',
    'option_c',
    'option_d',
    'correct_option',
    'explanation',
    'media_path',
    'media_type',
    'position',
  ];
}

