<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolSubscriptionSetting extends Model
{
    protected $fillable = [
        'school_id',
        'amount_per_student_per_term',
        'currency',
        'tax_percent',
        'allow_termly',
        'allow_yearly',
        'is_free_version',
        'manual_status_override',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'notes',
        'configured_by_user_id',
    ];

    protected $casts = [
        'amount_per_student_per_term' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'allow_termly' => 'boolean',
        'allow_yearly' => 'boolean',
        'is_free_version' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function configuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by_user_id');
    }
}
