<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolSubscriptionReceipt extends Model
{
    protected $fillable = [
        'school_id',
        'school_subscription_invoice_id',
        'receipt_number',
        'subtotal_paid',
        'processing_fee_paid',
        'total_paid',
        'currency',
        'recorded_by_user_id',
        'meta',
    ];

    protected $casts = [
        'subtotal_paid' => 'decimal:2',
        'processing_fee_paid' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'meta' => 'array',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SchoolSubscriptionInvoice::class, 'school_subscription_invoice_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}