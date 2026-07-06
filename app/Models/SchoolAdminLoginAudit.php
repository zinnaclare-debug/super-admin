<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolAdminLoginAudit extends Model
{
    protected $fillable = [
        'school_id',
        'user_id',
        'ip_address',
        'forwarded_ip',
        'user_agent',
        'device_info',
        'device_type',
        'device_model',
        'browser',
        'platform',
        'pc_name',
        'location_label',
        'logged_in_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'logged_in_at' => 'datetime',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
