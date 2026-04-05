<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolAdmissionApplication extends Model
{
    protected $fillable = [
        'school_id',
        'application_number',
        'full_name',
        'phone',
        'email',
        'applying_for_class',
        'payment_status',
        'payment_reference',
        'amount_due',
        'tax_rate',
        'tax_amount',
        'amount_total',
        'amount_paid',
        'paystack_access_code',
        'paystack_authorization_url',
        'paystack_status',
        'paystack_gateway_response',
        'paystack_channel',
        'paid_at',
        'exam_status',
        'score',
        'result_status',
        'admin_result_status',
        'exam_submitted_at',
        'exam_answers',
        'exam_result',
    ];

    protected $casts = [
        'score' => 'integer',
        'amount_due' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'amount_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'paid_at' => 'datetime',
        'exam_submitted_at' => 'datetime',
        'exam_answers' => 'array',
        'exam_result' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
