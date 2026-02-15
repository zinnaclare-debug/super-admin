<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassActivity extends Model
{
  protected $fillable = [
    'school_id',
    'uploaded_by_user_id',
    'term_subject_id',
    'title',
    'description',
    'file_path',
    'original_name',
    'mime_type',
    'size',
  ];
}
