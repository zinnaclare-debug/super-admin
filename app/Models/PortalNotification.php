<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalNotification extends Model
{
    protected $fillable = ['school_id', 'user_id', 'type', 'title', 'message', 'url', 'data', 'read_at'];

    protected $casts = ['data' => 'array', 'read_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}