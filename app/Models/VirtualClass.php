<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VirtualClass extends Model
{
  protected $fillable = [
    'school_id',
    'uploaded_by_user_id',
    'term_subject_id',
    'class_type',
    'title',
    'description',
    'provider',
    'provider_room_id',
    'staff_room_code',
    'student_room_code',
    'status',
    'meeting_link',
    'starts_at',
    'ends_at',
    'live_started_at',
    'live_ended_at',
  ];

  protected $casts = [
    'starts_at' => 'datetime',
    'ends_at' => 'datetime',
    'live_started_at' => 'datetime',
    'live_ended_at' => 'datetime',
  ];
}
