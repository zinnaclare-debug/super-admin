<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolSubscriptionInvoice extends Model
{
    protected $fillable = [
        'school_id',
        'academic_session_id',
        'term_id',
        'billing_cycle',
        'reference',
        'status',
        'payment_channel',
        'student_count_snapshot',
        'amount_per_student_snapshot',
        'subtotal',
        'tax_percent_snapshot',
        'tax_amount',
        'total_amount',
        'currency',
        'paystack_access_code',
        'paystack_authorization_url',
        'paystack_status',
        'paystack_gateway_response',
        'paystack_channel',
        'submitted_by_user_id',
        'approved_by_user_id',
        'paid_at',
        'meta',
    ];

    protected $casts = [
        'amount_per_student_snapshot' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_percent_snapshot' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
