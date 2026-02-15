<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LevelDepartment extends Model
{
    protected $fillable = [
        'school_id',
        'academic_session_id',
        'level',
        'name',
    ];
}
