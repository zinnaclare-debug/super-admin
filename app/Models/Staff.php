<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $fillable = [
        'user_id',
        'school_id',
        'sex',
        'dob',
        'address',
        'position',
        'education_level',
        'education_levels',
        'photo_path',
    ];

    protected $casts = [
        'education_levels' => 'array',
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
