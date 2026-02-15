<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBankQuestion extends Model
{
  protected $fillable = [
    'school_id',
    'teacher_user_id',
    'subject_id',
    'question_text',
    'option_a',
    'option_b',
    'option_c',
    'option_d',
    'correct_option',
    'explanation',
    'source_type',
    'media_path',
    'media_type',
  ];
}

