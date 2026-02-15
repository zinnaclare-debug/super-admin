<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicMaterial extends Model
{
  protected $fillable = [
    'school_id',
    'teacher_user_id',
    'term_subject_id',
    'title',
    'file_path',
    'original_name',
    'mime_type',
    'size',
  ];
}
