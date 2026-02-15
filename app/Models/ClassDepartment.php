<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassDepartment extends Model
{
    protected $table = 'class_departments';

    protected $fillable = [
        'school_id',
        'class_id',
        'name',
    ];
}
