<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolFeePayment extends Model
{
    protected $fillable = [
        'school_id',
        'student_id',
        'student_user_id',
        'academic_session_id',
        'term_id',
        'amount_due_snapshot',
        'amount_paid',
        'currency',
        'status',
        'reference',
        'paystack_access_code',
        'paystack_authorization_url',
        'paystack_status',
        'paystack_gateway_response',
        'paystack_channel',
        'paid_at',
        'meta',
    ];

    protected $casts = [
        'amount_due_snapshot' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];
}
