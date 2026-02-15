<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VirtualClass extends Model
{
  protected $fillable = [
    'school_id',
    'uploaded_by_user_id',
    'term_subject_id',
    'title',
    'description',
    'meeting_link',
    'starts_at',
  ];
}

