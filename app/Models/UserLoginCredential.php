<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLoginCredential extends Model
{
    protected $fillable = [
        'user_id',
        'school_id',
        'role',
        'name',
        'username',
        'email',
        'password_encrypted',
        'last_password_set_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'last_password_set_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

