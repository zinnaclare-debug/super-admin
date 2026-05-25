<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendantRecord extends Model
{
    protected $fillable = [
        'school_id',
        'academic_session_id',
        'term_id',
        'staff_user_id',
        'attendance_date',
        'signed_in_at',
        'signed_out_at',
        'status',
        'latitude',
        'longitude',
        'accuracy_meters',
        'distance_from_school_meters',
        'location_status',
        'sign_out_latitude',
        'sign_out_longitude',
        'sign_out_accuracy_meters',
        'sign_out_distance_from_school_meters',
        'sign_out_location_status',
        'ip_address',
        'sign_out_ip_address',
        'user_agent',
        'sign_out_user_agent',
        'device_info',
        'sign_out_device_info',
        'admin_note',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'signed_in_at' => 'datetime',
        'signed_out_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy_meters' => 'integer',
        'distance_from_school_meters' => 'integer',
        'sign_out_latitude' => 'float',
        'sign_out_longitude' => 'float',
        'sign_out_accuracy_meters' => 'integer',
        'sign_out_distance_from_school_meters' => 'integer',
        'device_info' => 'array',
        'sign_out_device_info' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
