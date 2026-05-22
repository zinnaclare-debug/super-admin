<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolPublicHoliday extends Model
{
    protected $fillable = [
        'school_id',
        'holiday_date',
        'title',
        'description',
        'created_by_user_id',
    ];

    protected $casts = [
        'holiday_date' => 'date',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
