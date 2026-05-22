<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendantRecord extends Model
{
    protected $fillable = [
        'school_id',
        'staff_user_id',
        'attendance_date',
        'signed_in_at',
        'status',
        'latitude',
        'longitude',
        'accuracy_meters',
        'distance_from_school_meters',
        'location_status',
        'ip_address',
        'user_agent',
        'device_info',
        'admin_note',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'signed_in_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy_meters' => 'integer',
        'distance_from_school_meters' => 'integer',
        'device_info' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
