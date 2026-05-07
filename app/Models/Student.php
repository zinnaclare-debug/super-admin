<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'school_id',
        'education_level',
        'sex',
        'religion',
        'dob',
        'address',
        'photo_path',
        'status',
        'graduated_at',
        'graduation_session_id',
    ];

    protected $casts = [
        'graduated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
