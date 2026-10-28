<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BulkRegistrationImport extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'school_id', 'requested_by_user_id', 'import_type', 'status',
        'total_rows', 'processed_rows', 'imported_rows', 'payload_encrypted',
        'credentials_encrypted', 'error_message', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}