<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolAttendantSetting extends Model
{
    protected $fillable = [
        'school_id',
        'latitude',
        'longitude',
        'radius_meters',
        'timezone',
        'working_days',
        'sign_in_start_time',
        'sign_in_end_time',
        'late_after_time',
        'allow_outside_location',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_meters' => 'integer',
        'working_days' => 'array',
        'allow_outside_location' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
